<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\Domain;
use App\Models\SecurityScan;
use Illuminate\Support\Facades\Http;

/**
 * OSINT & security scanner for OWNED/authorized domains.
 *
 * Each check is isolated (its own try/catch) so a single failure never aborts
 * the whole scan. Designed for the user's own properties registered in the app.
 */
class SecurityScannerService
{
    /** Common ports probed via TCP connect. */
    private const PORTS = [
        21 => 'FTP', 22 => 'SSH', 25 => 'SMTP', 80 => 'HTTP', 443 => 'HTTPS',
        3306 => 'MySQL', 5432 => 'PostgreSQL', 6379 => 'Redis', 8080 => 'HTTP-alt', 8443 => 'HTTPS-alt',
    ];

    /** Ports that should normally NOT be publicly reachable. */
    private const RISKY_PORTS = [22, 25, 3306, 5432, 6379];

    private const SECURITY_HEADERS = [
        'strict-transport-security' => ['label' => 'HSTS', 'weight' => 15],
        'content-security-policy' => ['label' => 'CSP', 'weight' => 15],
        'x-frame-options' => ['label' => 'X-Frame-Options', 'weight' => 10],
        'x-content-type-options' => ['label' => 'X-Content-Type-Options', 'weight' => 5],
        'referrer-policy' => ['label' => 'Referrer-Policy', 'weight' => 5],
        'permissions-policy' => ['label' => 'Permissions-Policy', 'weight' => 5],
    ];

    public function scan(Domain $domain, ?string $userId = null): SecurityScan
    {
        $startedAt = now();
        $url = rtrim($domain->url, '/');
        $host = parse_url($url, PHP_URL_HOST) ?: $url;

        // Single HTTP fetch reused for headers / HTTP version / tech detection.
        $http = $this->fetchHttp($url);

        $results = [
            'dns' => $this->safe(fn () => $this->checkDns($host)),
            'ssl' => $this->safe(fn () => $this->checkSsl($host)),
            'headers' => $this->safe(fn () => $this->checkHeaders($http)),
            'http_versions' => $this->safe(fn () => $this->checkHttpVersions($http)),
            'whois' => $this->safe(fn () => $this->checkWhois($host)),
            'ports' => $this->safe(fn () => $this->checkPorts($host)),
            'robots' => $this->safe(fn () => $this->checkRobots($url)),
            'dnssec' => $this->safe(fn () => $this->checkDnssec($host)),
            'tech_stack' => $this->safe(fn () => $this->checkTechStack($http)),
        ];

        [$score, $grade] = $this->grade($results);

        return SecurityScan::create([
            'domain_id' => $domain->id,
            'target_url' => $url,
            'target_host' => $host,
            'status' => 'completed',
            'grade' => $grade,
            'score' => $score,
            'results' => $results,
            'requested_by' => $userId,
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }

    /** Wrap a check so failures are reported, never fatal. */
    private function safe(callable $fn): array
    {
        try {
            return ['ok' => true] + $fn();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => class_basename($e).': '.$e->getMessage()];
        }
    }

    // ---- HTTP fetch (shared) ----

    private function fetchHttp(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'AdsDashboard-SecInspector/1.0',
            CURLOPT_HTTP_VERSION => defined('CURL_HTTP_VERSION_2TLS') ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_NONE,
            CURLOPT_ENCODING => '',
        ]);
        $raw = curl_exec($ch);
        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        curl_close($ch);

        $headers = [];
        $body = '';
        if (is_string($raw)) {
            $headerSize = (int) ($info['header_size'] ?? 0);
            $rawHeaders = substr($raw, 0, $headerSize);
            $body = substr($raw, $headerSize);
            $headers = $this->parseHeaders($rawHeaders);
        }

        return ['info' => $info, 'headers' => $headers, 'body' => $body, 'error' => $err];
    }

    private function parseHeaders(string $raw): array
    {
        // Keep the LAST header block (after redirects).
        $blocks = preg_split("/\r\n\r\n/", trim($raw));
        $last = end($blocks) ?: '';
        $headers = [];
        foreach (preg_split("/\r\n|\n/", $last) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }

        return $headers;
    }

    // ---- DNS ----

    private function checkDns(string $host): array
    {
        $records = [];
        foreach ([
            'A' => DNS_A, 'AAAA' => DNS_AAAA, 'MX' => DNS_MX,
            'NS' => DNS_NS, 'TXT' => DNS_TXT, 'CNAME' => DNS_CNAME,
        ] as $type => $const) {
            $rows = @dns_get_record($host, $const) ?: [];
            $records[$type] = array_values(array_map(function ($r) {
                return $r['ip'] ?? $r['ipv6'] ?? $r['target'] ?? $r['txt'] ?? ($r['host'] ?? '');
            }, $rows));
        }

        return ['records' => array_filter($records, fn ($v) => $v !== [])];
    }

    // ---- SSL ----

    private function checkSsl(string $host): array
    {
        $ctx = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $client = @stream_socket_client(
            "ssl://{$host}:443", $errno, $errstr, 8,
            STREAM_CLIENT_CONNECT, $ctx,
        );

        if (! $client) {
            return ['valid' => false, 'error' => $errstr ?: 'TLS connection failed'];
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (! $cert) {
            return ['valid' => false, 'error' => 'No peer certificate'];
        }

        $p = openssl_x509_parse($cert);
        $validTo = $p['validTo_time_t'] ?? 0;
        $validFrom = $p['validFrom_time_t'] ?? 0;
        $now = time();
        $daysRemaining = $validTo ? (int) floor(($validTo - $now) / 86400) : null;

        $sans = [];
        if (! empty($p['extensions']['subjectAltName'])) {
            $sans = array_map('trim', explode(',', str_replace('DNS:', '', $p['extensions']['subjectAltName'])));
        }

        return [
            'valid' => $now >= $validFrom && $now <= $validTo,
            'issuer' => $p['issuer']['O'] ?? ($p['issuer']['CN'] ?? null),
            'subject' => $p['subject']['CN'] ?? null,
            'valid_from' => $validFrom ? date('c', $validFrom) : null,
            'valid_to' => $validTo ? date('c', $validTo) : null,
            'days_remaining' => $daysRemaining,
            'signature_algorithm' => $p['signatureTypeSN'] ?? null,
            'sans' => array_slice($sans, 0, 20),
        ];
    }

    // ---- Headers ----

    private function checkHeaders(array $http): array
    {
        $headers = $http['headers'] ?? [];
        $present = [];
        $missing = [];
        foreach (self::SECURITY_HEADERS as $key => $meta) {
            if (isset($headers[$key])) {
                $present[$meta['label']] = $headers[$key];
            } else {
                $missing[] = $meta['label'];
            }
        }

        return [
            'server' => $headers['server'] ?? null,
            'present' => $present,
            'missing' => $missing,
            'all' => $headers,
        ];
    }

    // ---- HTTP/2 & HTTP/3 ----

    private function checkHttpVersions(array $http): array
    {
        $info = $http['info'] ?? [];
        $headers = $http['headers'] ?? [];
        $negotiated = match ((int) ($info['http_version'] ?? 0)) {
            1 => '1.0',
            2 => '1.1',
            3 => '2',
            30, 31, 32 => '3',
            default => 'unknown',
        };

        $altSvc = $headers['alt-svc'] ?? '';
        $h3Advertised = str_contains($altSvc, 'h3');

        return [
            'negotiated' => $negotiated,
            'http2' => in_array($negotiated, ['2', '3'], true),
            'http3' => $negotiated === '3' || $h3Advertised,
            'alt_svc' => $altSvc ?: null,
        ];
    }

    // ---- WHOIS (via RDAP, HTTP-based) ----

    private function checkWhois(string $host): array
    {
        // Registrable domain (drop leading "www." etc. for RDAP lookups).
        $domain = $this->registrableDomain($host);
        $res = Http::timeout(10)->acceptJson()->get("https://rdap.org/domain/{$domain}");

        if (! $res->successful()) {
            return ['domain' => $domain, 'available' => false, 'note' => 'RDAP lookup returned '.$res->status()];
        }

        $data = $res->json();
        $events = collect($data['events'] ?? [])->mapWithKeys(
            fn ($e) => [$e['eventAction'] => $e['eventDate'] ?? null]
        );

        $registrar = collect($data['entities'] ?? [])
            ->first(fn ($e) => in_array('registrar', $e['roles'] ?? [], true));
        $registrarName = null;
        foreach ($registrar['vcardArray'][1] ?? [] as $field) {
            if (($field[0] ?? '') === 'fn') {
                $registrarName = $field[3] ?? null;
            }
        }

        return [
            'domain' => $data['ldhName'] ?? $domain,
            'registrar' => $registrarName,
            'statuses' => $data['status'] ?? [],
            'created' => $events['registration'] ?? null,
            'expires' => $events['expiration'] ?? null,
            'last_changed' => $events['last changed'] ?? null,
            'nameservers' => array_map(fn ($ns) => $ns['ldhName'] ?? '', $data['nameservers'] ?? []),
            'dnssec_signed' => $data['secureDNS']['delegationSigned'] ?? null,
        ];
    }

    // ---- Ports ----

    private function checkPorts(string $host): array
    {
        $open = [];
        $closed = [];
        foreach (self::PORTS as $port => $name) {
            $conn = @fsockopen($host, $port, $errno, $errstr, 1.2);
            if ($conn) {
                fclose($conn);
                $open[] = ['port' => $port, 'service' => $name, 'risky' => in_array($port, self::RISKY_PORTS, true)];
            } else {
                $closed[] = $port;
            }
        }

        return ['open' => $open, 'closed' => $closed, 'scanned' => array_keys(self::PORTS)];
    }

    // ---- robots.txt ----

    private function checkRobots(string $url): array
    {
        $res = Http::timeout(8)->withHeaders(['User-Agent' => 'AdsDashboard-SecInspector/1.0'])->get($url.'/robots.txt');
        if (! $res->successful()) {
            return ['exists' => false, 'status' => $res->status()];
        }

        $body = $res->body();
        $sitemaps = [];
        $disallow = 0;
        foreach (preg_split('/\r\n|\n/', $body) as $line) {
            $line = trim($line);
            if (stripos($line, 'sitemap:') === 0) {
                $sitemaps[] = trim(substr($line, 8));
            } elseif (stripos($line, 'disallow:') === 0) {
                $disallow++;
            }
        }

        return [
            'exists' => true,
            'size_bytes' => strlen($body),
            'disallow_rules' => $disallow,
            'sitemaps' => $sitemaps,
            'excerpt' => mb_substr($body, 0, 500),
        ];
    }

    // ---- DNSSEC (DNS-over-HTTPS) ----

    private function checkDnssec(string $host): array
    {
        $domain = $this->registrableDomain($host);
        $res = Http::timeout(8)->acceptJson()->get('https://dns.google/resolve', [
            'name' => $domain, 'type' => 'DS',
        ]);
        $data = $res->json();
        $hasDs = ! empty($data['Answer']);
        $authenticated = (bool) ($data['AD'] ?? false);

        return [
            'enabled' => $hasDs || $authenticated,
            'ds_records' => $hasDs ? count($data['Answer']) : 0,
            'authenticated_data' => $authenticated,
        ];
    }

    // ---- Technology stack ----

    private function checkTechStack(array $http): array
    {
        $headers = $http['headers'] ?? [];
        $body = $http['body'] ?? '';
        $detected = [];

        $add = function (string $name, string $category) use (&$detected) {
            $detected[$name] = ['name' => $name, 'category' => $category];
        };

        // Header signatures.
        if (! empty($headers['server'])) {
            $add($headers['server'], 'Web Server');
        }
        if (! empty($headers['x-powered-by'])) {
            $add($headers['x-powered-by'], 'Language/Framework');
        }
        if (isset($headers['cf-ray']) || str_contains(strtolower($headers['server'] ?? ''), 'cloudflare')) {
            $add('Cloudflare', 'CDN/WAF');
        }
        if (isset($headers['x-amz-cf-id']) || isset($headers['x-amz-cf-pop'])) {
            $add('Amazon CloudFront', 'CDN');
        }
        if (isset($headers['x-akamai-transformed'])) {
            $add('Akamai', 'CDN');
        }

        // Body signatures.
        $sig = [
            'WordPress' => ['/wp-content/i', '/wp-includes/i'],
            'Next.js' => ['/__NEXT_DATA__/', '#/_next/#'],
            'Nuxt.js' => ['/__NUXT__/'],
            'React' => ['/data-reactroot/', '/react(?:-dom)?\.production/i'],
            'Vue.js' => ['/data-v-[0-9a-f]{8}/'],
            'Google Tag Manager' => ['/googletagmanager\.com\/gtm\.js/i', '/GTM-[A-Z0-9]+/'],
            'Google Analytics' => ['/gtag\(/', '/google-analytics\.com/i', '/\bG-[A-Z0-9]{6,}\b/'],
            'jQuery' => ['/jquery(?:\.min)?\.js/i'],
            'Bootstrap' => ['/bootstrap(?:\.min)?\.(?:css|js)/i'],
            'AMP' => ['/<html[^>]*\bamp\b/i', '/cdn\.ampproject\.org/i'],
        ];
        foreach ($sig as $name => $patterns) {
            foreach ($patterns as $p) {
                if (@preg_match($p, $body)) {
                    $add($name, 'Library/Tag');
                    break;
                }
            }
        }

        return ['detected' => array_values($detected)];
    }

    // ---- Scoring ----

    private function grade(array $results): array
    {
        $score = 100;

        // Security headers.
        $missing = $results['headers']['missing'] ?? array_column(self::SECURITY_HEADERS, 'label');
        foreach (self::SECURITY_HEADERS as $meta) {
            if (in_array($meta['label'], $missing, true)) {
                $score -= $meta['weight'];
            }
        }

        // SSL.
        $ssl = $results['ssl'] ?? [];
        if (($ssl['valid'] ?? false) !== true) {
            $score -= 25;
        } elseif (($ssl['days_remaining'] ?? 99) < 14) {
            $score -= 10;
        }

        // DNSSEC.
        if (($results['dnssec']['enabled'] ?? false) !== true) {
            $score -= 10;
        }

        // Risky open ports.
        foreach ($results['ports']['open'] ?? [] as $p) {
            if (! empty($p['risky'])) {
                $score -= 10;
            }
        }

        $score = max(0, min(100, $score));
        $grade = match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };

        return [$score, $grade];
    }

    private function registrableDomain(string $host): string
    {
        $host = preg_replace('/^www\./i', '', strtolower($host));

        return $host ?: '';
    }
}

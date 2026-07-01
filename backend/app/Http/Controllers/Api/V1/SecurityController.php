<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SecurityScanRequest;
use App\Models\Domain;
use App\Models\SecurityScan;
use App\Services\Security\ScanRateLimiter;
use App\Services\Security\SecurityScannerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __construct(
        private readonly SecurityScannerService $scanner,
        private readonly ScanRateLimiter $limiter,
    ) {}

    /** Run a new scan (rate-limited via Upstash/Redis). */
    public function scan(SecurityScanRequest $request): JsonResponse
    {
        $userId = (string) $request->user()->id;

        $gate = $this->limiter->check($userId);
        if (! $gate['allowed']) {
            return ApiResponse::error([[
                'code' => 'RATE_LIMITED',
                'message' => "Batas scan terlampaui ({$gate['limit']}). Coba lagi nanti.",
                'field' => null,
            ]], 429, ['retry_after' => $gate['retry_after']]);
        }

        /** @var Domain $domain */
        $domain = Domain::findOrFail($request->validated('domain_id'));
        $scan = $this->scanner->scan($domain, $userId);

        return ApiResponse::success($this->detail($scan), [], 201);
    }

    /** Scan history (per domain). */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 20), 100);

        $paginator = SecurityScan::query()
            ->with('domain')
            ->when($request->query('domain_id'), fn ($q, $id) => $q->where('domain_id', $id))
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: max(1, (int) $request->integer('page', 1)));

        $paginator->getCollection()->transform(fn (SecurityScan $s) => $this->summary($s));

        return ApiResponse::paginated($paginator, $paginator->items());
    }

    public function show(string $id): JsonResponse
    {
        $scan = SecurityScan::with('domain')->findOrFail($id);

        return ApiResponse::success($this->detail($scan));
    }

    /** Download the full scan result as a JSON file. */
    public function export(string $id): JsonResponse
    {
        $scan = SecurityScan::with('domain')->findOrFail($id);
        $filename = 'security-scan-'.$scan->target_host.'-'.$scan->created_at->format('Ymd-His').'.json';

        return response()->json($this->detail($scan), 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function summary(SecurityScan $s): array
    {
        return [
            'id' => $s->id,
            'domain' => $s->domain?->name,
            'target_host' => $s->target_host,
            'status' => $s->status,
            'grade' => $s->grade,
            'score' => $s->score,
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }

    private function detail(SecurityScan $s): array
    {
        return $this->summary($s) + [
            'target_url' => $s->target_url,
            'started_at' => $s->started_at?->toIso8601String(),
            'finished_at' => $s->finished_at?->toIso8601String(),
            'results' => $s->results,
        ];
    }
}

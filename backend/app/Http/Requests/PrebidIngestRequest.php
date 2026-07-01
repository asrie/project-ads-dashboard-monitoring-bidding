<?php

declare(strict_types=1);

namespace App\Http\Requests;

class PrebidIngestRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            // Publisher origin/host or URL (resolved to a domain server-side).
            'domain' => ['required', 'string', 'max:255'],
            'ingest_key' => ['nullable', 'string'], // alt to X-Ingest-Key header

            'auctions' => ['required', 'array', 'min:1', 'max:200'],
            'auctions.*.auction_id' => ['required', 'string', 'max:128'],
            'auctions.*.page_path' => ['nullable', 'string', 'max:512'],
            'auctions.*.device' => ['nullable', 'in:desktop,mobile,tablet'],
            'auctions.*.started_at' => ['nullable', 'date'],
            'auctions.*.duration_ms' => ['nullable', 'integer', 'min:0', 'max:120000'],
            'auctions.*.bidder_count' => ['nullable', 'integer', 'min:0', 'max:255'],
            'auctions.*.bids_received' => ['nullable', 'integer', 'min:0', 'max:255'],
            'auctions.*.timeouts' => ['nullable', 'integer', 'min:0', 'max:255'],
            'auctions.*.errors' => ['nullable', 'integer', 'min:0', 'max:255'],
            'auctions.*.won_bidder' => ['nullable', 'string', 'max:64'],
            'auctions.*.cpm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'auctions.*.status' => ['nullable', 'in:completed,timeout,error'],
        ];
    }
}

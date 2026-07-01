<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PagePreviewRequest;
use App\Models\Domain;
use App\Models\Page;
use App\Models\PagePreview;
use App\Services\Preview\PagePreviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreviewController extends Controller
{
    public function __construct(private readonly PagePreviewService $service) {}

    public function capture(PagePreviewRequest $request): JsonResponse
    {
        if (! $this->service->configured()) {
            return ApiResponse::error([[
                'code' => 'RENDERER_DISABLED',
                'message' => 'Ad-layout renderer is not configured.',
                'field' => null,
            ]], 503);
        }

        /** @var Domain $domain */
        $domain = Domain::findOrFail($request->validated('domain_id'));
        $page = $request->validated('page_id') ? Page::find($request->validated('page_id')) : null;
        $device = $request->validated('device') ?? 'mobile';

        $preview = $this->service->capture($domain, $page, $device, (string) $request->user()->id);

        if ($preview->status === 'failed') {
            return ApiResponse::error([[
                'code' => 'CAPTURE_FAILED',
                'message' => $preview->error ?? 'Render failed.',
                'field' => null,
            ]], 502);
        }

        return ApiResponse::success($this->detail($preview), [], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 20), 100);

        $paginator = PagePreview::query()
            ->with('domain')
            ->when($request->query('domain_id'), fn ($q, $id) => $q->where('domain_id', $id))
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: max(1, (int) $request->integer('page', 1)));

        $paginator->getCollection()->transform(fn (PagePreview $p) => $this->summary($p));

        return ApiResponse::paginated($paginator, $paginator->items());
    }

    public function show(string $id): JsonResponse
    {
        $preview = PagePreview::with('domain')->findOrFail($id);

        return ApiResponse::success($this->detail($preview));
    }

    private function summary(PagePreview $p): array
    {
        return [
            'id' => $p->id,
            'domain' => $p->domain?->name,
            'url' => $p->url,
            'device' => $p->device,
            'status' => $p->status,
            'slot_count' => $p->slot_count,
            'captured_at' => $p->captured_at?->toIso8601String(),
        ];
    }

    private function detail(PagePreview $p): array
    {
        return $this->summary($p) + [
            'page_width' => $p->page_width,
            'page_height' => $p->page_height,
            'viewport_css_width' => $p->viewport_css_width,
            'slots' => $p->slots ?? [],
            'header' => $p->header,
            'image' => $this->service->imageDataUri($p),
        ];
    }
}

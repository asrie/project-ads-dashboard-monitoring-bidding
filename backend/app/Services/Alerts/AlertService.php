<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\Insight;
use App\Support\DashboardFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AlertService
{
    private const SORTABLE = ['triggered_at', 'severity', 'category', 'status'];

    /**
     * @param  array{severity?: ?string, category?: ?string, status?: ?string}  $extra
     */
    public function paginate(DashboardFilters $filters, array $extra = []): LengthAwarePaginator
    {
        $sortBy = in_array($filters->sortBy, self::SORTABLE, true) ? $filters->sortBy : 'triggered_at';
        $sortDir = $filters->sortDir === 'asc' ? 'asc' : 'desc';

        $query = Alert::query()
            ->with(['domain', 'acknowledger'])
            ->whereBetween('triggered_at', [$filters->dateFrom, $filters->dateTo])
            ->when($filters->domainId, fn ($q) => $q->where('domain_id', $filters->domainId))
            ->when($extra['severity'] ?? null, fn ($q, $v) => $q->where('severity', $v))
            ->when($extra['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($extra['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters->search, fn ($q) => $q->where('message', 'ilike', '%'.$filters->search.'%'));

        // Severity-aware ordering keeps the most urgent alerts on top.
        if ($sortBy === 'severity') {
            $query->orderByRaw("CASE severity
                WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END $sortDir");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $paginator = $query->paginate(perPage: $filters->perPage, page: $filters->page);
        $paginator->getCollection()->transform(fn (Alert $a) => $this->map($a));

        return $paginator;
    }

    public function detail(string $id): array
    {
        /** @var Alert $alert */
        $alert = Alert::with(['domain', 'acknowledger'])->findOrFail($id);

        return $this->map($alert);
    }

    /**
     * Acknowledge an open alert. Idempotent for already-acknowledged alerts.
     *
     * @throws ModelNotFoundException
     */
    public function acknowledge(string $id, string $userId): array
    {
        /** @var Alert $alert */
        $alert = Alert::findOrFail($id);

        if ($alert->status === AlertStatus::Open) {
            $alert->status = AlertStatus::Acknowledged;
            $alert->acknowledged_by = $userId;
            $alert->acknowledged_at = now();
            $alert->save();
        }

        return $this->map($alert->fresh(['domain', 'acknowledger']));
    }

    public function insights(DashboardFilters $filters): array
    {
        return Insight::query()
            ->with('domain')
            ->whereBetween('generated_at', [$filters->dateFrom, $filters->dateTo])
            ->when($filters->domainId, fn ($q) => $q->where('domain_id', $filters->domainId))
            ->orderByDesc('generated_at')
            ->limit(50)
            ->get()
            ->map(fn (Insight $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'description' => $i->description,
                'type' => $i->type,
                'impact' => $i->impact,
                'related_metric' => $i->related_metric,
                'domain' => $i->domain?->name,
                'generated_at' => $i->generated_at?->toIso8601String(),
            ])->all();
    }

    private function map(Alert $a): array
    {
        return [
            'id' => $a->id,
            'severity' => $a->severity->value,
            'category' => $a->category->value,
            'metric' => $a->metric,
            'current_value' => $a->current_value,
            'threshold_value' => $a->threshold_value,
            'entity_type' => $a->entity_type,
            'entity_id' => $a->entity_id,
            'entity_label' => $a->entity_label,
            'domain' => $a->domain?->name,
            'message' => $a->message,
            'suggested_action' => $a->suggested_action,
            'status' => $a->status->value,
            'acknowledged_by' => $a->acknowledger?->name,
            'acknowledged_at' => $a->acknowledged_at?->toIso8601String(),
            'triggered_at' => $a->triggered_at?->toIso8601String(),
        ];
    }
}

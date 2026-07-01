<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Normalizes validated dashboard filter input and applies sane defaults.
 */
final class DashboardFilters
{
    public readonly CarbonImmutable $dateFrom;

    public readonly CarbonImmutable $dateTo;

    public function __construct(
        public readonly ?string $domainId,
        public readonly ?string $pageId,
        ?string $dateFrom,
        ?string $dateTo,
        public readonly ?string $device,
        public readonly int $perPage = 20,
        public readonly int $page = 1,
        public readonly ?string $search = null,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'desc',
    ) {
        $to = $dateTo ? CarbonImmutable::parse($dateTo) : CarbonImmutable::now();
        $from = $dateFrom ? CarbonImmutable::parse($dateFrom) : $to->subDays(29);

        $this->dateTo = $to->endOfDay();
        $this->dateFrom = $from->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            domainId: $input['domain_id'] ?? null,
            pageId: $input['page_id'] ?? null,
            dateFrom: $input['date_from'] ?? null,
            dateTo: $input['date_to'] ?? null,
            device: $input['device'] ?? null,
            perPage: (int) ($input['per_page'] ?? 20),
            page: (int) ($input['page'] ?? 1),
            search: $input['search'] ?? null,
            sortBy: $input['sort_by'] ?? null,
            sortDir: $input['sort_dir'] ?? 'desc',
        );
    }

    public function dateFromDate(): string
    {
        return $this->dateFrom->toDateString();
    }

    public function dateToDate(): string
    {
        return $this->dateTo->toDateString();
    }

    /** Number of whole days in the selected range (inclusive). */
    public function days(): int
    {
        return $this->dateFrom->startOfDay()->diffInDays($this->dateTo->startOfDay()) + 1;
    }
}

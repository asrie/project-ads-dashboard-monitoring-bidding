<?php

declare(strict_types=1);

namespace App\Http\Requests;

class AlertIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return array_merge($this->filterRules(), $this->paginationRules(), [
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'category' => ['nullable', 'in:bidding,prebid,gam,web_vitals,revenue,network,slot,server'],
            'status' => ['nullable', 'in:open,acknowledged,resolved'],
        ]);
    }
}

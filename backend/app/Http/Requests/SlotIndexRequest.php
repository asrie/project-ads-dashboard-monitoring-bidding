<?php

declare(strict_types=1);

namespace App\Http\Requests;

class SlotIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return array_merge($this->filterRules(), $this->paginationRules(), [
            'slot_id' => ['nullable', 'uuid', 'exists:ad_slots,id'],
        ]);
    }
}

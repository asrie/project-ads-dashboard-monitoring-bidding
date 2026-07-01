<?php

declare(strict_types=1);

namespace App\Http\Requests;

class BidderIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return array_merge($this->filterRules(), $this->paginationRules(), [
            'bidder_id' => ['nullable', 'uuid', 'exists:bidders,id'],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ServerChecksRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return array_merge($this->filterRules(), $this->paginationRules(), [
            'status' => ['nullable', 'in:up,down'],
        ]);
    }
}

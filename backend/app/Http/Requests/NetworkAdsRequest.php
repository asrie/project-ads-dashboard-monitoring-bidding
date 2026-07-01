<?php

declare(strict_types=1);

namespace App\Http\Requests;

class NetworkAdsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return array_merge($this->filterRules(), $this->paginationRules(), [
            'type' => ['nullable', 'in:script,xhr,img,css,font'],
            'third_party' => ['nullable', 'boolean'],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

class PagePreviewRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'domain_id' => ['required', 'uuid', 'exists:domains,id'],
            'page_id' => ['nullable', 'uuid', 'exists:pages,id'],
            'device' => ['nullable', 'in:mobile,desktop'],
        ];
    }
}

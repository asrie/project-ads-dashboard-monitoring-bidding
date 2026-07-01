<?php

declare(strict_types=1);

namespace App\Http\Requests;

class SecurityScanRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'domain_id' => ['required', 'uuid', 'exists:domains,id'],
        ];
    }
}

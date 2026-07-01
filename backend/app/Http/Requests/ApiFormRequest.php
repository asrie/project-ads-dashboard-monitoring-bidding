<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Common dashboard filter rules reused across modules.
     *
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'domain_id' => ['nullable', 'uuid', 'exists:domains,id'],
            'page_id' => ['nullable', 'uuid', 'exists:pages,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'device' => ['nullable', 'in:desktop,mobile,tablet'],
        ];
    }

    /**
     * Common pagination + sorting rules.
     *
     * @return array<string, mixed>
     */
    protected function paginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'max:64'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}

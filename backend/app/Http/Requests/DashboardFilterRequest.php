<?php

declare(strict_types=1);

namespace App\Http\Requests;

class DashboardFilterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return $this->filterRules();
    }
}

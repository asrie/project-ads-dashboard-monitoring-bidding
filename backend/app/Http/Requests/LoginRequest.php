<?php

declare(strict_types=1);

namespace App\Http\Requests;

class LoginRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ];
    }
}

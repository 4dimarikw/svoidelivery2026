<?php

namespace App\Http\Requests;

class LoginRequest extends \Laravel\Fortify\Http\Requests\LoginRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'email' => ['required', 'string', 'max:255']];
    }
}

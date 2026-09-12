<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuthInput
{
    public static function identity(Request $request): string
    {
        $email = $request->input('email');

        return is_string($email) && strlen($email) <= 1024
            ? hash('sha256', Str::transliterate(Str::lower($email)))
            : 'invalid-identity';
    }

    public static function oldInput(Request $request): array
    {
        return array_filter($request->only(['email', 'name', 'remember', 'age_confirmed', 'terms_accepted']),
            fn ($value) => is_scalar($value) && strlen((string) $value) <= 1024);
    }
}

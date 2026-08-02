<?php

namespace Support\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class HtmlEntityDecoder implements CastsAttributes
{
    private const string ENCODING = 'UTF-8';

    private const int FLAGS = ENT_QUOTES | ENT_HTML5;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value)
            ? html_entity_decode($value, self::FLAGS, self::ENCODING)
            : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) ? trim($value) : null;
    }
}

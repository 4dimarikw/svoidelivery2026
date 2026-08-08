<?php

namespace Support\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Facades\Purifier;

/**
 * Тот же источник данных, что у HtmlEntityDecoder — 1С отдаёт строку
 * entity-энкодленной, поэтому декодируем ДО очистки: иначе Purifier видит
 * "&lt;b&gt;" как безобидный текст и не превращает его обратно в тег.
 *
 * Чистит и на get(), и на set() — значит уже сохранённые (до появления
 * этого каста) строки самоисцеляются на следующем чтении через Eloquent,
 * без миграции данных.
 *
 * Параметризован профилем (:product_description и т.п. — синтаксис Eloquent
 * "CastClass:arg"), а не жёстко привязан к одному — переиспользуем каст,
 * если такое же понадобится на другом поле с другим набором разрешённых
 * тегов (config/purifier.php).
 */
class PurifiedHtml implements CastsAttributes
{
    public function __construct(private readonly string $config = 'default') {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->clean($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->clean($value);
    }

    private function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(Purifier::clean($decoded, $this->config));
    }
}

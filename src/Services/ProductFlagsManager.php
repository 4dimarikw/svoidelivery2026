<?php

namespace Services;

use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

final class ProductFlagsManager
{
    protected array $labels = [
        'wu' => 'Товар без Untappd',
        'fil' => 'Первый в списке',
        'mss' => 'Наличие устанавливается вручную',
        'promo' => 'Акция',
    ];

    protected array $boolValues = ['wu', 'fil', 'mss', 'promo'];

    public function getMoonshineFields(): array
    {
        return collect(config('catalog_import.flags.defaults', []))->map(function ($value, $key) {
            return match (gettype($value)) {
                'boolean' => $this->getBoolField($key),
                'string' => $this->getTextOrBoolField($key),
                'integer' => $this->getNumberOrBoolField($key),
                default => $this->getTextField($key),
            };
        })->toArray();
    }

    private function getBoolField(string $key)
    {
        return Switcher::make($this->labels[$key] ?? $key, $key);
    }

    private function getTextField(string $key)
    {
        return Text::make($this->labels[$key] ?? $key, $key);
    }

    private function getNumberField(string $key)
    {
        return Number::make($this->labels[$key] ?? $key, $key);
    }

    private function getNumberOrBoolField(string $key)
    {
        return in_array($key, $this->boolValues)
            ? $this->getBoolField($key)
            : $this->getNumberField($key);
    }

    private function getTextOrBoolField(string $key)
    {
        return in_array($key, $this->boolValues)
            ? $this->getBoolField($key)
            : $this->getTextField($key);
    }
}

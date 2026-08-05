<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация фильтров каталога на главной странице (route `home`).
 * Все поля необязательны — пустой запрос показывает весь опубликованный каталог.
 */
class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],

            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],

            'manufacturers' => ['nullable', 'array'],
            'manufacturers.*' => ['integer', 'exists:manufacturers,id'],

            'volumes' => ['nullable', 'array'],
            'volumes.*' => ['integer', 'exists:volumes,id'],

            'containers' => ['nullable', 'array'],
            'containers.*' => ['integer', 'exists:containers,id'],

            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'gte:price_min'],

            'in_stock' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<int>
     */
    public function categories(): array
    {
        return $this->validated('categories', []);
    }

    /**
     * @return list<int>
     */
    public function manufacturers(): array
    {
        return $this->validated('manufacturers', []);
    }

    /**
     * @return list<int>
     */
    public function volumes(): array
    {
        return $this->validated('volumes', []);
    }

    /**
     * @return list<int>
     */
    public function containers(): array
    {
        return $this->validated('containers', []);
    }

    public function priceMin(): ?string
    {
        return $this->validated('price_min');
    }

    public function priceMax(): ?string
    {
        return $this->validated('price_max');
    }

    public function searchTerm(): ?string
    {
        return $this->validated('q');
    }

    public function inStock(): bool
    {
        return $this->boolean('in_stock');
    }
}

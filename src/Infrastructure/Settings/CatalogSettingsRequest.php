<?php

namespace App\Settings;

use Domain\Product\Enums\ProductStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use MoonShine\Laravel\MoonShineAuth;

class CatalogSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return MoonShineAuth::getGuard()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'product_variation_metadata' => ['sometimes', 'required'],
            'product_variation_status' => ['required', Rule::enum(ProductStatus::class)],
            'details_categories_slug' => ['sometimes', 'required', 'array'],
            'product_details' => ['sometimes', 'required', 'array'],
            'liter_products_price' => ['sometimes', 'required', 'array'],
            'new_days' => ['required', 'integer', 'min:1'],
            'cache' => ['sometimes', 'required', 'boolean'],
        ];
    }
}

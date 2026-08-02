<?php

namespace App\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use MoonShine\Laravel\MoonShineAuth;

class GeneralSettingsRequest extends FormRequest
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
            'extra_charge' => 'sometimes|required|numeric|min:0',
            'test_user_email' => 'sometimes|required|email|exists:users,email',
            'last_catalog_update' => 'sometimes|nullable|string',
            'untappd_update_limit' => 'numeric',
            'vk_last_update' => 'nullable|string',
        ];
    }
}

<?php

namespace Infrastructure\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Infrastructure\Rules\CronExpressionRule;
use MoonShine\Laravel\MoonShineAuth;

class VKSyncSettingsRequest extends FormRequest
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
            'domain' => 'required|string',
            'count' => 'required|numeric',
            'cron' => ['required', 'string', new CronExpressionRule],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Domain\Catalog\Filters\FilterManager;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Проверяет необязательные фильтры каталога. FilterManager предоставляет имена полей и правила проверки. Laravel
 * выполняет валидацию при разрешении типизированного параметра контроллера, до выполнения его метода.
 */
class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return app(FilterManager::class)->rules();
    }
}

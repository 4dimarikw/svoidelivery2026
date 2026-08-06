<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Domain\Catalog\Filters\FilterManager;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация фильтров каталога на главной странице (route `home`).
 * Все поля необязательны — пустой запрос показывает весь опубликованный каталог.
 *
 * Чистый валидатор — ни значения фильтров, ни сами правила здесь больше не
 * прописаны руками: и то, и другое приходит из Domain\Catalog\Filters\FilterManager
 * (каждый фильтр — единственный источник правды про своё поле: имя, правило
 * валидации, применение к запросу, собственный Blade-виджет — всё в одном
 * классе). Типизированный параметр в CatalogController::index() держим ради
 * побочного эффекта: Laravel валидирует запрос при резолве параметра метода,
 * ДО выполнения тела контроллера (422/редирект на невалидный ID и т.п.).
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

<?php

namespace Domain\Content\Types;

use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Switcher;

/**
 * Контент страницы «О нас» (route `about`): приветствие, график приёма
 * заказов, правила оформления (список), акции (список), обязательное
 * уведомление об осмотре вложения. Один блок на секцию — фикстура
 * (database/seeders/data/site-content.php) хранит `blocks` плоской мапой
 * ключ-секции → один блок, второй блок под ту же секцию сидер не заведёт.
 */
final class AboutInfoBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'about_info';
    }

    public function label(): string
    {
        return 'Информация о сервисе';
    }

    public function blockFields(): array
    {
        return [
            $this->text('Заголовок страницы', 'heading'),
            $this->textarea('Приветственный текст', 'lead'),
            $this->textarea('График приёма заказов', 'schedule'),
            $this->text('Заголовок блока правил', 'ordering_heading'),
            Json::make('Правила оформления заказа', 'ordering_rules')->fields([
                $this->text('Текст пункта', 'text')->required(),
                Switcher::make('Активен', 'is_active')->default(true),
            ])->creatable()->removable()->reorderable()->stopFilteringEmpty(),
            $this->text('Заголовок блока акций', 'promo_lead'),
            Json::make('Акции', 'promotions')->fields([
                $this->text('Текст пункта', 'text')->required(),
                Switcher::make('Активен', 'is_active')->default(true),
            ])->creatable()->removable()->reorderable()->stopFilteringEmpty(),
            $this->textarea('Обязательное уведомление', 'notice'),
        ];
    }

    public function blockRules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:255'],
            'lead' => ['nullable', 'string', 'max:2000'],
            'schedule' => ['nullable', 'string', 'max:2000'],
            'ordering_heading' => ['nullable', 'string', 'max:255'],
            'promo_lead' => ['nullable', 'string', 'max:255'],
            'ordering_rules' => ['nullable', 'array'],
            'ordering_rules.*.text' => ['required', 'string', 'max:500'],
            'ordering_rules.*.is_active' => ['required', 'boolean'],
            'promotions' => ['nullable', 'array'],
            'promotions.*.text' => ['required', 'string', 'max:500'],
            'promotions.*.is_active' => ['required', 'boolean'],
            'notice' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

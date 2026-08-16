<?php

namespace Domain\Content\Types;

use Domain\Content\Data\ContentItemGroup;

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
            $this->text('Заголовок блока акций', 'promo_lead'),
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
            'notice' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function itemGroups(): array
    {
        return [
            'ordering_rules' => new ContentItemGroup('ordering_rules', 'Правила оформления заказа', [
                $this->text('Текст пункта', 'text'),
            ], ['text' => ['required', 'string', 'max:500']]),
            'promotions' => new ContentItemGroup('promotions', 'Акции', [
                $this->text('Текст пункта', 'text'),
            ], ['text' => ['required', 'string', 'max:500']]),
        ];
    }
}

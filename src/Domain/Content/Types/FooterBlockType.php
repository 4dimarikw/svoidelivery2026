<?php

namespace Domain\Content\Types;

/**
 * Текст подвала сайта (Domain\Content\Actions\Content\LoadSiteFooter,
 * resources/views/components/layouts/footer.blade.php) — копирайт и
 * возрастное предупреждение. Единственный блок глобальной секции
 * `footer` (SiteSection.route_name = null), не идёт через диспетчер
 * <x-content.sections> — нет записи в config('content.views').
 */
final class FooterBlockType extends AbstractContentBlockType
{
    public function key(): string
    {
        return 'footer_info';
    }

    public function label(): string
    {
        return 'Текст подвала сайта';
    }

    public function blockFields(): array
    {
        return [
            $this->text('Текст копирайта', 'rights'),
            $this->text('Возрастное предупреждение', 'age_notice'),
        ];
    }

    public function blockRules(): array
    {
        return [
            'rights' => ['nullable', 'string', 'max:255'],
            'age_notice' => ['nullable', 'string', 'max:255'],
        ];
    }
}

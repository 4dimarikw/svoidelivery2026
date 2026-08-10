<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use Illuminate\Http\Request;
use Infrastructure\Settings\SiteSettings;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use Throwable;

#[Icon('cog-6-tooth')]
#[Group('Контент', 'document-text')]
#[Order(60)]
final class SiteSettingsPage extends Page
{
    public function getTitle(): string
    {
        return 'Настройки сайта';
    }

    /** @return list<ComponentContract>
     * @throws Throwable
     */
    protected function components(): iterable
    {
        $settings = app(SiteSettings::class);

        return [
            Box::make('Общие настройки', [
                FormBuilder::make()
                    ->fields([
                        Text::make('Название сайта', 'site_name')->required(),
                        Text::make('Слоган', 'tagline'),
                        Textarea::make('Описание', 'description'),
                        Date::make('Начало события', 'event_starts_at')->withTime(),
                        Date::make('Окончание события', 'event_ends_at')->withTime(),
                        Text::make('Место', 'location_name'),
                        Text::make('Организатор', 'organizer_name'),
                        Text::make('Telegram (имя без ссылки)', 'telegram_handle'),
                        Date::make('Дедлайн маркета', 'market_deadline')->withTime(),
                        Number::make('Ожидаемое число гостей', 'expected_visitors')->min(0),
                        Text::make('Благотворительный проект', 'charity_name'),
                        Text::make('Copyright', 'copyright'),
                        Switcher::make('Автовход через Telegram Mini App', 'telegram_autologin')
                            ->hint('Гость, открывший сайт внутри Telegram, входит автоматически, без нажатия кнопки.'),
                    ])
                    ->fill($settings->toArray())
                    ->asyncMethod('save')
                    ->errorsAbove()
                    ->submit('Сохранить'),
            ]),
        ];
    }

    #[AsyncMethod]
    public function save(Request $request, SiteSettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'event_starts_at' => ['nullable', 'date'],
            'event_ends_at' => ['nullable', 'date', 'after_or_equal:event_starts_at'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'organizer_name' => ['nullable', 'string', 'max:255'],
            'telegram_handle' => ['nullable', 'string', 'max:100', 'regex:/^@?[A-Za-z0-9_]+$/'],
            'market_deadline' => ['nullable', 'date'],
            'expected_visitors' => ['nullable', 'integer', 'min:0'],
            'charity_name' => ['nullable', 'string', 'max:255'],
            'copyright' => ['nullable', 'string', 'max:255'],
            'telegram_autologin' => ['nullable', 'boolean'],
        ]);

        // Switcher не шлёт ключ вовсе, когда выключен — его нет в
        // $validated, и общий цикл ниже его не тронет. Обрабатываем
        // отдельно через $request->boolean(), иначе настройку нельзя
        // было бы выключить обратно.
        unset($validated['telegram_autologin']);

        foreach ($validated as $property => $value) {
            $settings->{$property} = $value ?? (in_array($property, ['event_starts_at', 'event_ends_at', 'market_deadline', 'expected_visitors'], true) ? null : '');
        }

        $settings->telegram_autologin = $request->boolean('telegram_autologin');

        $settings->save();

        return JsonResponse::make()->toast('Настройки сайта сохранены.');
    }
}

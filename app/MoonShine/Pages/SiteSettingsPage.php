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
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
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
                        Switcher::make('Автовход через Telegram Mini App', 'telegram_autologin')
                            ->hint('Гость, открывший сайт внутри Telegram, входит автоматически, без нажатия кнопки.'),
                        Text::make('Email тестового пользователя', 'test_user_email')
                            ->hint('Используется кнопкой «Тестовый заказ» в списке заказов — должен совпадать с email существующего пользователя.'),
                        Text::make('Email для уведомлений', 'notify_email'),
                        Number::make('Лимит обновлений Untappd', 'untappd_update_limit')->min(0),
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
            'test_user_email' => ['nullable', 'email', 'exists:users,email'],
            'notify_email' => ['nullable', 'email', 'max:255'],
            'untappd_update_limit' => ['required', 'integer', 'min:0'],
            'telegram_autologin' => ['nullable', 'boolean'],
        ]);

        $settings->site_name = $validated['site_name'];
        $settings->test_user_email = $validated['test_user_email'] ?? null;
        $settings->notify_email = $validated['notify_email'] ?? null;
        $settings->untappd_update_limit = (int) $validated['untappd_update_limit'];

        // Switcher не шлёт ключ вовсе, когда выключен — его нет в
        // $validated, и присваивание выше его не тронуло бы. Обрабатываем
        // отдельно через $request->boolean(), иначе настройку нельзя
        // было бы выключить обратно.
        $settings->telegram_autologin = $request->boolean('telegram_autologin');

        $settings->save();

        return JsonResponse::make()->toast('Настройки сайта сохранены.');
    }
}

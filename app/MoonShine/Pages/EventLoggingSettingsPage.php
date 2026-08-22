<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Events\Enums\LoggableEventType;
use Illuminate\Http\Request;
use Infrastructure\Settings\EventLoggingSettings;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Switcher;
use Throwable;

/**
 * Один переключатель на case App\Events\Enums\LoggableEventType — новый
 * LoggableEvent-класс с новым case появляется здесь сам, без правки этой
 * страницы. ON = событие пишется в event_logs (позитивная формулировка,
 * тот же тон, что у Switcher'ов SiteSettingsPage/VkSyncSettingsPage).
 */
#[Icon('adjustments-horizontal')]
#[Group('Система', 'users')]
#[Order(15)]
final class EventLoggingSettingsPage extends Page
{
    public function getTitle(): string
    {
        return 'Логирование событий';
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function components(): iterable
    {
        $settings = app(EventLoggingSettings::class);

        $fields = [];
        $fill = [];

        foreach (LoggableEventType::cases() as $type) {
            $fieldName = self::fieldName($type);

            $fields[] = Switcher::make($type->label(), $fieldName)->hint($type->value);
            $fill[$fieldName] = ! $settings->isDisabled($type->value);
        }

        return [
            Box::make('Типы событий', [
                FormBuilder::make()
                    ->fields($fields)
                    ->fill($fill)
                    ->asyncMethod('save')
                    ->errorsAbove()
                    ->submit('Сохранить'),
            ]),
        ];
    }

    #[AsyncMethod]
    public function save(Request $request, EventLoggingSettings $settings): JsonResponse
    {
        $disabled = [];

        foreach (LoggableEventType::cases() as $type) {
            // Switcher не шлёт ключ вовсе, когда выключен — см. тот же
            // комментарий в SiteSettingsPage::save()/VkSyncSettingsPage::save().
            if (! $request->boolean(self::fieldName($type))) {
                $disabled[] = $type->value;
            }
        }

        $settings->disabled_event_types = $disabled;
        $settings->save();

        return JsonResponse::make()->toast('Настройки логирования сохранены.');
    }

    /**
     * HTML-имя поля — не сырое $type->value: в нём есть точки, а PHP молча
     * превращает точки/пробелы в имени POST-поля в подчёркивания
     * ($_POST-парсинг), так что $request->boolean('untappd_beer.synced')
     * никогда не увидел бы настоящее значение.
     */
    private static function fieldName(LoggableEventType $type): string
    {
        return 'log_'.str_replace('.', '_', $type->value);
    }
}

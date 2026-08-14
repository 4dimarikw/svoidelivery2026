<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use Domain\Vk\Enums\VkPostStatus;
use Illuminate\Http\Request;
use Infrastructure\Rules\CronExpressionRule;
use Infrastructure\Settings\VKSyncSettings;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Page;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Checkbox;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Throwable;

#[Icon('share')]
#[Group('Контент', 'document-text')]
#[Order(66)]
final class VkSyncSettingsPage extends Page
{
    public function getTitle(): string
    {
        return 'Настройки синхронизации VK';
    }

    /** @return list<ComponentContract>
     * @throws Throwable
     */
    protected function components(): iterable
    {
        $settings = app(VKSyncSettings::class);

        return [
            Box::make('Синхронизация постов VK', [
                FormBuilder::make()
                    ->fields([
                        Switcher::make('Синхронизация включена', 'active')
                            ->hint('Пока выключено, vk:sync-posts ничего не делает без --force.'),
                        Text::make('Домен группы', 'domain')->required()
                            ->hint('Короткое имя группы VK, например public123456 или club123456.'),
                        Number::make('Сколько постов забирать за раз', 'count')->min(1)->required(),
                        Text::make('Расписание (cron)', 'cron')->required()
                            ->hint('Формат cron, например "0 * * * *" — раз в час.'),
                        Select::make('Статус новых постов', 'post_status')
                            ->options(collect(VkPostStatus::cases())->mapWithKeys(
                                fn (VkPostStatus $s) => [$s->value => $s->toString()],
                            )->all()),
                        Checkbox::make('Обычные посты', 'post_types_post')
                            ->hint('Учитывать посты самой группы (тип post).'),
                        Checkbox::make('Репосты', 'post_types_copy')
                            ->hint('Учитывать репосты сторонних записей на стену группы (тип copy).'),
                        Preview::make('Последнее обновление', 'last_update_display'),
                    ])
                    ->fill([
                        ...$settings->toArray(),
                        'post_types_post' => in_array('post', $settings->post_types ?? [], true),
                        'post_types_copy' => in_array('copy', $settings->post_types ?? [], true),
                        'last_update_display' => is_numeric($settings->last_update)
                            ? date('d.m.Y H:i', (int) $settings->last_update)
                            : 'ещё не запускалось',
                    ])
                    ->asyncMethod('save')
                    ->errorsAbove()
                    ->submit('Сохранить'),
            ]),
        ];
    }

    #[AsyncMethod]
    public function save(Request $request, VKSyncSettings $settings): JsonResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'count' => ['required', 'integer', 'min:1'],
            'cron' => ['required', 'string', new CronExpressionRule],
            'post_status' => ['required', 'string', function ($attribute, $value, $fail): void {
                if (! VkPostStatus::exists($value)) {
                    $fail('Неизвестный статус.');
                }
            }],
        ]);

        $postTypes = array_values(array_filter([
            $request->boolean('post_types_post') ? 'post' : null,
            $request->boolean('post_types_copy') ? 'copy' : null,
        ]));

        $settings->domain = $validated['domain'];
        $settings->count = (int) $validated['count'];
        $settings->cron = $validated['cron'];
        $settings->post_status = $validated['post_status'];
        $settings->post_types = $postTypes === [] ? ['post'] : $postTypes;

        // Switcher не шлёт ключ вовсе, когда выключен — см. тот же комментарий
        // в SiteSettingsPage::save().
        $settings->active = $request->boolean('active');

        $settings->save();

        return JsonResponse::make()->toast('Настройки синхронизации VK сохранены.');
    }
}

<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\VkPost\Pages;

use App\MoonShine\Resources\VkPost\VkPostResource;
use Domain\Vk\Actions\SendVkPostToChatAction;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Collapse;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Img;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Url;
use Throwable;

/**
 * @extends FormPage<VkPostResource, VkPost>
 */
final class VkPostFormPage extends FormPage
{
    /**
     * @return list<ComponentContract|FieldContract>
     *
     * @throws Throwable
     */
    protected function fields(): iterable
    {
        return [
            Box::make('Пост во ВКонтакте', [
                ID::make(),
                Url::make('Ссылка на VK', formatted: fn (?VkPost $item) => $item?->vkUrl ?? '')
                    ->title(fn () => 'Ссылка на пост VK')
                    ->previewMode(),
                Date::make('Опубликован в VK', 'posted_at')->withTime()->locked(),
                Enum::make('Статус', 'status')->attach(VkPostStatus::class),
            ]),
            Box::make('Рассылка', [
                Collapse::make('Исходный текст (VK)', [
                    Textarea::make('', 'text')->readonly()->customAttributes([
                        'rows' => 30,
                    ]),
                ]),
                Textarea::make('Текст для рассылки', 'message_text')
                    ->hint('Пусто — пост не рассылается. Перенесите нужное из исходного текста VK.')
                    ->customAttributes([
                        'rows' => 20,
                    ]),
                Collapse::make('Описание синтаксиса для форматирования текста сообщения Telegram', [
                    FlexibleRender::make(
                        view('pages.telegram-standard-html-tags')
                    ),
                ]),
                Collapse::make('Ссылки на картинки VK поста', [
                    Json::make('', 'images')->onlyValue('URL')->removable(),
                ]),

            ]),
        ];
    }

    /**
     * Отдельный блок со всеми картинками поста (то, что реально уйдёт
     * в рассылку — rejected_images сюда не входят, они остаются на
     * VkPostDetailPage как сырой JSON). Превью кликабельны — открывают
     * стандартный лайтбокс MoonShine (глобальный слушатель img-popup,
     * см. moonshine::components.layout.body).
     */
    private function imagesBox(): ComponentContract
    {
        $item = $this->getItem();
        $images = $item instanceof VkPost ? $item->images : [];

        if ($images === [] || $images === null) {
            return Box::make('Картинки поста', [
                Heading::make('Картинок нет')->h(5),
            ]);
        }

        return Box::make('Картинки поста ('.count($images).')', [
            Flex::make(
                array_map(
                    static fn (string $url) => Img::make($url)
                        ->lazyLoading()
                        ->customAttributes([
                            'class' => 'zoom-in',
                            'style' => 'width:100%;max-width:220px;height:140px;object-fit:cover;border-radius:4px;cursor:pointer',
                            '@click.stop' => '$dispatch(\'img-popup\', { open: true, src: '.json_encode($url).' })',
                        ]),
                    $images,
                ),
                justifyAlign: 'start',
            )->wrap(),
        ]);
    }

    /**
     * Данные о рассылке (только просмотр) — когда пост разослан
     * и с каким результатом. Пишутся только BroadcastVkPostJob,
     * на форме недоступны для редактирования.
     */
    private function broadcastInfoBox(): ComponentContract
    {
        $item = $this->getItem();

        if (! $item instanceof VkPost || $item->broadcast_at === null) {
            return Box::make('Данные рассылки', [
                Heading::make('Пост ещё не рассылался')->h(5),
            ]);
        }

        return Box::make('Данные рассылки', [
            Date::make('Разослан', 'broadcast_at')->withTime()->fillData($item)->previewMode(),
            Text::make('Статистика', 'broadcast_stats', formatted: fn (?VkPost $item) => sprintf(
                'Отправлено: %d, ошибок: %d, пропущено: %d',
                (int) data_get($item?->broadcast_stats, 'sent', 0),
                (int) data_get($item?->broadcast_stats, 'failed', 0),
                (int) data_get($item?->broadcast_stats, 'skipped', 0),
            ))->fillData($item)->previewMode(),
        ]);
    }

    /**
     * @return list<ComponentContract>
     *
     * @throws Throwable
     */
    protected function mainLayer(): array
    {
        return [
            Grid::make([
                Column::make(parent::mainLayer(), colSpan: 8, adaptiveColSpan: 12),
                Column::make([$this->broadcastInfoBox(), $this->imagesBox()], colSpan: 4, adaptiveColSpan: 12),
            ]),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'message_text' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*.value' => ['nullable', 'string', 'max:2048'],
            'status' => ['required', 'string'],
        ];
    }

    protected function buttons(): ListOf
    {
        return parent::buttons()->add(...[
            ActionButton::make('Отправить себе')->method('sendToSelf'),
            ActionButton::make('Разослать всем')->warning()->method('broadcastAll'),
        ]);
    }

    #[AsyncMethod]
    public function sendToSelf(CrudRequestContract $request, SendVkPostToChatAction $send): JsonResponse
    {
        $post = $request->getResource()->getItem();

        if (! $post instanceof VkPost) {
            return JsonResponse::make()->toast('Пост не найден.');
        }

        return VkPostIndexPage::sendTestMessage($post, $send);
    }

    #[AsyncMethod]
    public function broadcastAll(CrudRequestContract $request): JsonResponse
    {
        $post = $request->getResource()->getItem();

        if (! $post instanceof VkPost) {
            return JsonResponse::make()->toast('Пост не найден.');
        }

        return VkPostIndexPage::dispatchBroadcast($post);
    }
}

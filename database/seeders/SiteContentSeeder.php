<?php

namespace Database\Seeders;

use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\ContentBlockItem;
use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Domain\Content\Models\SiteSection;
use Domain\Content\Support\SafeContentUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Throwable;

final class SiteContentSeeder extends Seeder
{
    /** @var array<string, mixed> */
    private array $fixture;

    /** @param array<string, mixed>|null $fixture */
    public function __construct(?array $fixture = null)
    {
        $this->fixture = $fixture ?? require __DIR__.'/data/site-content.php';
    }

    /**
     * @throws Throwable
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            if ($this->cmsContainsData()) {
                $this->command?->info('CMS уже содержит данные. Установочный SiteContentSeeder пропущен.');

                return;
            }

            $this->validateFixture();
            $sections = $this->seedSections();
            $this->seedMenus($sections);
            $this->seedBlocks($sections);
        }, 3);
    }

    private function cmsContainsData(): bool
    {
        /** @var Model $model */
        return array_any([SiteSection::class, SiteMenu::class, SiteMenuItem::class, ContentBlock::class, ContentBlockItem::class], fn ($model) => $model::query()->exists());

    }

    /** @return array<string, SiteSection> */
    private function seedSections(): array
    {
        $sections = [];

        foreach ($this->fixture['sections'] as $sectionData) {
            $section = SiteSection::query()->create([
                'key' => $sectionData['key'],
                'title' => $sectionData['title'],
                'route_name' => $sectionData['route_name'],
                // Нормализация как в SiteSectionFormPage::prepareForValidation() —
                // якорь хранится без ведущего "#".
                'fragment' => ltrim(trim((string) ($sectionData['fragment'] ?? '')), '#'),
                'sort_order' => $sectionData['sort_order'],
                'is_active' => true,
            ]);

            $sections[$sectionData['key']] = $section;
        }

        return $sections;
    }

    /** @param array<string, SiteSection> $sections */
    private function seedMenus(array $sections): void
    {
        foreach ($this->fixture['menus'] as $menuKey => $menuData) {
            $menu = SiteMenu::query()->create([
                'key' => $menuKey,
                'title' => $menuData['title'],
                'is_active' => $menuData['is_active'],
            ]);
            $items = [];

            foreach ($menuData['items'] as $itemData) {
                $sectionKey = $itemData['section_key'];
                $parentKey = $itemData['parent_key'];
                $item = new SiteMenuItem([
                    'site_menu_id' => $menu->getKey(),
                    'key' => $itemData['key'],
                    'site_section_id' => $sectionKey === null ? null : $sections[$sectionKey]->getKey(),
                    'external_url' => $itemData['external_url'],
                    'label' => $itemData['label'],
                    'open_in_new_tab' => $itemData['open_in_new_tab'],
                    'is_active' => $itemData['is_active'],
                ]);

                if ($parentKey !== null) {
                    $item->appendToNode($items[$parentKey]);
                }

                $item->save();
                $items[$itemData['key']] = $item;
            }

            if (SiteMenuItem::scoped(['site_menu_id' => $menu->getKey()])->isBroken()) {
                throw new LogicException("Дерево пунктов меню [$menuKey] создано некорректно.");
            }
        }
    }

    /**
     * @param  array<string, SiteSection>  $sections
     *
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    private function seedBlocks(array $sections): void
    {
        foreach ($this->fixture['blocks'] as $sectionKey => $blockData) {
            $section = $sections[$sectionKey];
            $block = ContentBlock::query()->create([
                'site_section_id' => $section->getKey(),
                'key' => $sectionKey,
                'type' => $blockData['type'],
                'title' => $blockData['title'],
                'content' => $blockData['content'],
                'sort_order' => 10,
                'is_active' => true,
            ]);

            $this->seedItems($block, $blockData['items'] ?? []);
            $this->seedMedia($block, $blockData['media'] ?? []);
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function seedItems(ContentBlock $block, array $items): void
    {
        $orders = [];

        foreach ($items as $itemData) {
            $group = $itemData['group_key'];
            $orders[$group] = ($orders[$group] ?? 0) + 10;
            $item = ContentBlockItem::query()->make([
                'content_block_id' => $block->getKey(),
                'group_key' => $group,
                'key' => $itemData['key'],
                'title' => $itemData['title'],
                'content' => $itemData['content'],
                'sort_order' => $orders[$group],
                'is_active' => true,
            ]);
            $item->setRelation('block', $block);
            $item->save();
        }
    }

    /**
     * @param  array<string, string>  $media
     *
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    private function seedMedia(ContentBlock $block, array $media): void
    {
        foreach ($media as $collection => $fileName) {
            $path = __DIR__.'/assets/'.$fileName;
            $block->addMedia($path)
                ->preservingOriginal()
                ->toMediaCollection($collection, 'public');
        }
    }

    private function validateFixture(): void
    {
        $sections = [];
        $routeFragments = [];

        foreach ($this->fixture['sections'] ?? [] as $section) {
            $key = trim((string) ($section['key'] ?? ''));
            if ($key === '' || isset($sections[$key])) {
                throw new LogicException("Ключ раздела [$key] пуст или повторяется в fixture.");
            }

            // route_name === null — глобальная секция (не привязана к
            // странице, см. миграцию make_site_sections_route_name_nullable),
            // Route::has()-проверка и дедуп по паре route_name+fragment для
            // неё не имеют смысла: уникальность и так гарантирует key выше.
            // Явно null, а не пустая строка — опечатка/пропуск в fixture
            // по-прежнему обязана указывать на реальный маршрут.
            $routeNameRaw = array_key_exists('route_name', $section) ? $section['route_name'] : '';

            if ($routeNameRaw !== null) {
                $routeName = trim((string) $routeNameRaw);
                if ($routeName === '' || ! Route::has($routeName)) {
                    throw new LogicException("Раздел [$key] ссылается на неизвестный маршрут [$routeName].");
                }

                // Зеркалим уникальный индекс site_sections_route_name_fragment_unique —
                // иначе fixture падает не читаемым LogicException, а сырым SQL 1062.
                $fragment = ltrim(trim((string) ($section['fragment'] ?? '')), '#');
                $routeFragmentKey = $routeName.'#'.$fragment;
                if (isset($routeFragments[$routeFragmentKey])) {
                    throw new LogicException("Раздел [$key] дублирует пару маршрут+якорь [$routeFragmentKey].");
                }

                $routeFragments[$routeFragmentKey] = true;
            }

            $sections[$key] = true;
        }

        foreach ($this->fixture['menus'] ?? [] as $menuKey => $menu) {
            $knownItems = [];

            foreach ($menu['items'] ?? [] as $item) {
                $key = trim((string) ($item['key'] ?? ''));
                if ($key === '' || mb_strlen($key) > 100 || preg_match('/^[\pL\pM\pN_-]+$/u', $key) !== 1) {
                    throw new LogicException("Пункт меню [$menuKey] содержит недопустимый ключ [$key].");
                }

                if (isset($knownItems[$key])) {
                    throw new LogicException("Ключ пункта меню [$menuKey.$key] повторяется в fixture.");
                }

                $parentKey = $item['parent_key'] ?? null;
                if ($parentKey !== null && ! isset($knownItems[$parentKey])) {
                    throw new LogicException("Родитель [$parentKey] пункта [$menuKey.$key] должен быть объявлен раньше ребёнка.");
                }

                $sectionKey = $item['section_key'] ?? null;
                $externalUrl = trim((string) ($item['external_url'] ?? ''));
                if (($sectionKey !== null) === ($externalUrl !== '')) {
                    throw new LogicException("Пункт меню [$menuKey.$key] должен иметь ровно одну цель.");
                }

                if ($sectionKey !== null && ! isset($sections[$sectionKey])) {
                    throw new LogicException("Пункт меню [$menuKey.$key] ссылается на неизвестный раздел [$sectionKey].");
                }

                if ($externalUrl !== '' && SafeContentUrl::resolve($externalUrl) === null) {
                    throw new LogicException("Пункт меню [$menuKey.$key] содержит небезопасный URL.");
                }

                $knownItems[$key] = true;
            }
        }

        foreach ($this->fixture['blocks'] ?? [] as $sectionKey => $block) {
            if (! isset($sections[$sectionKey])) {
                throw new LogicException("Блок [$sectionKey] ссылается на неизвестный раздел.");
            }

            foreach ($block['media'] ?? [] as $fileName) {
                $path = __DIR__.'/assets/'.$fileName;
                if (! is_file($path)) {
                    throw new LogicException("Не найден seed-asset [$path].");
                }
            }
        }
    }
}

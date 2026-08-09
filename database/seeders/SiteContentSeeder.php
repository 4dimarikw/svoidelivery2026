<?php

namespace Database\Seeders;

use Domain\Content\Models\ContentBlock;
use Domain\Content\Models\ContentBlockItem;
use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Domain\Content\Models\SiteSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Infrastructure\Settings\SiteSettings;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

final class SiteContentSeeder extends Seeder
{
    /** @var array<string, mixed> */
    private array $fixture;

    public function __construct()
    {
        $this->fixture = require __DIR__.'/data/site-content.php';
    }

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedSettings();
            $sections = $this->seedSections();
            $this->seedMenu($sections);
            $this->seedBlocks($sections);
        });
    }

    private function seedSettings(): void
    {
        $settings = app(SiteSettings::class);
        $changed = false;

        foreach ($this->fixture['settings'] as $property => $value) {
            if ($settings->{$property} === null || $settings->{$property} === '') {
                $settings->{$property} = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $settings->save();
        }
    }

    /** @return array<string, SiteSection> */
    private function seedSections(): array
    {
        $sections = [];

        foreach ($this->fixture['sections'] as $sectionData) {
            $section = SiteSection::query()->firstOrCreate(
                ['key' => $sectionData['key']],
                [
                    'title' => $sectionData['title'],
                    'route_name' => 'home',
                    'fragment' => $sectionData['fragment'],
                    'sort_order' => $sectionData['sort_order'],
                    'is_active' => true,
                ],
            );

            $sections[$sectionData['key']] = $section;
        }

        return $sections;
    }

    /** @param array<string, SiteSection> $sections */
    private function seedMenu(array $sections): void
    {
        $menu = SiteMenu::query()->firstOrCreate(
            ['key' => 'main'],
            ['title' => 'Главное меню', 'is_active' => true],
        );

        foreach (array_slice($this->fixture['sections'], 1) as $sectionData) {
            $section = $sections[$sectionData['key']];
            SiteMenuItem::query()->firstOrCreate(
                ['site_menu_id' => $menu->getKey(), 'site_section_id' => $section->getKey()],
                [
                    'parent_id' => null,
                    'external_url' => null,
                    'label' => $sectionData['menu_label'] ?? $sectionData['title'],
                    'open_in_new_tab' => false,
                    'is_active' => true,
                ],
            );
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
            $block = ContentBlock::query()
                ->where('site_section_id', $section->getKey())
                ->where('key', $sectionKey)
                ->first();

            if ($block !== null && $block->type !== $blockData['type']) {
                throw new LogicException("Блок [{$sectionKey}] имеет тип [{$block->type}], ожидался [{$blockData['type']}].");
            }

            $block ??= ContentBlock::query()->create([
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
            $existing = ContentBlockItem::query()
                ->where('content_block_id', $block->getKey())
                ->where('key', $itemData['key'])
                ->first();

            if ($existing !== null && $existing->group_key !== $group) {
                throw new LogicException("Элемент [{$block->key}.{$itemData['key']}] находится в группе [{$existing->group_key}], ожидалась [{$group}].");
            }

            if ($existing === null) {
                $existing = ContentBlockItem::query()->make([
                    'content_block_id' => $block->getKey(),
                    'group_key' => $group,
                    'key' => $itemData['key'],
                    'title' => $itemData['title'],
                    'content' => $itemData['content'],
                    'sort_order' => $orders[$group],
                    'is_active' => true,
                ]);
                $existing->setRelation('block', $block);
                $existing->save();
            }
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
            if ($block->getMedia($collection)->isNotEmpty()) {
                continue;
            }

            $path = __DIR__.'/assets/'.$fileName;
            if (! is_file($path)) {
                throw new LogicException("Не найден seed-asset [{$path}].");
            }

            $block->addMedia($path)
                ->preservingOriginal()
                ->toMediaCollection($collection, 'public');
        }
    }
}

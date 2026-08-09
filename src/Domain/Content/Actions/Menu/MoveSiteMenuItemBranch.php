<?php

namespace Domain\Content\Actions\Menu;

use Domain\Content\Models\SiteMenu;
use Domain\Content\Models\SiteMenuItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MoveSiteMenuItemBranch
{
    public function handle(
        SiteMenuItem  $item,
        SiteMenu      $targetMenu,
        ?SiteMenuItem $targetParent = null,
    ): SiteMenuItem
    {
        if ((int)$item->site_menu_id === (int)$targetMenu->getKey()) {
            throw ValidationException::withMessages([
                'target_menu_id' => 'Выберите другое меню.',
            ]);
        }

        if ($targetParent !== null && (int)$targetParent->site_menu_id !== (int)$targetMenu->getKey()) {
            throw ValidationException::withMessages([
                'target_parent_id' => 'Родитель должен принадлежать выбранному меню.',
            ]);
        }

        return DB::transaction(function () use ($item, $targetMenu, $targetParent): SiteMenuItem {
            $sourceMenuId = (int)$item->site_menu_id;
            $targetMenuId = (int)$targetMenu->getKey();
            $menuIds = [$sourceMenuId, $targetMenuId];
            sort($menuIds);

            $lockedMenus = SiteMenu::query()
                ->whereKey($menuIds)
                ->orderBy((new SiteMenu)->getKeyName())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn(SiteMenu $menu): int => (int)$menu->getKey());

            if ($lockedMenus->count() !== count(array_unique($menuIds))) {
                throw (new ModelNotFoundException)->setModel(SiteMenu::class, $menuIds);
            }

            $lockedItems = SiteMenuItem::query()
                ->whereIn('site_menu_id', $menuIds)
                ->orderBy('site_menu_id')
                ->orderBy('_lft')
                ->lockForUpdate()
                ->get();

            /** @var SiteMenuItem|null $source */
            $source = $lockedItems->firstWhere($item->getKeyName(), $item->getKey());
            if ($source === null || (int)$source->site_menu_id !== $sourceMenuId) {
                throw (new ModelNotFoundException)->setModel(SiteMenuItem::class, [$item->getKey()]);
            }

            /** @var SiteMenuItem|null $lockedParent */
            $lockedParent = $targetParent === null
                ? null
                : $lockedItems->firstWhere($targetParent->getKeyName(), $targetParent->getKey());

            if ($targetParent !== null
                && ($lockedParent === null || (int)$lockedParent->site_menu_id !== $targetMenuId)) {
                throw ValidationException::withMessages([
                    'target_parent_id' => 'Родитель должен принадлежать выбранному меню.',
                ]);
            }

            if ($lockedItems
                ->where('site_menu_id', $targetMenuId)
                ->contains(static fn(SiteMenuItem $node): bool => $node->key === $source->key)) {
                throw ValidationException::withMessages([
                    'target_menu_id' => 'В целевом меню уже существует пункт с таким ключом.',
                ]);
            }

            $this->assertTreeIsValid($sourceMenuId);
            $this->assertTreeIsValid($targetMenuId);

            $left = (int)$source->_lft;
            $right = (int)$source->_rgt;
            $width = $right - $left + 1;

            if ($left < 1 || $right <= $left || $width % 2 !== 0) {
                throw new RuntimeException("Пункт меню [{$source->getKey()}] имеет некорректные границы дерева.");
            }

            $branch = $lockedItems
                ->where('site_menu_id', $sourceMenuId)
                ->filter(static fn(SiteMenuItem $node): bool => (int)$node->_lft >= $left && (int)$node->_rgt <= $right)
                ->values();

            $targetCut = $lockedParent === null
                ? ((int)$lockedItems->where('site_menu_id', $targetMenuId)->max('_rgt')) + 1
                : (int)$lockedParent->_rgt;

            $table = (new SiteMenuItem)->getTable();
            $branchIds = $branch->modelKeys();

            DB::table($table)
                ->whereIn('id', $branchIds)
                ->update(['site_menu_id' => $targetMenuId, '_lft' => 0, '_rgt' => 0]);

            DB::table($table)
                ->where('site_menu_id', $sourceMenuId)
                ->where('_lft', '>', $right)
                ->decrement('_lft', $width);
            DB::table($table)
                ->where('site_menu_id', $sourceMenuId)
                ->where('_rgt', '>', $right)
                ->decrement('_rgt', $width);

            DB::table($table)
                ->where('site_menu_id', $targetMenuId)
                ->where('_lft', '>=', $targetCut)
                ->increment('_lft', $width);
            DB::table($table)
                ->where('site_menu_id', $targetMenuId)
                ->where('_rgt', '>=', $targetCut)
                ->increment('_rgt', $width);

            foreach ($branch as $node) {
                $attributes = [
                    'site_menu_id' => $targetMenuId,
                    '_lft' => $targetCut + (int)$node->_lft - $left,
                    '_rgt' => $targetCut + (int)$node->_rgt - $left,
                    'updated_at' => now(),
                ];

                if ((int)$node->getKey() === (int)$source->getKey()) {
                    $attributes['parent_id'] = $lockedParent?->getKey();
                }

                DB::table($table)->where('id', $node->getKey())->update($attributes);
            }

            $this->assertTreeIsValid($sourceMenuId);
            $this->assertTreeIsValid($targetMenuId);

            return SiteMenuItem::query()
                ->with(['menu', 'parent', 'section'])
                ->findOrFail($source->getKey());
        }, 3);
    }

    private function assertTreeIsValid(int $menuId): void
    {
        if (SiteMenuItem::scoped(['site_menu_id' => $menuId])->isBroken()) {
            throw new RuntimeException("Дерево пунктов меню [{$menuId}] повреждено.");
        }
    }
}

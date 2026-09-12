<?php

namespace Tests\Support;

use BackedEnum;
use Services\CatalogImport\CategoryRegistry;

trait ConfiguresCategoryMatching
{
    private function addMatchingRule(array $rule): void
    {
        $rule += ['match_when' => null, 'value' => null, 'priority' => 0, 'is_active' => true];
        foreach (['type', 'match_when'] as $key) {
            if ($rule[$key] instanceof BackedEnum) {
                $rule[$key] = $rule[$key]->value;
            }
        }
        config(['category_matching.rules' => [...config('category_matching.rules'), $rule]]);
        app()->forgetInstance(CategoryRegistry::class);
    }
}

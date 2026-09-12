<?php

namespace Services\CatalogImport;

use Domain\Catalog\Enums\CategoryMatchType;
use Domain\Catalog\Enums\CategoryMatchWhen;
use InvalidArgumentException;

/** Проверка конфигурации без запросов к БД; порядок при равном приоритете задаёт список. */
final class CategoryMatchingRules
{
    public static function ordered(mixed $rules): array
    {
        if (! is_array($rules) || ! array_is_list($rules)) {
            throw new InvalidArgumentException('category_matching.rules должен быть списком.');
        }

        $seen = [];
        foreach ($rules as $index => $rule) {
            $error = "Правило #{$index}: ";
            if (! is_array($rule) || array_diff(['slug', 'type', 'match_when', 'value', 'priority', 'is_active'], array_keys($rule))) {
                throw new InvalidArgumentException($error.'неполная структура.');
            }
            if (! is_string($rule['slug']) || trim($rule['slug']) === ''
                || ! is_string($rule['type']) || CategoryMatchType::tryFrom($rule['type']) === null
                || ! is_int($rule['priority']) || $rule['priority'] < 0 || $rule['priority'] > 65535
                || ! is_bool($rule['is_active'])
                || ($rule['value'] !== null && (! is_string($rule['value']) || mb_strlen($rule['value']) > 255))) {
                throw new InvalidArgumentException($error.'некорректные поля.');
            }
            $alcohol = $rule['type'] === CategoryMatchType::Alcohol->value;
            if ($alcohol ? (! is_string($rule['match_when']) || CategoryMatchWhen::tryFrom($rule['match_when']) === null) : $rule['match_when'] !== null) {
                throw new InvalidArgumentException($error.'некорректный match_when.');
            }
            $needsValue = ! $alcohol || $rule['match_when'] === 'style';
            if ($needsValue ? blank($rule['value']) : $rule['value'] !== null) {
                throw new InvalidArgumentException($error.'некорректное значение value.');
            }
            $key = $alcohol && ! $needsValue ? 'alcohol:'.$rule['match_when']
                : ($rule['type'] === 'accessory_title' ? 'accessory:'.mb_strtolower($rule['value']) : null);
            if ($rule['is_active'] && $key !== null) {
                if (isset($seen[$key])) {
                    throw new InvalidArgumentException($error."дублирующее активное правило {$key}.");
                }
                $seen[$key] = true;
            }
        }

        // Сортировка PHP стабильна: равный priority сохраняет исходный порядок.
        usort($rules, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $rules;
    }
}

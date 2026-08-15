<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Spatie\LaravelSettings\Settings;

/**
 * Базовый сидер для групп spatie/laravel-settings. Дефолты settings-миграции
 * — единственный источник значений здесь тоже, сидер их не выдумывает заново.
 *
 * Две фазы:
 *  1. repair (всегда) — дописывает через SettingsMigrator строки, которых нет
 *     в репозитории (например, класс настроек получил новое свойство, а
 *     settings-миграцию для него забыли написать — иначе Spatie бросит
 *     MissingSettings при первом же чтении).
 *  2. reset (только $force) — возвращает существующие значения к дефолтам
 *     через fill()->save(). Журнальные поля (см. nonResettable()) не
 *     трогаются даже при force — это история выполнения, а не конфиг.
 */
abstract class AbstractSettingsSeeder extends Seeder
{
    /** @return class-string<Settings> */
    abstract protected function settingsClass(): string;

    /** @return array<string, mixed> имя_свойства => дефолт */
    abstract protected function defaults(): array;

    /** @return list<string> свойства, которые reset-фаза не должна перезаписывать */
    protected function nonResettable(): array
    {
        return [];
    }

    /** Хук для инвалидации кэшей, зависящих от группы (например, CategoryRegistry). */
    protected function afterSeed(): void {}

    public function run(bool $force = false): void
    {
        $force = $force || (bool) env('SETTINGS_SEED_FORCE', false);

        $group = $this->settingsClass()::group();
        $migrator = app(SettingsMigrator::class);

        $created = 0;
        foreach ($this->defaults() as $name => $value) {
            if (! $migrator->exists("{$group}.{$name}")) {
                $migrator->add("{$group}.{$name}", $value);
                $created++;
            }
        }

        $reset = 0;
        if ($force) {
            $resettable = array_diff_key($this->defaults(), array_flip($this->nonResettable()));
            app($this->settingsClass())->fill($resettable)->save();
            $reset = count($resettable);
        }

        $this->afterSeed();

        $this->command?->info(sprintf(
            '[%s] создано: %d, сброшено к дефолту: %d%s',
            $group,
            $created,
            $reset,
            $force ? '' : ' (force выключен — существующие значения не тронуты)',
        ));
    }
}

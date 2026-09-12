<?php

namespace Database\Seeders;

use Domain\Telegram\Models\TelegramBot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;

/** Installs the local database snapshot without replacing existing content. */
final class CurrentSiteDataSeeder extends Seeder
{
    private const CMS_TABLES = [
        'site_sections', 'site_menus', 'site_menu_items', 'content_blocks', 'content_block_items',
    ];

    public function __construct(private ?array $fixture = null, private ?array $tokens = null) {}

    public function run(): void
    {
        $data = $this->fixture ?? require __DIR__.'/data/current-site.php';
        $secretsPath = __DIR__.'/data/current-site.local.php';
        $tokens = $this->tokens ?? (is_file($secretsPath) ? require $secretsPath : []);

        DB::transaction(function () use ($data, $tokens): void {
            foreach ($data['settings'] as $row) {
                $key = ['group' => $row['group'], 'name' => $row['name']];
                unset($row['id']);
                if (! DB::table('settings')->where($key)->exists()) {
                    DB::table('settings')->insert($row);
                }
            }

            // The CMS is one connected graph. Do not mix snapshot IDs with live IDs.
            if (array_any(self::CMS_TABLES, fn ($table) => DB::table($table)->exists())) {
                $this->command?->info('CMS содержит данные — снимок CMS пропущен.');
            } else {
                foreach (self::CMS_TABLES as $table) {
                    $rows = $data[$table];
                    if ($table === 'site_menu_items') {
                        // Nested-set preorder always puts parents before their children.
                        usort($rows, fn ($a, $b) => [$a['site_menu_id'], $a['_lft']] <=> [$b['site_menu_id'], $b['_lft']]);
                    }
                    foreach ($rows as $row) {
                        DB::table($table)->insert($row);
                    }
                }
            }

            if (! DB::table('telegraph_bots')->exists()) {
                $bots = [];
                foreach ($data['telegraph_bots'] as $row) {
                    $token = $tokens[$row['id']] ?? null;
                    if (! is_string($token) || trim($token) === '') {
                        throw new LogicException('Для импорта ботов заполните database/seeders/data/current-site.local.php по примеру current-site.local.example.php.');
                    }
                    $row['token'] = $token;
                    $bots[] = $row;
                }
                foreach ($bots as $row) {
                    // Query builder avoids Telegram API calls from model observers.
                    DB::table('telegraph_bots')->insert($row);
                }
            }
        });

        TelegramBot::flushCache();
        foreach (app(SettingsCacheFactory::class)->all() as $cache) {
            $cache->clear();
        }
    }
}

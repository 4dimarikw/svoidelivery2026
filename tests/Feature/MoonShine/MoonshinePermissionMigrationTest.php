<?php

declare(strict_types=1);

namespace Tests\Feature\MoonShine;

use App\MoonShine\Models\MoonshinePermission;
use Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

class MoonshinePermissionMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_duplicate_preflight_and_index_rollback_preserve_data(): void
    {
        $migration = require database_path('migrations/2026_09_26_120000_add_unique_moonshine_permissions.php');
        $role = MoonshineUserRole::create(['name' => 'Migration role']);
        $permission = MoonshinePermission::create(['moonshine_user_role_id' => $role->id, 'model' => Product::class, 'permissions' => ['view' => true]]);
        $migration->down();
        $duplicate = MoonshinePermission::create(['moonshine_user_role_id' => $role->id, 'model' => Product::class, 'permissions' => ['view' => false]]);
        $before = DB::table('moonshine_permissions')->orderBy('id')->get()->toJson();
        try {
            $migration->up();
            self::fail('Migration must refuse duplicates');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('role='.$role->id, $exception->getMessage());
            self::assertStringContainsString(Product::class, $exception->getMessage());
            self::assertStringContainsString("ids=[{$permission->id}, {$duplicate->id}]", $exception->getMessage());
            self::assertSame($before, DB::table('moonshine_permissions')->orderBy('id')->get()->toJson());
            self::assertFalse(Schema::hasIndex('moonshine_permissions', 'moonshine_permissions_role_model_unique'));
        }
        $duplicate->delete();
        $migration->up();
        self::assertTrue(Schema::hasIndex('moonshine_permissions', 'moonshine_permissions_role_model_unique'));
        $migration->down();
        self::assertFalse(Schema::hasIndex('moonshine_permissions', 'moonshine_permissions_role_model_unique'));
        self::assertSame(['view' => true], $permission->refresh()->permissions);
        // Restore migration state for DatabaseMigrations teardown.
        $migration->up();
    }
}

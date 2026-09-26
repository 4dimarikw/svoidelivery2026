<?php

declare(strict_types=1);

namespace Tests\Feature\MoonShine;

use App\MoonShine\Models\MoonshinePermission;
use App\MoonShine\Resources\Favorite\FavoriteResource;
use App\MoonShine\Resources\MoonshinePermission\MoonshinePermissionResource;
use App\MoonShine\Resources\Product\ProductResource;
use App\MoonShine\Support\PermissionCache;
use App\MoonShine\Support\PermissionData;
use App\MoonShine\Support\PermissionModels;
use Database\Seeders\MoonshinePermissionSeeder;
use Domain\Catalog\Models\Product;
use Domain\Favorite\Models\Favorite;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MoonShine\ImportExport\ImportHandler;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\Ability;
use Tests\TestCase;

class MoonshinePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(bool $superuser = false): MoonshineUser
    {
        return MoonshineUser::create([
            'name' => 'Staff', 'email' => uniqid().'@test.local', 'password' => bcrypt('password'),
            'moonshine_user_role_id' => $superuser ? MoonshineUserRole::DEFAULT_ROLE_ID : MoonshineUserRole::create(['name' => uniqid('role')])->id,
        ]);
    }

    private function permission(MoonshineUser $user, array $permissions, string $model = Product::class): MoonshinePermission
    {
        return MoonshinePermission::create(['moonshine_user_role_id' => $user->moonshine_user_role_id, 'model' => $model, 'permissions' => $permissions]);
    }

    public function test_all_gate_abilities_and_default_denials(): void
    {
        $staff = $this->staff();
        $admin = $this->staff(true);
        $model = new Product;
        foreach (Ability::cases() as $ability) {
            self::assertTrue(Gate::forUser($admin)->allows($ability->value, $model));
            self::assertFalse(Gate::forUser($staff)->allows($ability->value, $model));
        }

        $permission = $this->permission($staff, array_fill_keys(array_column(Ability::cases(), 'value'), true));
        foreach (Ability::cases() as $ability) {
            self::assertTrue(Gate::forUser($staff)->allows($ability->value, $model));
        }
        $permission->update(['permissions' => array_fill_keys(array_column(Ability::cases(), 'value'), false)]);
        foreach (Ability::cases() as $ability) {
            self::assertFalse(Gate::forUser($staff)->allows($ability->value, $model));
        }
        $permission->update(['permissions' => ['view' => true]]);
        self::assertFalse(Gate::forUser($staff)->allows('update', $model));
        $staff->moonshine_user_role_id = null;
        $staff->unsetRelation('moonshineUserRole');
        self::assertFalse(Gate::forUser($staff)->allows('view', $model));
    }

    public function test_cache_includes_missing_records_and_isolates_roles_and_models(): void
    {
        $first = $this->staff();
        $second = $this->staff();
        $this->permission($first, ['view' => true]);
        $this->permission($second, ['view' => false]);
        $cache = app(PermissionCache::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            for ($i = 0; $i < 2; $i++) {
                self::assertSame(['view' => true], $cache->get($first->moonshine_user_role_id, Product::class));
                self::assertSame(['view' => false], $cache->get($second->moonshine_user_role_id, Product::class));
                self::assertNull($cache->get($first->moonshine_user_role_id, Favorite::class));
            }
            self::assertCount(3, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_eloquent_invalidates_old_and_new_pairs_and_deletions(): void
    {
        $first = $this->staff();
        $second = $this->staff();
        $cache = app(PermissionCache::class);
        self::assertNull($cache->get($first->moonshine_user_role_id, Product::class));
        self::assertNull($cache->get($second->moonshine_user_role_id, Favorite::class));
        $permission = $this->permission($first, ['view' => true]);
        self::assertSame(['view' => true], $cache->get($first->moonshine_user_role_id, Product::class));
        $permission->update(['permissions' => ['view' => false]]);
        self::assertSame(['view' => false], $cache->get($first->moonshine_user_role_id, Product::class));
        $permission->update(['moonshine_user_role_id' => $second->moonshine_user_role_id, 'model' => Favorite::class]);
        self::assertNull($cache->get($first->moonshine_user_role_id, Product::class));
        self::assertSame(['view' => false], $cache->get($second->moonshine_user_role_id, Favorite::class));
        $permission->delete();
        self::assertNull($cache->get($second->moonshine_user_role_id, Favorite::class));
    }

    public function test_new_scope_gets_fresh_permissions_even_after_a_bulk_update(): void
    {
        $staff = $this->staff();
        $permission = $this->permission($staff, ['view' => true]);
        $old = app(PermissionCache::class);
        self::assertSame(['view' => true], $old->get($staff->moonshine_user_role_id, Product::class));
        DB::table('moonshine_permissions')->where('id', $permission->id)->update(['permissions' => json_encode(['view' => false])]);
        $this->app->forgetScopedInstances();
        $fresh = app(PermissionCache::class);
        self::assertNotSame($old, $fresh);
        self::assertSame(['view' => false], $fresh->get($staff->moonshine_user_role_id, Product::class));
    }

    public function test_database_rejects_duplicate_pairs(): void
    {
        $staff = $this->staff();
        $this->permission($staff, []);
        $this->expectException(QueryException::class);
        $this->permission($staff, ['view' => true]);
    }

    public function test_validation_rejects_unknown_models_keys_values_and_duplicates(): void
    {
        $staff = $this->staff();
        $data = ['moonshine_user_role_id' => $staff->moonshine_user_role_id, 'model' => Product::class, 'permissions' => ['view' => true]];
        $validator = app(PermissionData::class);
        foreach ([
            ['model' => 'Unknown\\Model'], ['model' => str_repeat('a', 256)],
            ['permissions' => ['unknown' => true]], ['permissions' => ['view' => 'true']],
            ['permissions' => ['view' => 2]], ['permissions' => ['view' => null]],
            ['moonshine_user_role_id' => 99999999], ['moonshine_user_role_id' => ['invalid']],
        ] as $invalid) {
            $candidate = array_replace($data, $invalid);
            self::assertTrue(Validator::make($candidate, $validator->rules(null, $candidate['moonshine_user_role_id']))->fails());
        }
        $permission = $this->permission($staff, []);
        self::assertTrue(Validator::make($data, $validator->rules(null, $staff->moonshine_user_role_id))->fails());
        self::assertFalse(Validator::make($data, $validator->rules($permission->id, $staff->moonshine_user_role_id))->fails());
    }

    public function test_import_checks_data_and_preserves_boolean_json_format(): void
    {
        $staff = $this->staff();
        $resource = app(MoonshinePermissionResource::class);
        $path = storage_path('framework/permission-import-'.uniqid().'.csv');
        $write = static function (array $row) use ($path): void {
            $file = fopen($path, 'w');
            fputcsv($file, ['id', 'moonshine_user_role_id', 'model', 'permissions'], ';', '"', '');
            fputcsv($file, $row, ';', '"', '');
            fclose($file);
        };
        try {
            $write(['', $staff->moonshine_user_role_id, Product::class, json_encode(['view' => '1', 'update' => '0'])]);
            ImportHandler::process($path, $resource, delimiter: ';');
            $permission = MoonshinePermission::where('moonshine_user_role_id', $staff->moonshine_user_role_id)->firstOrFail();
            self::assertSame(['view' => true, 'update' => false], $permission->permissions);
            $write([$permission->id, $staff->moonshine_user_role_id, Product::class, json_encode(['view' => 1])]);
            ImportHandler::process($path, $resource, delimiter: ';');
            self::assertSame(['view' => true], $permission->refresh()->permissions);
            foreach ([
                [$permission->id, Product::class, ['view' => 'yes']],
                [$permission->id, Product::class, ['unknown' => true]],
                [$permission->id, 'Unknown\\Model', ['view' => true]],
                [$permission->id, Product::class, null],
                ['', Product::class, ['view' => true]],
            ] as [$id, $model, $invalid]) {
                $write([$id, $staff->moonshine_user_role_id, $model, json_encode($invalid)]);
                try {
                    ImportHandler::process($path, $resource, delimiter: ';');
                    self::fail('Invalid import must fail validation');
                } catch (ValidationException) {
                    self::assertSame(['view' => true], $permission->refresh()->permissions);
                    self::assertSame(1, MoonshinePermission::where('moonshine_user_role_id', $staff->moonshine_user_role_id)->count());
                }
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_form_saves_fixed_switches_as_booleans_and_rejects_duplicates(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $admin = $this->staff(true);
        $staff = $this->staff();
        $data = ['moonshine_user_role_id' => $staff->moonshine_user_role_id, 'model' => Product::class,
            'permissions' => ['viewAny' => '1', 'view' => true, 'create' => 0, 'update' => 1, 'delete' => false, 'restore' => '0', 'massDelete' => 0, 'forceDelete' => false]];
        $this->actingAs($admin, 'moonshine');
        $this->post('/admin/resource/moonshine-permission-resource/crud', $data)->assertRedirect()->assertSessionHasNoErrors();
        $permission = MoonshinePermission::where('moonshine_user_role_id', $staff->moonshine_user_role_id)->firstOrFail();
        self::assertEquals(PermissionData::normalize($data['permissions']), $permission->permissions);
        $this->postJson('/admin/resource/moonshine-permission-resource/crud', $data)->assertUnprocessable()->assertJsonValidationErrors('model');
        $data['permissions']['update'] = '0';
        $this->put('/admin/resource/moonshine-permission-resource/crud/'.$permission->id, $data)->assertRedirect()->assertSessionHasNoErrors();
        self::assertFalse($permission->refresh()->permissions['update']);
        $data['permissions']['unknown'] = 1;
        $this->putJson('/admin/resource/moonshine-permission-resource/crud/'.$permission->id, $data)->assertUnprocessable()->assertJsonValidationErrors('permissions');
        $response = $this->get('/admin/resource/moonshine-permission-resource/moonshine-permission-form-page/'.$permission->id)->assertOk();
        foreach (PermissionData::LABELS as $key => $label) {
            $response->assertSee($label)->assertSee('permissions['.$key.']', false);
        }
        $response->assertSee('Права не отменяют ограничения меню')->assertSee('матрица не управляет доступом');
        $create = $this->get('/admin/resource/moonshine-permission-resource/moonshine-permission-form-page')->assertOk()->assertSee('Просмотр списка');
        $index = $this->get('/admin/resource/moonshine-permission-resource/moonshine-permission-index-page')->assertOk();
        $modal = $this->get('/admin/resource/moonshine-permission-resource/moonshine-permission-form-page?_fragment-load=crud-form&_async_form=1&_component_name=moonshine-permission-resource')
            ->assertOk()->assertSee('Просмотр списка')->assertSee('permissions[forceDelete]', false);

        // Optional rendered artifacts for browser QA; generated exclusively from test data.
        if (getenv('PERMISSION_VISUAL_QA') === '1') {
            $directory = storage_path('framework/permission-qa');
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            foreach (['edit' => $response, 'create' => $create, 'index' => $index, 'modal' => $modal] as $name => $page) {
                file_put_contents($directory.'/'.$name.'.html', str_replace('http://localhost:8000/vendor/', '/public/vendor/', $page->getContent()));
            }
        }
    }

    public function test_every_policy_enabled_resource_has_all_abilities(): void
    {
        $count = 0;
        foreach (moonshine()->getResources() as $resource) {
            if (! $resource instanceof ModelResource || ! $resource->isWithPolicy()) {
                continue;
            }
            $policy = Gate::getPolicyFor($resource->getModel());
            self::assertNotNull($policy, $resource::class);
            foreach (Ability::cases() as $ability) {
                self::assertTrue(is_callable([$policy, $ability->value]), $resource::class.':'.$ability->value);
            }
            $count++;
        }
        self::assertGreaterThan(0, $count);
    }

    public function test_menu_restrictions_and_explanations_are_preserved(): void
    {
        $staff = $this->staff();
        $admin = $this->staff(true);
        $models = app(PermissionModels::class);
        self::assertFalse($models->menuVisible(FavoriteResource::class, $staff));
        self::assertTrue($models->menuVisible(FavoriteResource::class, $admin));
        self::assertTrue($models->menuVisible(ProductResource::class, $staff));
        self::assertStringContainsString('меню только для SuperUser', $models->options()[Favorite::class]);
        $this->seed(MoonshinePermissionSeeder::class);
        $managerRole = MoonshineUserRole::where('name', 'Manager')->firstOrFail();
        $staff->update(['moonshine_user_role_id' => $managerRole->id]);
        $this->actingAs($staff, 'moonshine')->get('/admin/resource/product-resource/product-index-page')->assertOk()
            ->assertDontSee('/admin/resource/favorite-resource', false)
            ->assertDontSee('/admin/resource/moonshine-permission-resource', false);
        $this->withoutMiddleware(AuthenticateSession::class);
        $this->actingAs($admin, 'moonshine')->get('/admin/resource/product-resource/product-index-page')->assertOk()
            ->assertSee('/admin/resource/favorite-resource', false)->assertSee('/admin/resource/moonshine-permission-resource', false);
    }
}

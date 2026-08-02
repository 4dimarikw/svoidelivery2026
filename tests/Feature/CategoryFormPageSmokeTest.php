<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

/**
 * Regression for the "Resource is required for RelationRepeater (matchRules)"
 * FieldException from laravel.log — caused by a stale Composer optimized
 * classmap not including newly-added App\MoonShine\Resources\CategoryMatchRule\*
 * classes (MoonShine resolves RelationRepeater's `resource:` via
 * OptimizerCollection::getFiltered(), which reads ClassLoader::getClassMap(),
 * not the filesystem). Fixed with `composer dump-autoload`; this test hits
 * the actual admin route to prove the form renders end-to-end.
 */
class CategoryFormPageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_edit_form_renders_without_field_exception(): void
    {
        $admin = MoonshineUser::query()->create([
            'email' => 'smoke@test.local',
            'password' => bcrypt('password'),
            'name' => 'Smoke Test',
        ]);

        $categoryId = \Domain\Catalog\Models\Category::query()->where('slug', 'beer')->value('id');

        $response = $this->actingAs($admin, 'moonshine')
            ->get("/admin/resource/category-resource/category-form-page/{$categoryId}");

        $response->assertOk();
    }
}

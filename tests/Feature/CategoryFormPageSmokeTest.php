<?php

namespace Tests\Feature;

use Domain\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

/** Проверяет форму и список категорий после удаления редактора правил. */
class CategoryFormPageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_edit_form_renders_without_field_exception(): void
    {
        $admin = MoonshineUser::query()->create([
            'email' => 'smoke@test.local',
            'password' => bcrypt('password'),
            'name' => 'Smoke Test',
            // Роль суперюзера — тест проверяет рендер формы, а не авторизацию
            // (без неё CategoryPolicy/withPolicy режет доступ 403 при отсутствии роли).
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);

        $categoryId = Category::query()->where('slug', 'beer')->value('id');

        $response = $this->actingAs($admin, 'moonshine')
            ->get("/admin/resource/category-resource/category-form-page/{$categoryId}");

        $response->assertOk()->assertDontSee('matchRules');
        $this->get('/admin/resource/category-resource/category-index-page')->assertOk();
    }
}

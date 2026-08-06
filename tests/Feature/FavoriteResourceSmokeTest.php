<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Domain\Favorite\Models\Favorite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

/**
 * Смоук на реальный admin-роут — та же регрессия, что и
 * CategoryFormPageSmokeTest: MoonShine резолвит resource/page uri через
 * OptimizerCollection (оптимизированный classmap), а не сканирование
 * файловой системы, так что новый App\MoonShine\Resources\Favorite\* класс
 * не подхватится без composer dump-autoload — этот тест бьёт по
 * реальному HTTP-роуту и проверяет, что раздел действительно виден.
 */
class FavoriteResourceSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function superuser(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'email' => 'favorite-smoke@test.local',
            'password' => bcrypt('password'),
            'name' => 'Smoke Test',
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
    }

    public function test_index_page_renders(): void
    {
        Favorite::factory()->create();

        $response = $this->actingAs($this->superuser(), 'moonshine')
            ->get('/admin/resource/favorite-resource/favorite-index-page');

        $response->assertOk();
    }

    public function test_form_page_renders_for_an_existing_record(): void
    {
        $favorite = Favorite::factory()->create();

        $response = $this->actingAs($this->superuser(), 'moonshine')
            ->get("/admin/resource/favorite-resource/favorite-form-page/{$favorite->id}");

        $response->assertOk();
    }

    public function test_user_form_page_renders_the_favorites_tab(): void
    {
        $user = User::factory()->create();
        Favorite::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($this->superuser(), 'moonshine')
            ->get("/admin/resource/user-resource/user-form-page/{$user->id}");

        $response->assertOk();
    }
}

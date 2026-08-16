<?php

declare(strict_types=1);

namespace Tests\Feature\Vk;

use App\MoonShine\Pages\VkSyncSettingsPage;
use Domain\Vk\Enums\VkPostStatus;
use Domain\Vk\Models\VkPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use Tests\TestCase;

/**
 * Смоук на реальный admin-роут — та же регрессия, что и
 * FavoriteResourceSmokeTest: без обновлённого classmap (composer
 * dump-autoload) новый App\MoonShine\Resources\VkPost\* класс не
 * подхватится MoonShine, и роут вернёт 404/500 вместо страницы.
 */
class VkAdminSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function superuser(): MoonshineUser
    {
        return MoonshineUser::query()->create([
            'email' => 'vk-smoke@test.local',
            'password' => bcrypt('password'),
            'name' => 'Smoke Test',
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
    }

    private function makePost(array $attributes = []): VkPost
    {
        return VkPost::query()->create([
            'owner_id' => -1,
            'vk_post_id' => 1,
            'post_type' => 'post',
            'posted_at' => now(),
            'text' => 'Новость',
            'images' => [],
            'raw' => [],
            'status' => VkPostStatus::DRAFT,
            ...$attributes,
        ]);
    }

    public function test_index_page_renders(): void
    {
        $this->makePost();

        $this->actingAs($this->superuser(), 'moonshine')
            ->get('/admin/resource/vk-post-resource/vk-post-index-page')
            ->assertOk();
    }

    public function test_form_page_renders_for_an_existing_post(): void
    {
        $post = $this->makePost();

        $this->actingAs($this->superuser(), 'moonshine')
            ->get("/admin/resource/vk-post-resource/vk-post-form-page/{$post->id}")
            ->assertOk()
            ->assertSee('https://vk.com/wall-1_1');
    }

    public function test_form_page_shows_post_images(): void
    {
        $post = $this->makePost(['images' => ['https://sun9-1.userapi.com/a.jpg']]);

        $this->actingAs($this->superuser(), 'moonshine')
            ->get("/admin/resource/vk-post-resource/vk-post-form-page/{$post->id}")
            ->assertOk()
            ->assertSee('https://sun9-1.userapi.com/a.jpg', false);
    }

    public function test_detail_page_renders(): void
    {
        $post = $this->makePost();

        $this->actingAs($this->superuser(), 'moonshine')
            ->get("/admin/resource/vk-post-resource/vk-post-detail-page/{$post->id}")
            ->assertOk();
    }

    public function test_settings_page_renders(): void
    {
        $this->actingAs($this->superuser(), 'moonshine')
            ->get(app(VkSyncSettingsPage::class)->getUrl())
            ->assertOk();
    }
}

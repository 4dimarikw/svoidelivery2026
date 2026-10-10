<?php

namespace Tests\Feature\MoonShine;

use App\MoonShine\Models\MoonshinePermission;
use App\MoonShine\Resources\Seo\SeoFields;
use App\MoonShine\Resources\Seo\SeoResource;
use App\MoonShine\Support\PermissionModels;
use Dimarik\LaravelSeo\Models\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use MoonShine\Laravel\Models\MoonshineUser;
use MoonShine\Laravel\Models\MoonshineUserRole;
use MoonShine\Laravel\TypeCasts\ModelCaster;
use MoonShine\Support\Enums\Ability;
use Tests\TestCase;

class SeoResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_text_fields_follow_updated_package_limits(): void
    {
        $rules = SeoFields::rules((new ModelCaster(Seo::class))->cast(new Seo));
        foreach (['description', 'keywords', 'text'] as $field) {
            $this->assertTrue(Validator::make([$field => str_repeat('Я', 21845)], [$field => $rules[$field]])->passes());
            $this->assertTrue(Validator::make([$field => str_repeat('Я', 21846)], [$field => $rules[$field]])->fails());
        }
    }

    public function test_superuser_can_create_and_edit_seo_with_local_path_validation(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $admin = MoonshineUser::create([
            'name' => 'SEO Admin',
            'email' => 'seo-admin@test.local',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => MoonshineUserRole::DEFAULT_ROLE_ID,
        ]);
        $this->actingAs($admin, 'moonshine');
        $this->get('/admin/resource/seo-resource/seo-index-page')->assertOk();
        $data = ['url' => '/seo-admin-test', 'title' => 'Admin SEO title', 'description' => 'Admin description'];
        $this->post('/admin/resource/seo-resource/crud', $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $seo = Seo::where('url', $data['url'])->firstOrFail();
        $data['title'] = 'Updated SEO title';
        $this->put('/admin/resource/seo-resource/crud/'.$seo->id, $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($data['title'], $seo->refresh()->title);
        $data['url'] = '//external.example/path';
        $this->putJson('/admin/resource/seo-resource/crud/'.$seo->id, $data)
            ->assertUnprocessable()->assertJsonValidationErrors('url');
        $this->assertSame('/seo-admin-test', $seo->refresh()->url);
        foreach (Ability::cases() as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability->value, $seo));
        }
        $this->delete('/admin/resource/seo-resource/crud/'.$seo->id)->assertRedirect();
        $this->assertDatabaseMissing('seo', ['id' => $seo->id]);
    }

    public function test_staff_cannot_access_seo_even_with_all_matrix_permissions(): void
    {
        $this->withoutMiddleware(AuthenticateSession::class);
        $staff = MoonshineUser::create([
            'name' => 'SEO Staff',
            'email' => 'seo-staff@test.local',
            'password' => bcrypt('password'),
            'moonshine_user_role_id' => MoonshineUserRole::create(['name' => 'SEO Staff Role'])->id,
        ]);
        MoonshinePermission::create([
            'moonshine_user_role_id' => $staff->moonshine_user_role_id,
            'model' => Seo::class,
            'permissions' => array_fill_keys(array_column(Ability::cases(), 'value'), true),
        ]);
        $seo = Seo::create(['url' => '/restricted-seo', 'title' => 'Original title']);
        foreach (Ability::cases() as $ability) {
            $this->assertFalse(Gate::forUser($staff)->allows($ability->value, $seo));
        }
        $this->assertFalse(app(PermissionModels::class)->menuVisible(SeoResource::class, $staff));
        $this->actingAs($staff, 'moonshine');
        $this->get('/admin/resource/seo-resource/seo-index-page')->assertForbidden();
        $this->get('/admin/resource/seo-resource/seo-form-page')->assertForbidden();
        $this->get('/admin/resource/seo-resource/seo-form-page?resourceItem='.$seo->id)->assertForbidden();
        $this->get('/admin/resource/seo-resource/seo-detail-page?resourceItem='.$seo->id)->assertForbidden();
        $data = ['url' => '/staff-created-seo', 'title' => 'Staff title'];
        $this->postJson('/admin/resource/seo-resource/crud', $data)->assertForbidden();
        $this->putJson('/admin/resource/seo-resource/crud/'.$seo->id, $data)->assertForbidden();
        $this->deleteJson('/admin/resource/seo-resource/crud/'.$seo->id)->assertForbidden();
        $this->assertDatabaseMissing('seo', ['url' => $data['url']]);
        $this->assertSame('Original title', $seo->refresh()->title);
    }
}

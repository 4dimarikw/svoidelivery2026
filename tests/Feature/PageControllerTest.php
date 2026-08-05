<?php

namespace Tests\Feature;

use Tests\TestCase;

class PageControllerTest extends TestCase
{
    public function test_home_page_renders_with_header_and_footer(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(__('account.login.submit'));
    }

    public function test_ui_kit_route_is_local_only(): void
    {
        // routes/web.php: Route::view('/ui-kit', ...) is wrapped in
        // app()->environment('local') — must not be reachable outside it.
        $this->assertSame('testing', app()->environment());

        $this->get('/ui-kit')->assertNotFound();
    }
}

<?php

namespace Tests\Unit\Vk;

use Illuminate\Support\Facades\Http;
use Services\Vk\Support\VkImageUrlValidator;
use Tests\TestCase;

class VkImageUrlValidatorTest extends TestCase
{
    private function validator(): VkImageUrlValidator
    {
        return new VkImageUrlValidator;
    }

    public function test_rejects_non_https_scheme(): void
    {
        Http::fake();

        $this->assertFalse(($this->validator())('http://sun9-1.userapi.com/photo.jpg'));
        Http::assertNothingSent();
    }

    public function test_rejects_disallowed_host_without_network_call(): void
    {
        Http::fake();

        $this->assertFalse(($this->validator())('https://evil.example.com/photo.jpg'));
        Http::assertNothingSent();
    }

    public function test_accepts_reachable_image_on_allowed_host(): void
    {
        Http::fake([
            'sun9-1.userapi.com/*' => Http::response('', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->assertTrue(($this->validator())('https://sun9-1.userapi.com/photo.jpg'));
    }

    public function test_rejects_when_head_request_fails(): void
    {
        Http::fake([
            'sun9-1.userapi.com/*' => Http::response('', 404),
        ]);

        $this->assertFalse(($this->validator())('https://sun9-1.userapi.com/missing.jpg'));
    }

    public function test_rejects_when_content_type_is_not_an_image(): void
    {
        Http::fake([
            'sun9-1.userapi.com/*' => Http::response('', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->assertFalse(($this->validator())('https://sun9-1.userapi.com/not-an-image'));
    }
}

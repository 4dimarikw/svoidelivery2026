<?php

namespace Tests\Unit\Logging;

use PHPUnit\Framework\Attributes\DataProvider;
use Support\Logging\LogRedactor;
use Tests\TestCase;

class LogRedactorTest extends TestCase
{
    public static function secrets(): array
    {
        return [
            ['password=short-secret', 'short-secret'],
            ['{"password":"secret with spaces"}', 'secret with spaces'],
            ['Authorization: Bearer short-secret', 'short-secret'],
            ['Authorization: Basic dXNlcjpwYXNz', 'dXNlcjpwYXNz'],
            ['https://name:short-secret@example.com/file', 'short-secret'],
            ['ftp://name:short-secret@example.com/file', 'short-secret'],
            ['https://example.com/path?arbitrary=short-secret&x=2', 'short-secret'],
            ['/telegraph/short-secret/webhook', 'short-secret'],
            ['https://api.telegram.org/botshort-secret/sendMessage', 'short-secret'],
            ['init_data="short-secret"', 'short-secret'],
            ['initData=short-secret', 'short-secret'],
            ['password%3Dshort-secret', 'short-secret'],
            ['user=%7B%22id%22%3A1%7D&auth_date=123&hash='.str_repeat('a', 64), 'auth_date=123'],
            ["-----BEGIN PRIVATE KEY-----\nshort-secret\n-----END PRIVATE KEY-----", 'short-secret'],
        ];
    }

    #[DataProvider('secrets')]
    public function test_text_secret_is_removed(string $text, string $secret): void
    {
        $clean = (new LogRedactor)->text($text);
        $this->assertStringNotContainsString($secret, $clean);
        $this->assertStringContainsString('REDACTED', $clean);
    }

    public function test_nested_keys_and_exception_arguments_are_not_serialized(): void
    {
        $exception = new \RuntimeException('FTP failed password=short-secret');
        $result = (new LogRedactor)->value(['request' => ['init_data' => 'raw-init', 'headers' => ['Authorization' => 'auth-secret']], 'exception' => $exception, 'model' => (object) ['password' => 'model-secret']]);
        $json = json_encode($result);
        foreach (['short-secret', 'raw-init', 'auth-secret', 'model-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertSame(\RuntimeException::class, $result['exception']['class']);
        $this->assertSame($exception->getLine(), $result['exception']['line']);
        $this->assertArrayNotHasKey('args', $result['exception']['trace'][0] ?? []);
    }

    public function test_custom_webhook_route_masks_token(): void
    {
        config(['telegraph.webhook.url' => '/callbacks/{token}/telegram']);
        $this->assertSame('POST /callbacks/[REDACTED]/telegram', (new LogRedactor)->text('POST /callbacks/short-secret/telegram'));
    }
}

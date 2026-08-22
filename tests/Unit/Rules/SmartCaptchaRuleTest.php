<?php

namespace Tests\Unit\Rules;

use App\Events\Security\CaptchaFailOpen;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Infrastructure\Rules\SmartCaptchaRule;
use Tests\TestCase;

/**
 * Через Validator::make(), не прямым вызовом ->validate() — иначе тест не
 * доказал бы работу $implicit (см. SmartCaptchaRule::$implicit), ради
 * которого правка и делалась: только через реальный валидатор проявляется
 * разница между "правило пропущено, потому что поле пустое" и "правило
 * реально отработало и пропустило".
 */
class SmartCaptchaRuleTest extends TestCase
{
    private function validate(?string $token): \Illuminate\Contracts\Validation\Validator
    {
        $data = $token === null ? [] : ['smart-token' => $token];

        return Validator::make($data, ['smart-token' => [new SmartCaptchaRule]]);
    }

    public function test_disabled_captcha_passes_without_a_network_call(): void
    {
        config(['security.smart_captcha.enabled' => false]);
        Http::fake();

        $this->assertTrue($this->validate(null)->passes());
        Http::assertNothingSent();
    }

    public function test_enabled_with_missing_token_fails(): void
    {
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => 'k']);

        $validator = $this->validate(null);

        $this->assertTrue($validator->fails());
        $this->assertSame(__('account.security.captcha_required'), $validator->errors()->first('smart-token'));
    }

    public function test_enabled_with_empty_token_fails(): void
    {
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => 'k']);

        $this->assertTrue($this->validate('')->fails());
    }

    public function test_enabled_without_server_key_fails_open(): void
    {
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => '']);
        Http::fake();
        Log::shouldReceive('channel')->with('security')->once()->andReturnSelf();
        Log::shouldReceive('warning')->once();

        $this->assertTrue($this->validate('some-token')->passes());
        Http::assertNothingSent();
    }

    public function test_accepted_token_passes(): void
    {
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => 'k']);
        Http::fake([
            'smartcaptcha.cloud.yandex.ru/*' => Http::response(['status' => 'ok']),
        ]);

        $this->assertTrue($this->validate('good-token')->passes());

        Http::assertSent(fn ($request) => $request['secret'] === 'k'
            && $request['token'] === 'good-token'
            && $request->hasHeader('Content-Type'));
    }

    public function test_rejected_token_fails(): void
    {
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => 'k']);
        Http::fake([
            'smartcaptcha.cloud.yandex.ru/*' => Http::response(['status' => 'failed', 'message' => 'bad']),
        ]);

        $validator = $this->validate('bad-token');

        $this->assertTrue($validator->fails());
        $this->assertSame(__('account.security.captcha_failed'), $validator->errors()->first('smart-token'));
    }

    public function test_api_5xx_fails_open(): void
    {
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => 'k']);
        Http::fake([
            'smartcaptcha.cloud.yandex.ru/*' => Http::response('', 500),
        ]);
        Log::shouldReceive('channel')->with('security')->once()->andReturnSelf();
        Log::shouldReceive('warning')->once();

        $this->assertTrue($this->validate('any-token')->passes());
    }

    public function test_connection_exception_fails_open(): void
    {
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => 'k']);
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });
        Log::shouldReceive('channel')->with('security')->once()->andReturnSelf();
        Log::shouldReceive('error')->once();

        $this->assertTrue($this->validate('any-token')->passes());
    }

    /**
     * security.log никто не читает — CaptchaFailOpen делает fail-open заметным
     * в event_logs. Cache::add душит повтор на 5 минут: правило срабатывает на
     * каждую регистрацию, событие в журнале не должно плодиться так же часто.
     */
    public function test_fail_open_without_server_key_dispatches_a_deduped_event(): void
    {
        Event::fake([CaptchaFailOpen::class]);
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => '']);
        Http::fake();

        $this->validate('token-one')->passes();
        $this->validate('token-two')->passes();

        Event::assertDispatchedTimes(CaptchaFailOpen::class, 1);
        Event::assertDispatched(CaptchaFailOpen::class, fn (CaptchaFailOpen $e) => $e->reason === 'no_server_key');
    }

    public function test_fail_open_on_connection_exception_dispatches_event_with_error_message(): void
    {
        Event::fake([CaptchaFailOpen::class]);
        config(['security.smart_captcha.enabled' => true, 'security.smart_captcha.server_key' => 'k']);
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });

        $this->validate('any-token')->passes();

        Event::assertDispatched(CaptchaFailOpen::class, fn (CaptchaFailOpen $e) => $e->reason === 'request_failed'
            && $e->errorMessage === 'timed out');
    }
}

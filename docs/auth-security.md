# Защита форм входа и регистрации

Описывает все механизмы защиты `resources/views/auth/login.blade.php` (`/login`) и
`resources/views/auth/register.blade.php` (`/register`) в этом проекте — от чего защищаемся
и где это в коде. Аутентификацию обслуживает `laravel/fortify`, вьюхи и действия
переопределены в `app/Providers/FortifyServiceProvider.php` и `app/Actions/Fortify/`.

Конфиг всей защиты, описанной ниже (кроме CSRF/сессии — те стоковые Laravel), — в одном
файле, `config/security.php`. Срабатывания честно логируются в отдельный канал
`security` (`config/logging.php`, файл `storage/logs/security.log`, хранится 90 дней) —
без него разбор инцидента задним числом был бы невозможен.

## 1. Общее для обеих форм

**CSRF.** Обе формы шлют `@csrf` (эмитится самим `<x-ui.form>`, `form.blade.php`).
Проверка токена выполняется встроенным Laravel-мидлваром `web`-группы.

**Cookies и сессия.** `config/session.php`, стоковые дефолты:
- `http_only = true` — кука сессии недоступна из JS;
- `same_site = env('SESSION_SAME_SITE', 'lax')`;
- `secure` — управляется `SESSION_SECURE_COOKIE` (HTTPS-only кука на проде);
- `lifetime = 120` минут, драйвер — `database`.

## 2. Форма входа

Маршруты логина регистрирует сам Fortify, вьюха подключена в
`FortifyServiceProvider.php`:

```php
Fortify::loginView(fn () => view('auth.login'));
```

### Rate limiting

Fortify автоматически навешивает `throttle:{limiter}` на POST-маршрут логина, имя лимитера
берёт из `config('fortify.limiters.login')`. Сам лимит объявлен в
`FortifyServiceProvider.php`:

```php
RateLimiter::for('login', function (Request $request) {
    $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

    return Limit::perMinute(5)->by($throttleKey)->response(
        fn (Request $request, array $headers) => back()
            ->withInput($request->except('password'))
            ->withErrors([Fortify::username() => __('auth.throttle', [
                'seconds' => $headers['Retry-After'] ?? 60,
            ])])
    );
});
```

5 попыток в минуту, ключ — транслитерированный email + IP. Поведение при превышении лимита
покрыто тестом `test_throttled_login_returns_the_translated_message_not_a_bare_429`
(`tests/Feature/Auth/AuthenticationTest.php`).

### Пароль

Проверяется через `Hash::check()` внутри Fortify-контроллера логина против bcrypt-хэша.
Требования к паролю — см. «На заметку» ниже, они общие для входа/регистрации/сброса.

### Двухфакторная аутентификация

Код присутствует (trait `Laravel\Fortify\TwoFactorAuthenticatable` на модели `User`,
лимитер `RateLimiter::for('two-factor', ...)` на 5/мин), но **сейчас выключена** —
`Features::twoFactorAuthentication(...)` отсутствует в `config/fortify.php`. Включение —
конфигурационное изменение, не разработка с нуля.

### Honeypot / капча

На логине их нет — осознанно: сама форма входа не создаёт данных и не является
привлекательной целью для автоматической массовой отправки (в отличие от регистрации, где
боты штампуют аккаунты). Rate limit выше уже покрывает подбор пароля.

## 3. Форма регистрации

Обработка — `app/Actions/Fortify/CreateNewUser.php`, подключено через
`Fortify::createUsersUsing(CreateNewUser::class)`.

### Honeypot

`<x-ui.honeypot>` (рендерится внутри `<x-ui.form>` на `register.blade.php`, вне
grid-контейнера с видимыми полями) даёт два скрытых поля:

- поле-приманка, имя из `config('security.honeypot.field')` (`website_url` по умолчанию) —
  если бот его заполнил, форма отклоняется;
- поле-таймер, имя из `config('security.honeypot.timer_field')`
  (`form_loaded_at` по умолчанию) — значение это `Crypt::encryptString(unix-время рендера)`,
  **не голый timestamp**: без шифрования бот подделал бы «прошло достаточно времени» одной
  строкой. Если между рендером и отправкой прошло меньше `min_seconds` — форма отклоняется;
  больше `max_seconds` (протухшая вкладка/реплей, не бот) — тоже отклоняется, но отдельным
  сообщением с советом обновить страницу.

Оба поля скрыты **инлайновым `style`**, не Tailwind-классом и не `sr-only`:
`sr-only` было бы прямо неверным (скринридер поле прочитает и предложит заполнить — обходить
его должны именно боты), а инлайн-стиль не зависит от JIT-purge Tailwind и не ломается, если
`app.css` не загрузился (иначе ловушка стала бы видимым полем и блокировала реальных
пользователей, а не ботов).

Проверка — `Infrastructure\Rules\HoneypotRule`, вешается на атрибут `email` в
`CreateNewUser` (реализует `DataAwareRule`, чтобы читать соседние поля запроса; вешать
ошибку на `website_url`/`form_loaded_at` некуда — там нет `<x-ui.error>`). Все причины
отказа (кроме протухшей формы) дают **одно и то же** сообщение
(`account.security.form_rejected`) — бот не должен понять, какая именно проверка сработала.

Конфиг — `config/security.php` (`honeypot.*`), env: `HONEYPOT_ENABLED`, `HONEYPOT_FIELD`,
`HONEYPOT_TIMER_FIELD`, `HONEYPOT_MIN_SECONDS`, `HONEYPOT_MAX_SECONDS`.

### Rate limiting регистрации и сброса пароля

Fortify регистрирует `register.store`/`password.email`/`password.update` сам и не даёт им
ни ключа лимитера (`config('fortify.limiters')` поддерживает только
`login`/`two-factor`/`passkeys`/`verification`), ни способа переобъявить маршрут без
конфликта имён. Троттлинг подключён через собственное middleware,
`App\Http\Middleware\ThrottleAuthForms` — **наследует `Illuminate\Routing\Middleware\ThrottleRequests`**,
а не переизобретает `RateLimiter::tooManyAttempts()/hit()` заново, поэтому бесплатно
получает `Retry-After`/`X-RateLimit-*` заголовки и `Limit::response()`. Подключено через
`config('fortify.middleware')` — единственное место, где Fortify реально читает этот ключ
(`vendor/laravel/fortify/routes/routes.php`), поэтому middleware отрабатывает ровно на
маршрутах Fortify и ни на одной публичной странице каталога/корзины:

```php
// config/fortify.php
'middleware' => ['web', \App\Http\Middleware\ThrottleAuthForms::class],
```

Middleware само отображает имя маршрута на именованный лимитер и делегирует остальное
`ThrottleRequests::handle()`; GET и немаппленные маршруты (например `/logout`) проходят
насквозь. Лимитеры `register`/`password-reset` объявлены в `FortifyServiceProvider::boot()`
рядом с `login`, каждый возвращает **два** `Limit` — по email-идентичности и по IP — чтобы
один атакующий не обходил лимит сотней разных email с одного хоста.

`verification` (повторная отправка письма подтверждения) — единственный из пяти
лимитеров, который Fortify поддерживает штатно через `config('fortify.limiters.verification')`
(из коробки уже троттлится `6,1`) — назвать его в конфиге понадобилось только затем, чтобы
получить дружелюбный ответ (`session('status') = 'verification-throttled'`,
`verify-email.blade.php`) вместо голого 429.

`POST /user/confirm-password` (`password.confirm.store`) — тоже подбор пароля, риск ниже
(нужна уже авторизованная сессия), но закрыт тем же `ThrottleAuthForms`. Ключ здесь —
`user_id` + IP, не email (на этой форме email вообще не передаётся), ошибка ложится на поле
`password`.

Конфиг — `config/security.php` (`register_throttle.*`, `password_reset_throttle.*`,
`verification_throttle.*`, `password_confirm_throttle.*`), env:
`REGISTER_THROTTLE_PER_IDENTITY`, `REGISTER_THROTTLE_PER_IP`, `REGISTER_THROTTLE_DECAY`,
аналогично для `PASSWORD_RESET_THROTTLE_*`, `VERIFICATION_THROTTLE_*`,
`PASSWORD_CONFIRM_THROTTLE_*`.

### Капча (Yandex SmartCaptcha)

Виджет — `<x-ui.smart-captcha>` (сайткей из `config('security.smart_captcha.client_key')`),
рендерится на `register.blade.php` перед кнопкой отправки. Компонент сам инжектит
`<script src="https://smartcaptcha.cloud.yandex.ru/captcha.js">` — не через `layouts/app.blade.php`
или `layouts/auth.blade.php`: капча нужна ровно одной форме, а в проекте нет ни одного
`@push`/`@stack`, заводить его ради одного скрипта было бы лишним. И как
`<x-ui.telegram-login-button>`, компонент сам себя прячет — ничего не рендерит, пока
`config('security.smart_captcha.enabled')` выключен.

Серверная проверка — `src/Infrastructure/Rules/SmartCaptchaRule.php`, подключена в
валидацию регистрации на поле `smart-token` (имя фиксировано самим виджетом):

```php
'smart-token' => [new SmartCaptchaRule],
```

Правило помечено `public bool $implicit = true` — без этого Laravel по умолчанию пропускает
кастомные правила валидации, когда значение поля пустая строка или поле вовсе отсутствует
(`Validator::presentOrRuleIsImplicit()` проверяет `instanceof ImplicitRule`, к которому это
свойство и приводит через `InvokableValidationRule::make()`), и пустой/непереданный токен
капчи проходил бы валидацию молча.

Конфиг — `config/security.php` (`smart_captcha.*`), env: `SMART_CAPTCHA_ENABLED`,
`SMART_CAPTCHA_CLIENT_KEY`, `SMART_CAPTCHA_SERVER_KEY`, `SMART_CAPTCHA_TIMEOUT`.

**Важно: fail-open, но только на стороне API.** При недоступности/ошибке Yandex API
(таймаут, не-2xx, исключение) или пустом `SMART_CAPTCHA_SERVER_KEY` регистрация **не
блокируется** — правило логирует предупреждение в канал `security` и пропускает запрос
дальше. Решение осознанное: недоступность стороннего сервиса не должна останавливать
регистрацию на сайте.

Это **асимметрично** отказу самого виджета на клиенте: `$implicit = true` делает пустой
токен жёсткой ошибкой валидации, поэтому пользователь, у которого виджет не загрузился
(старый браузер, блокировщик рекламы, заблокированный CDN Яндекса), зарегистрироваться
**не сможет** — здесь никакого fail-open нет. Именно поэтому капча по умолчанию выключена
(`SMART_CAPTCHA_ENABLED=false`) — включать стоит только после того, как в
`storage/logs/security.log` станет видно (по записям `smartcaptcha token missing`), что
виджет реально грузится у пользователей. Риск не гипотетический: browserslist проекта —
`Safari >= 13.1, Chrome >= 80`.

### Валидация полей

`CreateNewUser.php`:

| Поле | Правила | Примечание |
|---|---|---|
| `name` | `required`, `string`, `max:255` | без дополнительных ограничений на кириллицу и т.п. |
| `email` | `required`, `email`, `max:255`, `unique`, `HoneypotRule` | |
| `password` | `Password::default()` + `confirmed` | см. ниже |
| `smart-token` | `SmartCaptchaRule` | no-op, пока капча выключена |

Телефон на регистрации не запрашивается (в отличие от оформления заказа, где есть
`RussianPhoneNumber`).

**Пароль.** `Password::default()` = `Illuminate\Validation\Rules\Password::min(8)` — в
проекте нигде не зарегистрирован кастомный `Password::defaults()`, поэтому действует голый
framework-дефолт: **только минимальная длина 8 символов**, без требований к регистру,
цифрам, спецсимволам или проверки по базам утечек (`uncompromised()`).

## 4. На заметку

- **Fail-open капчи** — сбой Yandex API не блокирует регистрацию (раздел выше), но сбой
  *самого виджета на клиенте* — блокирует (`$implicit`). Мониторить канал `security` на
  предмет частых `smartcaptcha token missing` — это сигнал, что виджет не грузится у
  реальных пользователей, а не только ботов.
- **Нет honeypot/капчи на логине** — осознанно, единственная защита от подбора — rate limit
  5/мин.
- **`Password::default()`** не требует ничего, кроме длины ≥ 8 символов. Известный, осознанно
  оставленный пробел — усиление политики паролей не входило в объём этой работы.
- **2FA реализована, но выключена** — код и миграции есть, включение — конфигурационное
  изменение, не разработка с нуля.

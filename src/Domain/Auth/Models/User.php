<?php

namespace Domain\Auth\Models;

use Database\Factories\UserFactory;
use Domain\Favorite\Models\Favorite;
use Domain\Order\Models\Order;
use Domain\Profile\Models\Address;
use Domain\Profile\Models\Profile;
use Domain\Telegram\Models\TelegramChat;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * MustVerifyEmail и middleware('verified') на /account, /cart, /checkout
     * остаются на месте — отключать верификацию мы не собираемся. Но у
     * аккаунта, созданного через Telegram, email просто нет: подтверждать
     * нечего, а значит и требовать подтверждение не с чего.
     *
     * Обнулить уже существующий email через форму профиля нельзя (см.
     * App\Actions\Fortify\UpdateUserProfileInformation — пустой ввод означает
     * «не трогать поле»), поэтому email === null достижим только регистрацией
     * через Telegram с нуля, и обойти этим верификацию нельзя.
     *
     * Альтернатива «проставить email_verified_at = now()» отвергнута: это
     * запись в БД, утверждающая, что email подтверждён, у пользователя без
     * email — любой будущий whereNotNull('email_verified_at') (рассылка,
     * выгрузка) подхватил бы адресата без адреса.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email === null || parent::hasVerifiedEmail();
    }

    /**
     * event(new Registered($user)) в TelegramLoginController поднимает штатный
     * SendEmailVerificationNotification, а тот дёргает этот метод. Без
     * заглушки уведомление ушло бы на null-адрес — при MAIL_MAILER=smtp это
     * реальное исключение, а не тихий no-op.
     */
    public function sendEmailVerificationNotification(): void
    {
        if ($this->email === null) {
            return;
        }

        parent::sendEmailVerificationNotification();
    }

    /**
     * Привязанный Telegram живёт в telegraph_chats.user_id (Domain\Telegram),
     * не в колонке на users — токен бота и вся телеграм-идентичность
     * принадлежат defstudio/telegraph, users только ссылается на неё.
     */
    public function telegramChat(): HasOne
    {
        return $this->hasOne(TelegramChat::class);
    }

    /**
     * Вызывается только на редких путях (рендер /account/profile, отвязка) —
     * не в hasVerifiedEmail(), который дёргается на каждом запросе за
     * middleware('verified'). Там достаточно free-проверки email === null,
     * лишний запрос на связь ни к чему.
     */
    public function telegramLinked(): bool
    {
        return $this->telegramChat()->exists();
    }

    /**
     * Отвязать Telegram можно только тому, у кого остаётся чем войти.
     * Иначе пользователь без пароля и без email потерял бы единственный
     * способ аутентификации безвозвратно.
     */
    public function canUnlinkTelegram(): bool
    {
        return $this->telegramLinked()
            && $this->email !== null
            && $this->password !== null;
    }

    /**
     * Без этого переопределения Laravel угадывает класс фабрики по
     * умолчанию как `Database\Factories\Domain\Auth\Models\UserFactory` (не
     * существует) — конвенция резолвит только модели под `App\`/`App\Models\`,
     * а `User` при переезде в `Domain\Auth\Models` (см. CLAUDE.md) выпал из
     * неё. `User::factory()` был полностью сломан до этого фикса.
     */
    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Мемоизированный счётчик адресов: `<x-ui.user-menu>` и
     * `<x-ui.account-nav>` рендерятся на одной странице /account/* и оба
     * показывают это число. loadCount() кладёт результат в атрибут
     * `addresses_count` того же инстанса модели, а auth()->user() в рамках
     * запроса всегда один и тот же объект (гвард его мемоизирует) — второй
     * вызов запрос не повторяет. Отдельный менеджер, как у избранного/
     * корзины, тут не нужен: счётчик один и мутаций через него нет (пишет
     * AddressController напрямую).
     */
    public function addressesCount(): int
    {
        if (! array_key_exists('addresses_count', $this->attributes)) {
            $this->loadCount('addresses');
        }

        return (int) $this->addresses_count;
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Мемоизированный счётчик заказов — тот же приём, что addressesCount()
     * (см. её докблок выше): <x-ui.user-menu> и <x-ui.account-nav> оба
     * показывают это число на одной странице /account/*, loadCount() кладёт
     * результат в атрибут orders_count того же инстанса модели, второй
     * вызов в рамках запроса не повторяет SELECT.
     */
    public function ordersCount(): int
    {
        if (! array_key_exists('orders_count', $this->attributes)) {
            $this->loadCount('orders');
        }

        return (int) $this->orders_count;
    }
}

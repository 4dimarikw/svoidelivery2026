<?php

namespace Domain\Profile\Models;

use Database\Factories\Profile\AddressFactory;
use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        // Mass-assignable, чтобы фабрики/тесты могли create() с явным
        // владельцем. Ни один контроллер не валидирует этот ключ через
        // request (см. AddressController::validated()) — запросные пути
        // всегда идут через $user->addresses()->create(), который сам
        // выставляет FK независимо от $fillable, так что дыры
        // mass-assignment здесь нет.
        'user_id',
        'label',
        'city',
        'address',
        'comment',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return AddressFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Только один адрес пользователя может быть адресом по умолчанию —
     * проверяется здесь, а не constraint'ом БД (частичный unique-индекс
     * себя не окупает ради этого случая). Сохранение адреса как
     * дефолтного снимает флаг со всех остальных адресов того же
     * пользователя.
     */
    protected static function booted(): void
    {
        static::saving(function (Address $address) {
            if ($address->is_default) {
                static::where('user_id', $address->user_id)
                    ->when($address->exists, fn ($query) => $query->whereKeyNot($address->getKey()))
                    ->update(['is_default' => false]);
            }
        });
    }
}

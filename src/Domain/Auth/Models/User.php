<?php

namespace Domain\Auth\Models;

use Database\Factories\UserFactory;
use Domain\Favorite\Models\Favorite;
use Domain\Profile\Models\Address;
use Domain\Profile\Models\Profile;
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

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }
}

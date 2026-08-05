<?php

namespace Domain\Profile\Models;

use Database\Factories\Profile\ProfileFactory;
use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'user_profiles';

    protected $fillable = [
        // Mass-assignable so factories/tests can create() with an explicit
        // owner — same rationale as Address::$fillable's user_id (see there).
        // No controller ever request-validates this key (ProfileController
        // uses $user->profile()->updateOrCreate(), which sets the FK itself).
        'user_id',
        'first_name',
        'last_name',
        'patronymic',
        'phone',
        'vk_url',
        'telegram_url',
        'default_order_comment',
    ];

    protected static function newFactory(): Factory
    {
        return ProfileFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * "Фамилия Имя Отчество" — the conventional Russian display order.
     * Null when nothing has been filled in yet.
     */
    public function getFullNameAttribute(): ?string
    {
        $parts = array_filter([$this->last_name, $this->first_name, $this->patronymic]);

        return $parts ? implode(' ', $parts) : null;
    }
}

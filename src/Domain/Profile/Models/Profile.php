<?php

namespace Domain\Profile\Models;

use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory;

    protected $table = 'user_profiles';

    protected $fillable = [
        'first_name',
        'last_name',
        'patronymic',
        'phone',
        'vk_url',
        'telegram_url',
        'default_order_comment',
    ];

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

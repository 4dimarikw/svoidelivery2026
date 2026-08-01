<?php

namespace Domain\Profile\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'city',
        'street',
        'house',
        'apartment',
        'entrance',
        'floor',
        'intercom',
        'comment',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Only one address per user may be the default — enforced here rather
     * than a DB constraint (a partial unique index is more portability
     * trouble than it's worth for this). Saving an address as default
     * unsets it on every other address belonging to the same user.
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

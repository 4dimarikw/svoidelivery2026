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
        // Mass-assignable so factories/tests can create() with an explicit
        // owner. No controller ever request-validates this key (see
        // AddressController::validated()) — the request-driven paths always
        // go through $user->addresses()->create(), which sets the FK itself
        // regardless of $fillable — so this doesn't open a mass-assignment hole.
        'user_id',
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

    protected static function newFactory(): Factory
    {
        return AddressFactory::new();
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

<?php

namespace Domain\Logging\Models;

use Database\Factories\Logging\EventLogFactory;
use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $event_type
 * @property string $level
 * @property string $message
 * @property array|null $context
 * @property int|null $caused_by_user_id
 * @property string|null $caused_by_type
 * @property Carbon $created_at
 * @property-read User|null $causer
 */
class EventLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('EventLog records are immutable.');
        });
    }

    protected $fillable = [
        'event_type',
        'level',
        'message',
        'context',
        'caused_by_user_id',
        'caused_by_type',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caused_by_user_id');
    }

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") —
     * дефолтная конвенция фабрик её не резолвит, та же ловушка, что уже
     * задокументирована в Domain\Auth\Models\User::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return EventLogFactory::new();
    }
}

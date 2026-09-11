<?php

declare(strict_types=1);

namespace Domain\Order\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderDelivery extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['xml', 'ftp_path'];

    protected $casts = [
        'version' => 'integer', 'attempts' => 'integer', 'cycle_attempts' => 'integer',
        'next_attempt_at' => 'datetime', 'locked_until' => 'datetime', 'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $delivery): void {
            if ($delivery->isDirty(['order_id', 'channel', 'version', 'operation_key', 'source', 'xml', 'disk', 'ftp_path'])) {
                throw new LogicException('Содержимое и адрес сохранённой доставки неизменяемы. Создайте новую версию.');
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeDue(Builder $query): void
    {
        $query->where('channel', 'ftp')->where(function (Builder $query): void {
            $query->where(function (Builder $query): void {
                $query->whereIn('state', ['pending', 'retry'])->where('next_attempt_at', '<=', now());
            })->orWhere(function (Builder $query): void {
                $query->where('state', 'processing')->where('locked_until', '<=', now());
            });
        });
    }
}

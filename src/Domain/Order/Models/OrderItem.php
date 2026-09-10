<?php

namespace Domain\Order\Models;

use Closure;
use Database\Factories\Order\OrderItemFactory;
use Domain\Catalog\Models\Product;
use Domain\Order\Actions\CheckedOrderTotal;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Support\Casts\PriceCast;
use Support\Rules\DecimalPrice;
use Support\ValueObjects\Price;
use Throwable;

class OrderItem extends Model
{
    use HasFactory;

    public function save(array $options = [])
    {
        return $this->withLockedOrders(fn () => parent::save($options), true);
    }

    public function delete()
    {
        if (! $this->exists) {
            return parent::delete();
        }

        return $this->withLockedOrders(fn () => parent::delete());
    }

    private function withLockedOrders(Closure $operation, bool $saving = false): mixed
    {
        // Блокировки заказов по ID предшествуют записи позиции и всем пересчётам.
        // Повтор транзакции здесь не нужен: checkout повторяет весь процесс со свежими моделями.
        $state = [$this->attributes, $this->original, $this->changes, $this->relations, $this->exists, $this->wasRecentlyCreated];
        try {
            return $this->getConnection()->transaction(function () use ($operation, $saving) {
                $ids = array_values(array_unique(array_filter([
                    $this->getRawOriginal('order_id'), $this->order_id,
                ])));
                sort($ids, SORT_NUMERIC);
                foreach ($ids as $id) {
                    Order::query()->whereKey($id)->lockForUpdate()->firstOrFail();
                }
                $current = null;
                if ($this->exists) {
                    $current = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
                    if ((int) $current->order_id !== (int) $this->getRawOriginal('order_id')) {
                        throw ValidationException::withMessages(['order_id' => __('order.errors.item_moved')]);
                    }
                }

                // При сохранении устаревшей модели неизменённые поля берём из БД.
                $candidate = array_replace($current?->getAttributes() ?? [], $this->getDirty());
                if ($saving) {
                    Validator::make($candidate, [
                        'price' => ['required', new DecimalPrice(1)],
                        'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
                    ])->validate();
                }
                foreach ($ids as $id) {
                    $rows = static::query()->where('order_id', $id)
                        ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
                        ->orderBy('id')->lockForUpdate()->get();
                    $lines = $rows->map(fn ($row) => ['price' => $row->price, 'quantity' => $row->quantity])->all();
                    if ($saving && (int) $id === (int) $candidate['order_id']) {
                        $lines[] = ['price' => Price::fromMajor($candidate['price']), 'quantity' => (int) $candidate['quantity']];
                    }
                    CheckedOrderTotal::minor($lines);
                }

                return $operation();
            });
        } catch (Throwable $e) {
            [$this->attributes, $this->original, $this->changes, $this->relations, $this->exists, $this->wasRecentlyCreated] = $state;
            $this->classCastCache = [];
            $this->attributeCastCache = [];
            throw $e;
        }
    }

    protected $fillable = [
        'order_id',
        'product_id',
        'price',
        'quantity',
    ];

    protected $casts = [
        'price' => PriceCast::class,
        'quantity' => 'integer',
    ];

    /**
     * Та же формула, что уже есть у CartItem::amount() (src/Domain/Cart/Models/CartItem.php) —
     * снятая price-строка не меняется задним числом, живой Product::price её не касается.
     */
    public function amount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->price?->multiply($this->quantity)
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productTitle(): Attribute
    {
        return Attribute::make(get: fn () => $this->product_brand ?: $this->product_name);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Модель живёт вне `App\Models` (см. CLAUDE.md, "Domain layer") —
     * дефолтная конвенция фабрик её не резолвит, та же ловушка, что уже
     * задокументирована в Domain\Auth\Models\User::newFactory().
     */
    protected static function newFactory(): Factory
    {
        return OrderItemFactory::new();
    }
}

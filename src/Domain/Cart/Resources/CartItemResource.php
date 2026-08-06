<?php

namespace Domain\Cart\Resources;

use Domain\Cart\Models\CartItem;
use Domain\Product\Resources\ProductVariationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'price' => (string) $this->price,
            'amount' => (string) $this->amount,
            'amountValue' => $this->amount->value(),
            'productVariation' => $this->productVariation
                ? ProductVariationResource::make($this->productVariation)->additional(['for' => 'cart'])->resolve()
                : null,
        ];
    }
}

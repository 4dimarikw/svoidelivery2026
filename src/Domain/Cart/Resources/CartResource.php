<?php

namespace Domain\Cart\Resources;

use Domain\Cart\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Support\ValueObjects\Price;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
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
            'id' => $this?->id,
            'storage_id' => $this->storage_id,
            'user_id' => $this->user_id,
            'count' => $this->cartItems->sum(function ($item) {
                return $item->quantity;
            }),
            'amount' => (string) Price::make(
                $this->cartItems->sum(function ($cartItem) {
                    return $cartItem->amount->raw();
                }),
                false
            ),
            //            'cartItems' => CartItemResource::collection($this->whenLoaded('cartItems')),
            //            'user' => $this->whenLoaded('user'),
        ];
    }
}

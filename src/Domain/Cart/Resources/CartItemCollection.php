<?php

namespace Domain\Cart\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class CartItemCollection extends ResourceCollection
{
    public static $wrap = null;

    public $collects = CartItemResource::class;

    public function toArray(Request $request): Collection
    {
        return $this->collection;
    }
}

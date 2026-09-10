<?php

declare(strict_types=1);

namespace Domain\Order\Processes;

use Domain\Order\Actions\CheckedOrderTotal;
use Domain\Order\Contracts\OrderProcessContract;
use Domain\Order\Exceptions\OrderProcessException;
use Domain\Order\Models\Order;
use Illuminate\Validation\ValidationException;

final class TermsOrder implements OrderProcessContract
{
    /**
     * Категории, для которых логистика короба/паллеты требует кратности
     * 12/20 суммарно по всем позициям этих категорий в заказе — тара
     * конкретного товара (банка/бутылка/что угодно) значения не имеет,
     * важна только принадлежность категории. Значения — categories.code,
     * не slug (у «Безалкогольные напитки» они расходятся: code
     * non_alcoholic, slug non-alcoholic).
     */
    private const array MULTIPLE_ORDER_CATEGORY_CODES = ['beer', 'mead', 'cider', 'non_alcoholic'];

    private const array VALID_MULTIPLES = [12, 20];

    /**
     * @throws OrderProcessException
     */
    public function handle(Order $order, $next)
    {
        try {
            $minor = CheckedOrderTotal::minor(cart()->cartItems()->map(
                fn ($item) => ['price' => $item->price, 'quantity' => $item->quantity]
            ));
        } catch (ValidationException) {
            throw new OrderProcessException(__('order.errors.amount_overflow'));
        }
        if ($minor <= 100) {
            throw new OrderProcessException(__('order.errors.min_amount_1'));
        }

        $this->assertPackMultiplicity();

        return $next($order);
    }

    /**
     * Пиво/мёд/сидр/безалкогольное — сумма количества по этим категориям
     * (не по каждой позиции отдельно — позиции внутри заказа могут быть
     * добавлены и по 1 шт) должна быть кратна 12 или 20. Если товаров этих
     * категорий в заказе нет вовсе, правило не применяется.
     *
     * @throws OrderProcessException
     */
    private function assertPackMultiplicity(): void
    {
        $quantity = cart()->cartItems()
            ->loadMissing('product.category')
            ->filter(fn ($item) => in_array($item->product->category?->code, self::MULTIPLE_ORDER_CATEGORY_CODES, true))
            ->sum('quantity');

        if ($quantity === 0) {
            return;
        }

        $isValid = collect(self::VALID_MULTIPLES)->contains(fn ($multiple) => $quantity % $multiple === 0);

        if (! $isValid) {
            throw new OrderProcessException(__('order.errors.pack_multiplicity', ['quantity' => $quantity]));
        }
    }
}

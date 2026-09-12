<?php

namespace App\Http\Controllers;

use App\Events\Order\OrderCreationFailed;
use Domain\Cart\Actions\UpdateCartPrices;
use Domain\Cart\Models\Cart;
use Domain\Order\DTO\CustomerDTO;
use Domain\Order\Exceptions\OrderProcessException;
use Domain\Order\Models\DeliveryType;
use Domain\Order\Models\Order;
use Domain\Order\Models\PaymentMethod;
use Domain\Order\Processes\AssignCustomer;
use Domain\Order\Processes\AssignProducts;
use Domain\Order\Processes\ChangeStateToPending;
use Domain\Order\Processes\CheckProductInStock;
use Domain\Order\Processes\ClearCart;
use Domain\Order\Processes\OrderProcess;
use Domain\Order\Processes\PersistOrder;
use Domain\Order\Processes\TermsOrder;
use Domain\Order\Processes\UpdateProductStockQuantity;
use Domain\Order\Requests\OrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Оформление заказа — сама форма встроена в /cart (CartController::index(),
 * pages/cart.blade.php); этот контроллер отвечает только за POST. История
 * уже оформленных заказов — Account\OrderController.
 */
class OrderController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(OrderRequest $request): RedirectResponse|JsonResponse
    {
        $cartItemsCount = 0;

        try {
            $order = cart()->transaction(function (Cart $cart) use ($request, &$cartItemsCount): Order|array {
                $key = strtolower($request->input('checkout_key'));
                $existing = Order::query()->where('user_id', $request->user()->id)
                    ->where('checkout_key', $key)->first();

                if ($existing !== null) {
                    return $existing;
                }

                if ($cart->checkout_token === null || ! hash_equals($cart->checkout_token, $key)) {
                    throw new OrderProcessException(__('order.errors.checkout_expired'));
                }

                $changes = app(UpdateCartPrices::class)->updateLocked(cart()->cartItems());
                if ($changes !== []) {
                    return $changes;
                }

                $request->validateCustomer();
                $customer = CustomerDTO::fromRequest($request);
                $cartItemsCount = cart()->count();
                $order = new Order([
                    'user_id' => $request->user()->id,
                    'checkout_key' => $key,
                    'delivery_type_id' => DeliveryType::default()->id,
                    'payment_method_id' => PaymentMethod::default()->id,
                    'comment' => $request->input('comment'),
                ]);

                $order = new OrderProcess($order)
                    ->processes([
                        new TermsOrder,
                        new CheckProductInStock,
                        new PersistOrder,
                        new AssignCustomer($customer),
                        new AssignProducts,
                        new ChangeStateToPending,
                        new UpdateProductStockQuantity,
                        new ClearCart,
                    ])
                    ->run();
                $cart->checkout_token = (string) Str::uuid();
                $cart->save();

                return $order;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (OrderProcessException $e) {
            // Отказы по бизнес-правилам записываются в журнал для анализа причин незавершённого оформления.
            event(new OrderCreationFailed(
                userId: $request->user()->id,
                reason: 'business_rejected',
                cartItemsCount: $cartItemsCount,
                errorMessage: $e->getMessage(),
            ));

            // Не per-field ошибка валидации, а общий бизнес-сбой пайплайна
            // (мин. сумма, нет в наличии) — тот же {errors:{field:[msg]}}
            // формат, что uiForm.submit() уже понимает у 422 от FormRequest,
            // просто с искусственным полем 'checkout' вместо реального.
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['checkout' => [$e->getMessage()]]], 422);
            }

            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        } catch (Throwable $e) {
            // Непредвиденный сбой записывается в журнал, после чего исключение передаётся общему обработчику.
            event(new OrderCreationFailed(
                userId: $request->user()->id,
                reason: 'unexpected',
                cartItemsCount: $cartItemsCount,
                errorMessage: $e->getMessage(),
                exceptionClass: $e::class,
            ));

            throw $e;
        }

        if (is_array($order)) {
            // Обновление цен должно быть зафиксировано до возврата отказа.
            $message = __('order.errors.cart_prices_changed', ['changes' => implode('; ', $order)]);
            $redirect = redirect()->route('cart.index')->withErrors(['checkout' => $message])
                ->withInput($request->except('_token'));
            if ($request->wantsJson()) {
                return response()->json([
                    'errors' => ['checkout' => [$message]],
                    'code' => 'cart_prices_changed',
                    'redirect' => route('cart.index'),
                ], 422);
            }

            return $redirect;
        }

        if ($request->wantsJson()) {
            return response()->json([], 200)->header('X-Redirect', route('account.orders.show', $order));
        }

        return redirect()->route('account.orders.show', $order);
    }
}

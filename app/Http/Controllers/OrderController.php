<?php

namespace App\Http\Controllers;

use App\Events\Order\OrderCreationFailed;
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
        $customer = CustomerDTO::fromRequest($request);

        // delivery_type_id/payment_method_id — не выбор клиента: публичное
        // оформление заказа не даёт вариантов, значения фиксированы
        // (config/order.php). Даже если клиент пришлёт свои id в теле
        // запроса, OrderRequest их больше не валидирует и не читает.
        $order = new Order([
            'user_id' => $request->user()->id,
            'delivery_type_id' => DeliveryType::default()->id,
            'payment_method_id' => PaymentMethod::default()->id,
            'comment' => $request->input('comment'),
        ]);

        // До пайплайна — ClearCart в его конце очищает корзину, а PersistOrder
        // может частично изменить состояние ещё до броска исключения.
        $cartItemsCount = cart()->count();

        try {
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
        } catch (OrderProcessException $e) {
            // Отказ по бизнес-правилу (мин. сумма, нет в наличии) — раньше
            // нигде не логировался, хотя это единственная точка, где
            // накапливается статистика отказов оформления.
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
            // Любой другой сбой (дедлок БД, удалённый в корзине товар и т.п.)
            // раньше уходил на общий 500 без единой записи о заказе — событие
            // здесь, исключение пробрасывается дальше как раньше.
            event(new OrderCreationFailed(
                userId: $request->user()->id,
                reason: 'unexpected',
                cartItemsCount: $cartItemsCount,
                errorMessage: $e->getMessage(),
                exceptionClass: $e::class,
            ));

            throw $e;
        }

        if ($request->wantsJson()) {
            return response()->json([], 200)->header('X-Redirect', route('account.orders.show', $order));
        }

        return redirect()->route('account.orders.show', $order);
    }
}

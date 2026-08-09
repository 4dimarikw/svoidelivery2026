<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Оформление заказа — не часть личного кабинета (тот же принцип, что
 * CartController: самостоятельный раздел вне prefix('account')). История
 * уже оформленных заказов — Account\OrderController.
 */
class OrderController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (cart()->count() === 0) {
            return redirect()->route('cart.index');
        }

        $addresses = $request->user()->addresses()->latest()->get();

        // Уже посчитали коллекцию — отдаём то же число в addressesCount()
        // (account-nav.blade.php, user-menu.blade.php), чтобы она не делала
        // свой отдельный count()-запрос по той же таблице.
        $request->user()->setAttribute('addresses_count', $addresses->count());

        // profile передаём явно, а не читаем в шаблоне (auth()->user()->profile) —
        // тот же паттерн, что ProfileController::edit(), иначе шаблон бьёт
        // в БД сам.
        return view('pages.checkout', [
            'cartItems' => cart()->cartItems(),
            'count' => cart()->count(),
            'amount' => cart()->amount(),
            'addresses' => $addresses,
            'profile' => $request->user()->profile,
        ]);
    }

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

        try {
            $order = (new OrderProcess($order))
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
            // Не per-field ошибка валидации, а общий бизнес-сбой пайплайна
            // (мин. сумма, нет в наличии) — тот же {errors:{field:[msg]}}
            // формат, что uiForm.submit() уже понимает у 422 от FormRequest,
            // просто с искусственным полем 'checkout' вместо реального.
            if ($request->wantsJson()) {
                return response()->json(['errors' => ['checkout' => [$e->getMessage()]]], 422);
            }

            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json([], 200)->header('X-Redirect', route('account.orders.show', $order));
        }

        return redirect()->route('account.orders.show', $order);
    }
}

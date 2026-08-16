<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Domain\Order\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.orders.index', [
            'orders' => Order::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->withCount('orderItems')
                ->get(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load([
            'orderCustomer',
            'orderItems.product',
            'deliveryType',
        ]);

        return view('account.orders.show', [
            'order' => $order,
        ]);
    }
}

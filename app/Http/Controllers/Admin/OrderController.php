<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

/** Orders are read-only in the admin: payment is simulated and nothing can be refunded or edited. */
class OrderController extends Controller
{
    public function index(): View
    {
        return view('admin.orders.index', [
            'orders' => Order::withCount('items')->orderByDesc('placed_at')->orderByDesc('id')->paginate(25),
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', ['order' => $order->load('items')]);
    }
}

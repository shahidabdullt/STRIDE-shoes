<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class OrderConfirmationController extends Controller
{
    public function __invoke(Order $order): View
    {
        return view('orders.confirmation', ['order' => $order->load('items')]);
    }
}

<?php

namespace App\Http\Controllers\Seller;

use App\Models\Market;
use App\Models\OrderStatus;
use App\Services\OrderService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class OrdersController extends Controller
{

    private $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    public function getNewOrders(Request $request)
    {
        $orders = $this->orderService->getOrders(1, Auth::user()->id, false, false, false, $request);
        return view('seller.orders.index', compact('orders'));
    }

    public function getOrders(Request $request)
    {
        $orders = $this->orderService->getOrders(0, Auth::user()->id, false, false, false, $request);
        return view('seller.orders.index', compact('orders'));
    }

    public function getOrdersInProgress(Request $request)
    {
        $orders = $this->orderService->getOrders(0, Auth::user()->id, true, false, false, $request);
        return view('seller.orders.index', compact('orders'));
    }

    public function getCompletedOrders(Request $request)
    {
        $orders = $this->orderService->getOrders(0, Auth::user()->id, false, true, false, $request);
        return view('seller.orders.index', compact('orders'));
    }

    public function getFailedOrders(Request $request)
    {
        $orders = $this->orderService->getOrders(0, Auth::user()->id, false, false, true, $request);
        return view('seller.orders.index', compact('orders'));
    }

    public function getOrder($order_id)
    {
        $order = $this->orderService->getOrder($order_id);
        if ($order) {
            $statuses = $this->orderService->getAvailableStatuses($order->status_id, Market::where('id', $order->market_id)->first()->market_code);
            return view('seller.orders.edit', compact('order', 'statuses'));
        } else return redirect('seller/orders/new');
    }

    public function updateOrder(Request $request, $order_id) {
        $res = $this->orderService->updateStatus($order_id, $request['status'], $request['comment'], $request['ttn'],  $request['cancel_reason']);
        if ($res) {
            return redirect()->back()->with(array('message_lang_ref'=> 'common.model_updated'));
        } else {
            return redirect()->back()->with(array('message_lang_ref'=> 'common.model_not_updated', 'type' => 'error'));
        }
    }

    public function editNote(Request $request, $order_id) {
        $this->orderService->editOrderNote($order_id, $request['note']);
        return redirect()->back();
    }

    public function deleteNote($order_id) {
        $this->orderService->deleteOrderNote($order_id);
        return redirect()->back();
    }

}

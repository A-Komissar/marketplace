<?php

namespace App\Http\Controllers\Admin;

use App\Models\Market;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\OrderService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response as FacadeResponse;
use Illuminate\Support\Facades\Storage;

class OrdersController extends Controller
{

    private $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    public function getNewOrders(Request $request)
    {
        $orders = $this->orderService->getOrders(1,null, false, false, false, $request);
        return view('admin.orders.index', compact('orders'));
    }

    public function getOrders(Request $request)
    {
        $orders = $this->orderService->getOrders(0, null, false, false, false, $request);
        return view('admin.orders.index', compact('orders'));
    }

    public function getOrdersInProgress(Request $request)
    {
        $orders = $this->orderService->getOrders(0, null, true, false, false, $request);
        return view('admin.orders.index', compact('orders'));
    }

    public function getCompletedOrders(Request $request)
    {
        $orders = $this->orderService->getOrders(0, null, false, true, false, $request);
        return view('admin.orders.index', compact('orders'));
    }

    public function getFailedOrders(Request $request)
    {
        $orders = $this->orderService->getOrders(0, null, false, false, true, $request);
        return view('admin.orders.index', compact('orders'));
    }

    public function getOrder($order_id)
    {
        $order = $this->orderService->getOrder($order_id);
        if ($order) {
            $statuses = $this->orderService->getAvailableStatuses($order->status_id, Market::where('id', $order->market_id)->first()->market_code);
            return view('admin.orders.edit', compact('order', 'statuses'));
        } else return redirect('admin/orders/new');
    }

    public function updateOrder(Request $request, $order_id) {
        $res = $this->orderService->updateStatus($order_id, $request['status'], $request['comment'], $request['ttn'], $request['cancel_reason']);
        if ($res) {
            return redirect()->back()->with(array('message_lang_ref'=> 'common.model_updated'));
        } else {
            return redirect()->back()->with(array('message_lang_ref'=> 'common.model_not_updated', 'type' => 'error'));
        }
    }

    public function create()
    {
        $markets = Market::all();
        return view('admin.orders.create', compact('markets'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'market' => 'required',
            'order_id' => 'required',
        ]);
        $order = $this->orderService->createOrder($request);
        if ($order) {
            return redirect()->route('admin.orders.get', ['order_id' => $order->id]);
        } else {
            return redirect()->back()->with(array('message_lang_ref'=> 'common.model_not_updated', 'type' => 'error'));
        }
    }

    public function deleteOrder($order_id)
    {
        $deleted = $this->orderService->deleteOrder($order_id);
        if ($deleted) {
            return redirect()->route('admin.orders.all')->with(array('message_lang_ref'=> 'common.model_deleted'));
        } else {
            return redirect()->route('admin.orders.all')->with(array('message_lang_ref'=> 'common.model_not_deleted', 'type' => 'error'));
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

    public function exportToExcel(Request $request) {
        $start = $request['start_date'];
        $end = $request['end_date'];
        $filepath = $this->orderService->exportToExcel($start, $end);
        $path = storage_path($filepath);
        if (!File::exists($path)) {
            abort(404);
        }
        $file = File::get($path);
        $type = File::mimeType($path);
        $response = FacadeResponse::make($file, 200);
        $response->header("Content-Type", $type);
        return $response;
    }

}

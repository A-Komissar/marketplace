<?php

namespace App\Http\Controllers\Seller;

use App\Services\LogService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class LogController extends Controller
{

    private $logService;

    public function __construct()
    {
        $this->logService = new LogService();
    }

    public function getProductsLog(Request $request)
    {
        $items = $this->logService->getProductsLog(Auth::user()->id, $request);
        return view('seller.logs.products.index', compact('items'));
    }

    public function rollbackProducts(Request $request) {
        $this->logService->rollbackProducts(json_decode($request['items']));
        $json = array();
        $json['status'] = 'changed';
        return json_encode($json);
    }

}

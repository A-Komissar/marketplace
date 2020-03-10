<?php

namespace App\Http\Controllers\Seller;

use App\Models\Seller;
use App\Models\TransactionHistory;
use App\Services\FinanceService;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;

class FinanceController extends Controller
{
    private $financeService;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->financeService = new FinanceService();
    }

    public function getBill(Request $request)
    {
        $seller = Seller::select('id', 'name', 'balance')->where('id', Auth::user()->id)->first();
        $transactions = $this->financeService->getTransactionHistory(Auth::user()->id, $request);
        $statistics = $this->financeService->getTransactionStatistics(Auth::user()->id, $request);
        return view('seller.finance.bill', compact('transactions', 'seller', 'statistics'));
    }

    public function getRefill(Request $request)
    {
        return redirect('/seller/home');
    }

}

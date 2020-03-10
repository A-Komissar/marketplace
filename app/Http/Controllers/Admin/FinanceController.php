<?php

namespace App\Http\Controllers\Admin;

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

    public function index(Request $request)
    {
        $query = Seller::query();
        $query->where('approved', 1);
        if($request['name']) {
            $query->where('name', 'LIKE', $request['name'].'%');
        }
        if($request['company']) {
            $query->where('company_name', 'LIKE', $request['company'].'%');
        }
        $query->orderBy('name', 'asc');
        $sellers = $query->paginate(50)->appends(Input::except('page'));
        $statistics = new \stdClass();
        $statistics->debet = Seller::where('balance', '>', 0)->sum('balance');
        $statistics->credit = 0 - Seller::where('balance', '<', 0)->sum('balance');
        $statistics->sum = Seller::sum('balance');
        return view('admin.finance.index', compact('sellers', 'statistics'));
    }

    public function edit(Request $request, $seller_id)
    {
        $seller = Seller::select('id', 'name', 'balance')->where('id', $seller_id)->first();
        if ($seller) {
            $transactions = $this->financeService->getTransactionHistory($seller_id, $request);
            $statistics = $this->financeService->getTransactionStatistics($seller_id, $request);
            return view('admin.finance.edit', compact('transactions', 'seller', 'statistics'));
        } else {
            return abort(404);
        }
    }

    public function update(Request $request, $seller_id)
    {
        $this->validate($request, [
            'transaction_value'    => 'required|numeric',
            'transaction_description'    => 'required',
        ]);
        $this->financeService->updateBalance($seller_id, Auth::user()->id, $request['transaction_value'], $request['transaction_description']);
        return redirect('admin/finance/'.$seller_id);
    }

}

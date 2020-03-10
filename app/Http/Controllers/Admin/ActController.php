<?php

namespace App\Http\Controllers\Admin;

use App\Models\Commission;
use App\Models\ImportProducts;
use App\Models\OrderProduct;
use App\Models\SellerExtra;
use App\Models\TransactionHistory;
use App\Services\ActService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Input;

use App\Models\Seller;
use App\Models\SellerSettings;
use App\Mail\RegistrationEmail;
use App\Services\MessageService;

class ActController extends Controller
{

    private $actService;

    public function __construct()
    {
        $this->actService = new ActService();
    }

    public function createActs(Request $request) {
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
        return view('admin.acts.new', compact('sellers'));
    }

    public function getActs(Request $request) {
        $query = Seller::with('acts')->has('acts');
        if ($request['email_sent'] == 'false') {
            $query->whereHas('acts', function ($q) {
                $q->where('is_email_sent', false);
            });
            // $acts = $this->actService->getActs(null, false);
        } else {
            $query->whereHas('acts', function ($q) {
                $q->where('is_email_sent', true);
            });
            // $acts = $this->actService->getActs();
        }
        $sellers = $query->get();
        return view('admin.acts.list', compact('sellers'));
    }

    public function storeActs(Request $request) {
        $is_monthly_act = $request['monthly_act'] ? true : false;
        if ($request['items'] == 'all') {
            $sellers = Seller::all();
            $items = array();
            foreach ($sellers as $seller) {
                array_push($items, $seller->id);
            }
            $this->actService->createActs($items, $request['start_date'], $request['end_date'], $is_monthly_act);
        } else {
            $this->actService->createActs(json_decode($request['items']), $request['start_date'], $request['end_date'], $is_monthly_act);
        }
        $json = array();
        $json['status'] = 'created';
        return json_encode($json);
    }

    public function deleteAct($act_id) {
        $this->actService->deleteAct($act_id);
        return response()->json(['success' => 'success'], 200);
        // return redirect()->back()->withInput(['tab' => 'act']);
    }

    public function deleteActs(Request $request) {
        $this->actService->deleteActs(json_decode($request['items']));
        $json = array();
        $json['status'] = 'deleted';
        return json_encode($json);
    }

    public function sendActs(Request $request) {
        $this->actService->sendActs(json_decode($request['items']));
        $json = array();
        $json['status'] = 'sent';
        return json_encode($json);
    }

}

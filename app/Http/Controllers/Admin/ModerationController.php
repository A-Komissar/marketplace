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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Input;

use App\Models\Seller;
use App\Models\SellerSettings;
use App\Mail\RegistrationEmail;
use App\Services\MessageService;

class ModerationController extends Controller
{

    public function getRegistrationRequests(Request $request)
    {
        $query = Seller::query();
        $query->where('approved', 0)->where('declined', '<>', 1);
        if($request['name']) {
            $query->where('name', 'LIKE', $request['name'].'%');
        }
        if($request['company']) {
            $query->where('company_name', 'LIKE', $request['company'].'%');
        }
        $query->orderBy('created_at', 'desc');
        $registrationRequests = $query->paginate(50)->appends(Input::except('page'));
        return view('admin.moderation.registration_requests.index', compact('registrationRequests'));
    }

    public function editRegistrationRequest($seller_id)
    {
        $registrationRequest = Seller::where('id', $seller_id)->first();
        if ($registrationRequest) {
            return view('admin.moderation.registration_requests.edit', compact('registrationRequest'));
        } else {
            return abort(404);
        }
    }

    public function verificationRegistrationRequest(Request $request)
    {
        try{
            $this->validate($request, [
                'id'    => 'required',
                'declined'=> 'required',
            ]);
            $seller = Seller::where('id',$request['id'])->first();
            $obj = new \stdClass();
            $obj->receiver = $seller->name;
            if($request['declined']) {
                $seller->declined = 1;
                if($request['declined_description']) {
                    $obj->description = $request['declined_description'];
                    if (Config::get('app.env') == 'production') {
                        Mail::to($seller->email)->send(new RegistrationEmail($obj, false));
                    }
                }
            } else {
                $seller->approved = 1;
                if (Config::get('app.env') == 'production') {
                    Mail::to($seller->email)->send(new RegistrationEmail($obj, true));
                }
            }
            $seller->save();
        } catch (\Exception $e) {
        }
        return redirect('admin/moderation/requests');
    }

    public function getChangeInfoRequests(Request $request)
    {
        $query = Seller::query();
        $query->whereIn('id', function($query) {
            $query->select('seller_id')->from((new SellerSettings)->getTable());
        });
        if($request['name']) {
            $query->where('name', 'LIKE', $request['name'].'%');
        }
        if($request['company']) {
            $query->where('company_name', 'LIKE', $request['company'].'%');
        }
        $query->orderBy('created_at', 'desc');
        $sellers = $query->paginate(50)->appends(Input::except('page'));
        return view('admin.moderation.change_info_requests.index', compact('sellers'));
    }

    public function editChangeInfoRequest($seller_id)
    {
        $seller = Seller::where('id', $seller_id)->first();
        $new = SellerSettings::where('seller_id', $seller_id)->first();
        if ($seller && $new) {
            return view('admin.moderation.change_info_requests.edit', compact('new', 'seller'));
        } else {
            return abort(404);
        }
    }

    public function verificationChangeInfoRequest(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'declined' => 'required',
        ]);
        $new = SellerSettings::where('seller_id', $request['id'])->first();
        if (!$request['declined']) {
            $seller = Seller::where('id',$request['id'])->first();
            if($new->name) $seller->name =  $new->name;
            if($new->email) $seller->email = $new->email;
            if($new->phone) $seller->phone = $new->phone;
            if($new->company_name) $seller->company_name = $new->company_name;
            if($new->website_link) $seller->website_link = $new->website_link;
            if($new->legal_address) $seller->legal_address = $new->legal_address;
            if($new->post_address) $seller->post_address = $new->post_address;
            if($new->checking_account) $seller->checking_account = $new->checking_account;
            if($new->telephone_fax) $seller->telephone_fax = $new->telephone_fax;
            if($new->bank_code) $seller->bank_code = $new->bank_code;
            if($new->legal_code) $seller->legal_code = $new->legal_code;
            $seller->save();
        }
        $new->delete();
        return redirect('admin/moderation/change-info');
    }

    public function getSellers($query, Request $request)
    {
        if($request['name']) {
            $query->where('name', 'LIKE', $request['name'].'%');
        }
        if($request['company']) {
            $query->where('company_name', 'LIKE', $request['company'].'%');
        }
        $query->orderBy('name', 'asc');
        $sellers = $query->paginate(50)->appends(Input::except('page'));
        return view('admin.moderation.sellers.index', compact('sellers'));
    }

    public function getDeclinedSellers(Request $request)
    {
        $query = Seller::query();
        $query->where('approved', 0)->where('declined', 1);
        return $this->getSellers($query, $request);
    }

    public function getHiddenSellers(Request $request)
    {
        $query = Seller::query();
        $query->where('is_hidden', 1);
        return $this->getSellers($query, $request);
    }

    public function getAcceptedSellers(Request $request)
    {
        $query = Seller::query();
        $query->where('approved', 1)->where('is_hidden', 0);
        return $this->getSellers($query, $request);
    }

    public function editSeller($seller_id)
    {
        $seller = Seller::where('id', $seller_id)->first();
        if ($seller) {
            $extra = SellerExtra::where('seller_id', $seller_id)->first();
            if (!$extra) {
                $extra = new SellerExtra();
                $extra->seller_id = $seller_id;
                $extra->save();
            }
            $commissions = Commission::with('market', 'category')
                ->where('seller_id', $seller_id)->orderBy('value', 'asc')->get();
            $acts = (new ActService())->getActs($seller_id);
            return view('admin.moderation.sellers.edit', compact('seller', 'extra', 'commissions', 'acts'));
        } else {
            return abort(404);
        }
    }

    public function deleteSeller(Request $request, $seller_id)
    {
        $seller = Seller::where('id', $seller_id)->first();
        $type = ($seller->approved) ? 'approved' : 'declined';
        if ($seller && ($request['confirm'] == 'Впевнений' || $request['confirm'] ==  'Уверен')) {
            $seller->delete();
            $messageService = new MessageService();
            $messageService->deleteAllMessagesWithSeller($seller_id);

            $productService = new ProductService();
            $productService->deleteAllProductsWithSeller($seller_id);

            (new ActService())->deleteAllActsWithSeller($seller_id);

            ImportProducts::where('seller_id', $seller_id)->delete();
            OrderProduct::where('seller_id', $seller_id)->delete();

            SellerSettings::where('seller_id', $seller_id)->delete();
            TransactionHistory::where('seller_id', $seller_id)->delete();
            return redirect('admin/moderation/sellers/'.$type);
        } else {
            return redirect()->back()->with(array('message_lang_ref'=> 'admin.seller_not_deleted', 'message_type' => 'error'));
        }
    }

    public function updateSeller(Request $request, $seller_id)
    {
        $seller = Seller::where('id', $seller_id)->first();
        if($request['name']) $seller->name = $request['name'];
        if($request['email'] && $request['email'] != $seller->email) {
            $this->validate($request, [
                'email' => 'email|max:255|unique:sellers',
            ]);
            $seller->email = $request['email'];
        }
        if($request['phone']) $seller->phone = $request['phone'];
        if($request['company_name']) $seller->company_name = $request['company_name'];
        if($request['website_link']) $seller->website_link = $request['website_link'];
        if($request['legal_address']) $seller->legal_address = $request['legal_address'];
        if($request['post_address']) $seller->post_address = $request['post_address'];
        if($request['checking_account']) $seller->checking_account = $request['checking_account'];
        if($request['telephone_fax']) $seller->telephone_fax = $request['telephone_fax'];
        if($request['bank_code']) $seller->bank_code = $request['bank_code'];
        if($request['legal_code']) $seller->legal_code = $request['legal_code'];
        if($request['balance_remind_sum']) $seller->balance_remind_sum = $request['balance_remind_sum'];

        $seller->approved = $request['approved'] ?: false;
        $seller->is_hidden = $request['is_hidden'] ?: false;
        $seller->big = $request['big'] ?: false;
        $seller->use_prefix = $request['use_prefix'] ?: false;
        $seller->add_article_to_name = $request['add_article_to_name'] ?: false;

        $seller->save();

        $extra = SellerExtra::where('seller_id', $seller_id)->first();
        if ($request['contract_number']) $extra->contract_number = $request['contract_number'];
        if ($request['contract_date']) $extra->contract_date = $request['contract_date'];

        if ($request['legal_name_short']) $extra->legal_name_short = $request['legal_name_short'];
        if ($request['legal_name_long']) $extra->legal_name_long = $request['legal_name_long'];
        if ($request['legal_code_text']) $extra->legal_code_text = $request['legal_code_text'];
        if ($request['legal_info_text']) $extra->legal_info_text = $request['legal_info_text'];
        if ($request['act_signature_name']) $extra->act_signature_name = $request['act_signature_name'];
        if ($request['act_signature_decoding']) $extra->act_signature_decoding = $request['act_signature_decoding'];

        if ($request['accountant_name']) $extra->accountant_name = $request['accountant_name'];
        if ($request['accountant_email']) $extra->accountant_email = $request['accountant_email'];
        if ($request['accountant_phone']) $extra->accountant_phone = $request['accountant_phone'];
        if ($request['accountant_viber']) $extra->accountant_viber = $request['accountant_viber'];
        if ($request['accountant_telegram']) $extra->accountant_telegram = $request['accountant_telegram'];
        if ($request['manager_name']) $extra->manager_name = $request['manager_name'];
        if ($request['manager_email']) $extra->manager_email = $request['manager_email'];
        if ($request['manager_phone']) $extra->manager_phone = $request['manager_phone'];
        if ($request['manager_viber']) $extra->manager_viber = $request['manager_viber'];
        if ($request['manager_telegram']) $extra->manager_telegram = $request['manager_telegram'];
        if ($request['warehouse_name']) $extra->warehouse_name = $request['warehouse_name'];
        if ($request['warehouse_email']) $extra->warehouse_email = $request['warehouse_email'];
        if ($request['warehouse_phone']) $extra->warehouse_phone = $request['warehouse_phone'];
        if ($request['warehouse_viber']) $extra->warehouse_viber = $request['warehouse_viber'];
        if ($request['warehouse_telegram']) $extra->warehouse_telegram = $request['warehouse_telegram'];
        if ($request['warehouse_address']) $extra->warehouse_address = $request['warehouse_address'];
        if ($request['np_address']) $extra->np_address = $request['np_address'];
        if ($request['pickup_address']) $extra->pickup_address = $request['pickup_address'];
        if ($request['own_post_service']) $extra->own_post_service = $request['own_post_service'];
        if ($request['shipping_price']) $extra->shipping_price = $request['shipping_price'];
        if ($request['shipping_today']) $extra->shipping_today = $request['shipping_today'];
        if ($request['ownership_type']) $extra->ownership_type = $request['ownership_type'];
        if ($request['funds_accepting']) $extra->funds_accepting = $request['funds_accepting'];
        if ($request['schedule']) $extra->schedule = $request['schedule'];
        if ($request['working_hours_weekdays']) $extra->working_hours_weekdays = $request['working_hours_weekdays'];
        if ($request['working_hours_weekends']) $extra->working_hours_weekends = $request['working_hours_weekends'];
        $extra->save();

        return redirect()->back()->with(array('message_lang_ref'=> 'common.model_updated'));
    }

    public function findSeller(Request $request) {
        $seller = null;
        if ($request['id']) {
            $seller = Seller::where('id', $request['id'])->get();
        }
        if ($seller) {
           return $seller;
        } else {
            $limit = $request['limit'] == 'all' ? null : ((int) $request['limit'] > 0 ? (int) $request['limit'] : 5);
            $query = Seller::query();
            $query->where('name', 'LIKE', '%'.$request['pattern'].'%');
            if ($limit) $query->limit($limit);
            return $query->get();
        }
    }

    public function loginAsSeller($seller_id) {
        if (get_class(Auth::user()) == 'App\Models\Admin') {
            Auth::guard('seller')->loginUsingId($seller_id);
            return redirect()->route('seller.home');
        } else {
            return abort(401, 'Unauthorized!');
        }
    }

}

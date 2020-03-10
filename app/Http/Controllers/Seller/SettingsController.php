<?php

namespace App\Http\Controllers\Seller;

use App\Mail\SellerChangeInfoEmailToAdmin;
use App\Models\Seller;
use App\Models\SellerExtraEmail;
use App\Models\SellerSettings;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    public function editAccount()
    {
        $seller = Seller::with('emails')->where('id', Auth::user()->id)->first();
        $new = SellerSettings::where('seller_id', Auth::user()->id)->first();
        return view('seller.settings.edit', compact('seller', 'new'));
    }

    public function updateAccount(Request $request)
    {
        $seller = Seller::where('id', Auth::user()->id)->first();
        $settings = SellerSettings::where('seller_id', Auth::user()->id)->first();
        if(!$settings) {
            $settings = new SellerSettings;
            $settings->seller_id = Auth::user()->id;
        }
        $is_updated = false;
        if($request['name'] && $seller->name != $request['name']) {
            $settings->name = $request['name'];
            $is_updated = true;
        }
        $email = $settings && $settings->email ? $settings->email : $seller->email;
        if($request['email'] && $email != $request['email']) {
            if($request['email'] != $seller->email) {
                $this->validate($request, [
                    'email' => 'email|max:255|unique:sellers',
                ]);
            }
            $settings->email = $request['email'];
            $is_updated = true;
        }
        if($request['phone'] && $seller->phone != $request['phone']) {
            $settings->phone = $request['phone'];
            $is_updated = true;
        }
        if($request['company_name'] && $seller->company_name != $request['company_name']) {
            $settings->company_name = $request['company_name'];
            $is_updated = true;
        }
        if($request['website_link'] && $seller->website_link != $request['website_link']) {
            $settings->website_link = $request['website_link'];
            $is_updated = true;
        }
        if($request['legal_address'] && $seller->legal_address != $request['legal_address']) {
            $settings->legal_address = $request['legal_address'];
            $is_updated = true;
        }
        if($request['post_address'] && $seller->post_address != $request['post_address']) {
            $settings->post_address = $request['post_address'];
            $is_updated = true;
        }
        if($request['checking_account'] && $seller->checking_account != $request['checking_account']) {
            $settings->checking_account = $request['checking_account'];
            $is_updated = true;
        }
        if($request['telephone_fax'] && $seller->telephone_fax != $request['telephone_fax']) {
            $settings->telephone_fax = $request['telephone_fax'];
            $is_updated = true;
        }
        if($request['bank_code'] && $seller->bank_code != $request['bank_code']) {
            $settings->bank_code = $request['bank_code'];
            $is_updated = true;
        }
        if($request['legal_code'] && $seller->legal_code != $request['legal_code']) {
            $settings->legal_code = $request['legal_code'];
            $is_updated = true;
        }
        if ($is_updated) {
            $settings->save();
            try {
                if (Config::get('app.env') == 'production') {
                    $receiver = config('mail.admin_notification_email');
                    Mail::to($receiver)->send(new SellerChangeInfoEmailToAdmin($seller));
                }
            } catch (\Exception $e) { }
        }

        if($request['password']) {
            $this->validate($request, [
                'password'    => 'min:6|confirmed',
            ]);
            $seller->password = bcrypt($request['password']);
            $seller->save();
        }

        if($request['balance_remind_sum'] && $seller->balance_remind_sum != $request['balance_remind_sum']) {
            $seller->balance_remind_sum = $request['balance_remind_sum'];
            $seller->save();
        }

        return redirect('seller/settings/')->with(array('message_lang_ref'=> 'common.model_updated'));
    }

    public function deleteAccount()
    {
        $seller = Seller::where('id', Auth::user()->id)->first();

        // fake delete
        $seller->approved = false;
        $seller->declined = true;
        $seller->save();

        return redirect('seller/logout');
    }

    public function editEmails(Request $request) {
        $old_emails = SellerExtraEmail::where('seller_id', Auth::user()->id)->get()->keyBy('id');
        if($request['extra_email']) {
            foreach ($request['extra_email'] as $email) {
                if (!$email) continue;
                $old = $old_emails->where('email', $email['email'])->first();
                if ($old) {
                    if ($old->name != $email['name']) {
                        $old->name = $email['name'];
                        $old->save();
                    }
                    $old_emails->forget($old->id);
                } else {
                    $new = new SellerExtraEmail;
                    $new->seller_id = Auth::user()->id;
                    $new->email = $email['email'];
                    $new->name = $email['name'];
                    $new->save();
                }
            }
        }
        foreach ($old_emails as $deleted) {
            $deleted->delete();
        }
        return redirect()->back()->with(array('message_lang_ref'=> 'common.model_updated'));
    }

}

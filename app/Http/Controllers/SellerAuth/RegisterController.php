<?php

namespace App\Http\Controllers\SellerAuth;

use App\Mail\SellerRegistrationEmailToAdmin;
use App\Models\Seller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Validator;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/welcome';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('seller.guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:sellers',
            'password' => 'required|min:6|confirmed',
            'g-recaptcha-response' => 'required|recaptcha',
            'phone' => 'required', // 'required|numeric|digits_between:10,13',
            'company_name' => 'required|max:255',
            'website_link' => 'required|max:255',
            //'legal_address' => 'required|max:255',
            //'post_address' => 'required|max:255',
            //'checking_account' => 'required|max:255',
            //'telephone_fax' => 'required|max:255',
            //'bank_code' => 'required|max:255',
            //'legal_code' => 'required|max:255',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return Seller
     */
    protected function create(array $data)
    {
        while (true) {
            try {
                // generate sellers prefix
                $str = "";
                $letters = array_merge(range('A','Z'));
                $digits = array_merge(range('0','9'));
                $max_letters = count($letters) - 1;
                $max_digits = count($digits) - 1;
                for ($i = 0; $i < 2; $i++) {
                    $rand = mt_rand(0, $max_letters);
                    $str .= $letters[$rand];
                }
                for ($i = 0; $i < 2; $i++) {
                    $rand = mt_rand(0, $max_digits);
                    $str .= $digits[$rand];
                }
                // create seller
                $seller = Seller::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?: '',
                    'company_name' => $data['company_name'] ?: '',
                    'website_link' => $data['website_link'] ?: '',
                    'legal_address' => $data['legal_address'] ?: '',
                    'post_address' => $data['post_address'] ?: '',
                    'checking_account' => $data['checking_account'] ?: '',
                    'telephone_fax' => $data['telephone_fax'] ?: '',
                    'legal_code' => $data['legal_code'] ?: '',
                    'bank_code' => $data['bank_code'] ?: '',
                    'password' => bcrypt($data['password']),
                    'prefix' => $str,
                ]);
                try {
                    if (Config::get('app.env') == 'production') {
                        $receiver = config('mail.admin_notification_email');
                        Mail::to($receiver)->send(new SellerRegistrationEmailToAdmin($seller));
                    }
                } catch (\Exception $e) { }
                return $seller;
            } catch (\Exception $e) {}
        }
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showRegistrationForm()
    {
        return view('seller.auth.register');
    }

    /**
     * Get the guard to be used during registration.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard('seller');
    }

    public function register(Request $request)
    {
        $this->validator($request->all())->validate();

        event(new Registered($user = $this->create($request->all())));

        // $this->guard()->login($user);

        return $this->registered($request, $user)
            ?: redirect($this->redirectPath())->with(array('message_lang_ref'=> 'auth.after_register_message'));
    }
}

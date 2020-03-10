<?php

namespace App\Models;

use App\Notifications\SellerResetPassword;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Seller extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'prefix', 'name', 'email', 'password', 'approved', 'phone', 'company_name',
        'website_link', 'legal_address', 'post_address', 'checking_account', 'telephone_fax',
        'bank_code', 'legal_code', 'balance', 'balance_remind_sum', 'last_balance_remind_time',
        'add_article_to_name', 'approved', 'is_hidden', 'declined', 'big'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new SellerResetPassword($token));
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function extra()
    {
        return $this->hasOne(SellerExtra::class);
    }

    public function acts()
    {
        return $this->hasMany(Act::class);
    }

    public function emails()
    {
        return $this->hasMany(SellerExtraEmail::class);
    }
}


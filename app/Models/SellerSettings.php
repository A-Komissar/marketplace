<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerSettings extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'seller_settings';

    /**
     * @var array
     */
    protected $fillable = ['id', 'seller_id',  'name', 'email', 'phone', 'company_name',
        'website_link', 'legal_address', 'post_address', 'checking_account', 'telephone_fax',
        'bank_code', 'legal_code', 'updated_at'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    public function seller()
    {
        return $this->belongsTo('App\Models\Seller', 'seller_id')->select(array('id', 'name', 'email'));
    }
}

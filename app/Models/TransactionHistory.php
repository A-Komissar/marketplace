<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionHistory extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transaction_history';

    /**
     * @var array
     */
    protected $fillable = ['id', 'seller_id', 'admin_id', 'description', 'transaction_value', 'balance_before', 'balance_after', 'created_at'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    public function seller()
    {
        return $this->belongsTo('App\Models\Seller', 'seller_id')->select(array('id', 'name', 'company_name', 'balance'));
    }

    public function admin()
    {
        return $this->belongsTo('App\Models\Admin', 'admin_id')->select(array('id', 'name'));
    }
}

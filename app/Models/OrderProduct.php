<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders_products';

    // protected $fillable = ['id', 'seller_id'];

    public function seller()
    {
        return $this->belongsTo('App\Models\Seller', 'seller_id')->select(array('id', 'name', 'email', 'prefix'));
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id');
    }

    public function order()
    {
        return $this->belongsTo('App\Models\Order', 'order_id');
    }

    public $timestamps = false;

}

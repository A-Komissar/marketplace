<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders';

    // protected $fillable = ['id', 'market_id'];

    public function market()
    {
        return $this->belongsTo('App\Models\Market', 'market_id')->select(array('id', 'market_code', 'market_name'));
    }

    public function status()
    {
        return $this->belongsTo('App\Models\OrderStatus', 'status_id');
    }

    public function products()
    {
        return $this->hasMany('App\Models\OrderProduct', 'order_id', 'id');
    }

    public $timestamps = false;

}

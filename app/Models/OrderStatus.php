<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatus extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_status';

    //protected $fillable = ['id', 'market_id'];

    public function market()
    {
        return $this->belongsTo('App\Models\Market', 'market_id')->select(array('id', 'market_code', 'market_name'));
    }

    public $timestamps = false;

}

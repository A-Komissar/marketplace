<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kit extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kits';

    // protected $fillable = ['id', 'kit_id'];

    public function market()
    {
        return $this->belongsTo('App\Models\Market', 'market_id');
    }

    public function seller()
    {
        return $this->belongsTo('App\Models\Seller', 'seller_id');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id');
    }

    public function items()
    {
        return $this->hasMany('App\Models\KitItem', 'kit_id', 'id');
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

}

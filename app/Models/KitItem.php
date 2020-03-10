<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitItem extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kit_items';

    // protected $fillable = ['id', 'kit_id'];

    public function kit()
    {
        return $this->belongsTo('App\Models\Kit', 'kit_id');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id');
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

}

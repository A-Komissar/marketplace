<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductChange extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_changes';

    // protected $fillable = ['id', 'product_id', 'type', 'key', 'changed_at'];

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id');
    }

    public $timestamps = false;

}

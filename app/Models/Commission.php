<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'commissions';

    protected $fillable = ['id', 'market_id', 'category_id', 'value'];

    public function category()
    {
        return $this->belongsTo('App\Models\Category', 'category_id')->select(array('category_id', 'parent_id', 'market_id', 'name'));
    }

    public function market()
    {
        return $this->belongsTo('App\Models\Market', 'market_id')->select(array('id', 'market_code', 'market_name'));
    }

    public $timestamps = false;

}

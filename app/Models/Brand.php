<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'm_brands';

    protected $fillable = ['id', 'market_id', 'code', 'name'];

    public function market()
    {
        return $this->belongsTo('App\Models\Market', 'market_id')->select(array('id', 'market_code', 'market_name'));
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;
}

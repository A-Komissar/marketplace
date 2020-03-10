<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Characteristic extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'm_characteristics';

    // protected $fillable = ['id', 'market_id'];

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

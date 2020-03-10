<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Market extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'markets';

    protected $fillable = ['id', 'market_code', 'market_name'];

    public function getIDByCode($code)
    {
        return $this->select(array('id'))->where('market_code', $code)->first()->id;
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

}

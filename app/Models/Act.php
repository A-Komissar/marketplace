<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Act extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'acts';

    // protected $fillable = ['id', 'seller_id'];

    public function seller()
    {
        return $this->belongsTo('App\Models\Seller', 'seller_id')->select(array('id', 'name', 'company_name', 'email', 'legal_address', 'post_address'));
    }

}

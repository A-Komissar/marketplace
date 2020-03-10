<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'messages';

    /**
     * @var array
     */
    protected $fillable = ['id', 'seller_id', 'admin_id', 'message', 'created_at', 'written_by_admin', 'new'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    public function seller()
    {
        return $this->belongsTo('App\Models\Seller', 'seller_id')->select(array('id', 'name', 'company_name'));
    }

    public function admin()
    {
        return $this->belongsTo('App\Models\Admin', 'admin_id')->select(array('id', 'name'));
    }

}

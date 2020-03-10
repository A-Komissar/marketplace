<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chats';

    // protected $fillable = ['id', 'chat_id'];

    public function market()
    {
        return $this->belongsTo('App\Models\Market', 'market_id');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client', 'client_id');
    }

    public function order()
    {
        return $this->belongsTo('App\Models\Order', 'order_id');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id');
    }

    public function messages()
    {
        return $this->hasMany('App\Models\ChatMessage', 'chat_id', 'id');
    }

}

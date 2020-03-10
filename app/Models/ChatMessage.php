<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chat_messages';

    // protected $fillable = ['id', 'chat_id'];

    public function chat()
    {
        return $this->belongsTo('App\Models\Chat', 'chat_id');
    }

}

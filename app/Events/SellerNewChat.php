<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SellerNewChat implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $chats_num;
    private $seller_id;

    public function __construct($seller_id, $chats_num)
    {
        $this->seller_id = $seller_id;
        $this->chats_num = $chats_num;
    }

    public function broadcastOn()
    {
        $newMessage = 'seller.'.$this->seller_id.'.chat.new';
        return new Channel($newMessage);
    }
}

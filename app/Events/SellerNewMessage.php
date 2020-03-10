<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SellerNewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $new_messages_num;
    public $sender;
    public $message;
    private $seller_id;

    public function __construct($seller_id, $new_messages_num, $sender, $message)
    {
        $this->seller_id = $seller_id;
        $this->new_messages_num = $new_messages_num;
        $this->sender = $sender;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        $newMessage = 'seller.'.$this->seller_id.'.message.new';
        return new Channel($newMessage);
    }
}

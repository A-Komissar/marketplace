<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SellerNewOrder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $new_orders_num;
    private $seller_id;

    public function __construct($seller_id, $new_orders_num)
    {
        $this->seller_id = $seller_id;
        $this->new_orders_num = $new_orders_num;
    }

    public function broadcastOn()
    {
        $newMessage = 'seller.'.$this->seller_id.'.order.new';
        return new Channel($newMessage);
    }
}

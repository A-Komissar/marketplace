<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AdminNewOrder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $new_orders_num;
    private $admin_id;

    public function __construct($admin_id, $new_orders_num)
    {
        $this->admin_id = $admin_id;
        $this->new_orders_num = $new_orders_num;
    }

    public function broadcastOn()
    {
        $newMessage = 'admin.'.$this->admin_id.'.order.new';
        return new Channel($newMessage);
    }
}

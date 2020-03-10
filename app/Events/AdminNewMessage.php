<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class AdminNewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public $new_messages_num;
    public $sender;
    public $message;
    private $admin_id;

    public function __construct($admin_id, $new_messages_num, $sender, $message)
    {
        $this->admin_id = $admin_id;
        $this->new_messages_num = $new_messages_num;
        $this->sender = $sender;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        $newMessage = 'admin.'.$this->admin_id.'.message.new';
        return new Channel($newMessage);
    }
}

<?php

namespace App\Events;

use App\Models\Admin;
use App\Models\Message;
use App\Models\Seller;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $admin;
    public $seller;

    public function __construct(Message $message, Admin $admin, Seller $seller)
    {
        $this->message = $message;
        $this->admin = $admin;
        $this->seller = $seller;
    }

    public function broadcastOn()
    {
        $chat = 'chat.'.$this->message->admin_id.'.'.$this->message->seller_id;
        return new Channel($chat);
        // return new PrivateChannel($chat); // TODO: change to private
    }
}

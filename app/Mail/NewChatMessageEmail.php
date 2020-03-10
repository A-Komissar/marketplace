<?php

namespace App\Mail;

use App\Models\Chat;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;

class NewChatMessageEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $receiver;
    public $message;
    public $chat;
    public $user;

    public function __construct($receiver, $message)
    {
        $this->receiver = $receiver;
        $this->message = $message;
        $this->chat = Chat::where('id', $message->chat_id)->first();
        $this->user = Client::where('id', $this->chat->client_id)->first();
    }

    public function build()
    {
        return $this->subject('Нове повідомлення від покупця')
            ->markdown('mails.new_chat_message');
    }
}

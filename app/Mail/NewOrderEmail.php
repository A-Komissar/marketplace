<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;

class NewOrderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $contact_email;
    public $receiver;

    public function __construct($name, $data)
    {
        $this->data = $data;
        $this->receiver = $name;
        $this->contact_email = Config::get('mail.contact_email');
    }

    public function build()
    {
        return $this->subject('Нове замовлення на NonStop')
            ->markdown('mails.new_order');
    }
}

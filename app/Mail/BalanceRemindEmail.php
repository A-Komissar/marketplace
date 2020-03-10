<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;

class BalanceRemindEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $contact_email;
    public $receiver;

    public function __construct($name)
    {
        $this->receiver = $name;
        $this->contact_email = Config::get('mail.contact_email');
    }

    public function build()
    {
        return $this->subject('Недостатньо коштів на рахунку')
            ->markdown('mails.balance_remind');
    }
}

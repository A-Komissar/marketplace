<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;

class RegistrationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $mail;
    public $accepted;
    public $contact_email;

    public function __construct($mail, $accepted = false)
    {
        $this->mail = $mail;
        $this->accepted = $accepted;
        $this->contact_email = Config::get('mail.contact_email');
    }

    public function build()
    {
        if(!$this->accepted) {
            return $this->subject('Відмова реєстрації на NonStop')
                ->markdown('mails.registration_declined');
        } else {
            return $this->subject('Підтвердження реєстрації на NonStop')
                ->markdown('mails.registration_accepted');
        }
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ActEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $act;
    public $receiver;
    protected $file;

    public function __construct($name, $act)
    {
        $this->act = $act;
        $this->receiver = $name;
        $this->file = storage_path('app/sellers/'.$act->seller_id.'/acts/'.$act->file);
    }

    public function build()
    {
        return $this->subject('Акт-звіт про продаж товарів')
            ->markdown('mails.act_email')
            ->attach($this->file);
    }
}

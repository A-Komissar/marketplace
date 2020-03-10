<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

class ProductsChangesEmail extends Mailable
{
    use Queueable, SerializesModels;

    protected $file;
    protected $from;

    public function __construct($filepath, $from)
    {
        $this->file = storage_path($filepath);
        $this->from = $from;
    }

    public function build()
    {
        return $this->subject('Изменения в данных товаров')
            ->from($this->from, 'D`n`D Group')
            ->markdown('mails.products_changes_email')
            ->attach($this->file);
    }
}

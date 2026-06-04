<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentApprovalNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $message;
    protected $url;

    // Data apa saja yang mau dikirim ke lonceng?
    public function __construct($title, $message, $url)
    {
        $this->title = $title;
        $this->message = $message;
        $this->url = $url;
    }

    // Kita pilih jalur pengiriman via 'database' (muncul di web)
    public function via($notifiable)
    {
        return ['database'];
    }

    // Susunan data yang akan disimpan ke tabel notifications
    public function toArray($notifiable)
    {
        return [
            'title'   => $this->title,
            'message' => $this->message,
            'url'     => $this->url,
        ];
    }
}

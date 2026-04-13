<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminBroadcastMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $broadcastTitle,
        public readonly string $broadcastMessage,
        public readonly string $notificationType,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[CoinQuest] '.$this->broadcastTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-broadcast',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InternalNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $protocol,
        public readonly string $event,
        public readonly string $status,
        public readonly string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            ),
            subject: "SIGME | Atualização {$this->protocol}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.internal-notification');
    }

    /** @return array<int, mixed> */
    public function attachments(): array
    {
        return [];
    }
}

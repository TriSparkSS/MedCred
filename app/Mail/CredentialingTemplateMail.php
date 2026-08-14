<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class CredentialingTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $mailBody,
        public ?string $messageId = null,
        public ?string $inReplyTo = null,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            from: new Address(
                $this->fromAddress ?? config('mail.from.address'),
                $this->fromName ?? config('mail.from.name')
            ),
        );
    }

    public function headers(): Headers
    {
        $text = [];

        if ($this->inReplyTo) {
            $text['In-Reply-To'] = $this->inReplyTo;
            $text['References'] = $this->inReplyTo;
        }

        return new Headers(
            messageId: $this->messageId ? trim($this->messageId, '<>') : null,
            text: $text,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: nl2br(e($this->mailBody)),
        );
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CredentialingTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $mailBody,
        public string $fromAddress,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            from: new \Illuminate\Mail\Mailables\Address(
                $this->fromAddress,
                config('credentialing.mailbox.from_name')
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: nl2br(e($this->mailBody)),
        );
    }
}

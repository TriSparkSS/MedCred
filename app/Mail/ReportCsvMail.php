<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportCsvMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $reportName,
        public string $csvContents,
        public string $csvFilename,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cred App Report: '.$this->reportName,
            from: new Address(
                $this->fromAddress ?? config('mail.from.address'),
                $this->fromName ?? config('mail.from.name')
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Please find the attached credentialing report: <strong>'
                .e($this->reportName)
                .'</strong>.</p><p>Generated from Revantage Cred App on '
                .now()->format('M j, Y g:i A')
                .'.</p>',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->csvContents, $this->csvFilename)
                ->withMime('text/csv'),
        ];
    }
}

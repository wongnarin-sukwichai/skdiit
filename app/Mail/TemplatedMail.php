<?php

namespace App\Mail;

use App\Support\SiteContent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A workflow email (EmailTemplate) or an announcement: Thai text first, then English, and a button to the system.
 * Sent through the configured mailer: the log until SMTP is set up, then real email with no other change.
 */
class TemplatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyTh,
        public ?string $bodyEn = null,
        public ?string $buttonUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: SiteContent::all()['conference']['shortName'].' – '.$this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.templated', with: ['shortName' => SiteContent::all()['conference']['shortName']]);
    }
}

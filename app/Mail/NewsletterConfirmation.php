<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $confirmUrl,
    ) {
        $this->replyTo('online@takeovercreatives.com');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your newsletter subscription',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter-confirmation',
        );
    }
}

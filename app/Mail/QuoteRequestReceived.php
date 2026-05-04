<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly QuoteRequest $quote,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New quote request — ' . $this->quote->ref_number,
        );
    }

    public function content(): Content
    {
        $adminUrl = rtrim(config('app.url', 'https://api.okelcor.de'), '/');

        return new Content(
            view: 'emails.quote-request-received',
            with: [
                'quote'    => $this->quote,
                'adminUrl' => $adminUrl,
            ],
        );
    }
}

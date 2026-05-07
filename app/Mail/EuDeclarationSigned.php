<?php

namespace App\Mail;

use App\Models\EuDeclaration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EuDeclarationSigned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EuDeclaration $declaration,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Entry certificate submitted — ' . $this->declaration->order_ref,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.eu-declaration-signed',
            text: 'emails.eu-declaration-signed-text',
            with: [
                'declaration' => $this->declaration,
            ],
        );
    }
}

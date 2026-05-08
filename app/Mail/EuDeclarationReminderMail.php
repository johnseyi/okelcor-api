<?php

namespace App\Mail;

use App\Models\EuDeclaration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EuDeclarationReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EuDeclaration $declaration,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action required: EU entry certificate pending — ' . $this->declaration->order_ref,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.eu-declaration-reminder',
            text: 'emails.eu-declaration-reminder-text',
            with: [
                'declaration' => $this->declaration,
            ],
        );
    }
}

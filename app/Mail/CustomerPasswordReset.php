<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly string $resetUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your Okelcor password');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-reset-password',
            with: [
                'customer' => $this->customer,
                'resetUrl' => $this->resetUrl,
            ],
        );
    }
}

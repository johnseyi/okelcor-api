<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerEmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly string $verificationUrl
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verify your Okelcor account');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-verify-email',
            with: [
                'customer'        => $this->customer,
                'verificationUrl' => $this->verificationUrl,
            ],
        );
    }
}

<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WixCustomerWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly string $temporaryPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to Okelcor — Set Your Password');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wix-customer-welcome',
            with: [
                'customer'          => $this->customer,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl'          => rtrim(config('app.frontend_url', 'https://okelcor.com'), '/') . '/login',
            ],
        );
    }
}

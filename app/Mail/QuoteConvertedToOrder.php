<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteConvertedToOrder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly QuoteRequest $quote,
        public readonly ?string $checkoutUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'support@okelcor.com'),
                config('mail.from.name', 'Okelcor'),
            ),
            subject: 'Okelcor payment instructions — ' . $this->order->ref,
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'https://okelcor.com'), '/');

        return new Content(
            html: 'emails.quote-converted-to-order',
            text: 'emails.quote-converted-to-order-text',
            with: [
                'order'       => $this->order,
                'quote'       => $this->quote,
                'ordersUrl'   => $frontendUrl . '/account/orders/' . $this->order->ref,
                'checkoutUrl' => $this->checkoutUrl,
            ],
        );
    }
}

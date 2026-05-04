<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteConvertedToOrder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly QuoteRequest $quote,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your quote has been converted to an order — ' . $this->order->ref,
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'https://okelcor.com'), '/');

        return new Content(
            view: 'emails.quote-converted-to-order',
            with: [
                'order'      => $this->order,
                'quote'      => $this->quote,
                'ordersUrl'  => $frontendUrl . '/account/orders/' . $this->order->ref,
            ],
        );
    }
}

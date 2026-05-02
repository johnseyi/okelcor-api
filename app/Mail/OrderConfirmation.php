<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly ?Invoice $invoice = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Order Confirmation — ' . $this->order->ref,
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'https://okelcor.com'), '/');

        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'order'       => $this->order,
                'invoice'     => $this->invoice,
                'trackingUrl' => $frontendUrl . '/account/orders/' . $this->order->ref,
                'invoicesUrl' => $frontendUrl . '/account/invoices',
            ],
        );
    }
}

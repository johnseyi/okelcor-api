<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Order — ' . $this->order->ref . ' (' . $this->order->customer_name . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-received',
            with: [
                'order'       => $this->order,
                'trackingUrl' => rtrim(env('FRONTEND_URL', 'https://okelcor.com'), '/')
                                 . '/account/orders/' . $this->order->ref,
            ],
        );
    }
}

<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
            from: new Address(
                config('mail.from.address', 'support@okelcor.com'),
                config('mail.from.name', 'Okelcor'),
            ),
            replyTo: [
                new Address($this->order->customer_email, $this->order->customer_name),
            ],
            subject: 'New order — ' . $this->order->ref . ' (' . $this->order->customer_name . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-received',
            with: [
                'order'       => $this->order,
                'trackingUrl' => rtrim(config('app.frontend_url', 'https://okelcor.com'), '/')
                                 . '/account/orders/' . $this->order->ref,
            ],
        );
    }
}

<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
        $isBankTransferPending = $this->order->payment_method === 'bank_transfer'
            && $this->order->payment_status !== 'paid';

        $subject = $isBankTransferPending
            ? 'Okelcor payment instructions — ' . $this->order->ref
            : 'Okelcor order confirmation — ' . $this->order->ref;

        return new Envelope(
            from: new Address(
                config('mail.from.address', 'support@okelcor.com'),
                config('mail.from.name', 'Okelcor'),
            ),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'https://okelcor.com'), '/');

        return new Content(
            html: 'emails.order-confirmation',
            text: 'emails.order-confirmation-text',
            with: [
                'order'       => $this->order,
                'invoice'     => $this->invoice,
                'trackingUrl' => $frontendUrl . '/account/orders/' . $this->order->ref,
                'invoicesUrl' => $frontendUrl . '/account/invoices',
            ],
        );
    }
}

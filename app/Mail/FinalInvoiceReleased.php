<?php

namespace App\Mail;

use App\Models\EuDeclaration;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FinalInvoiceReleased extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly EuDeclaration $declaration,
        public readonly Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.from.address', 'support@okelcor.com'),
                config('mail.from.name', 'Okelcor'),
            ),
            subject: 'Your final invoice is ready — ' . $this->declaration->order_ref,
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'https://okelcor.com'), '/');

        return new Content(
            html: 'emails.final-invoice-released',
            text: 'emails.final-invoice-released-text',
            with: [
                'declaration' => $this->declaration,
                'invoice'     => $this->invoice,
                'invoicesUrl' => $frontendUrl . '/account/invoices',
                'downloadUrl' => route('invoices.download', $this->invoice->id),
            ],
        );
    }
}

<?php

namespace App\Mail;

use App\Models\AdminUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AdminUser $admin,
        public readonly string $temporaryPassword
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Okelcor Admin Account & Login Credentials');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-welcome',
            with: [
                'admin'             => $this->admin,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl'          => rtrim(config('app.frontend_url', 'https://okelcor.com'), '/') . '/admin',
            ],
        );
    }
}

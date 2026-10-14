<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The 6-digit password the system generated, with a link back to the login page (config/registration.php).
 * Sent in Thai and English, since the registrant's language is not stored.
 * kind: 'registered' (new account) | 'reset' (asked for a new one, or admin sent one)
 */
class LoginPassword extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $password,
        public string $kind = 'registered',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->kind === 'registered'
                ? 'SKDIIT 2027 – รหัสผ่านเข้าสู่ระบบ / Your sign-in password'
                : 'SKDIIT 2027 – รหัสผ่านใหม่ / Your new password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.login-password',
            with: ['loginUrl' => url('/login').'?'.http_build_query(['email' => $this->user->email])],
        );
    }
}

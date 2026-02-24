<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KYCApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'KYC Verification Successful - SwayRider',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.riders.kyc_approved',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

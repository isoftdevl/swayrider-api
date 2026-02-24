<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KYCRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $reason;

    public function __construct($name, $reason)
    {
        $this->name = $name;
        $this->reason = $reason;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'KYC Verification Update - SwayRider',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.riders.kyc_rejected',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

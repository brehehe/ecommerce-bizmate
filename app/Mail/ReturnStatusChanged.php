<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly \App\Models\ReturnRequest $returnRequest,
        public readonly string $storeName,
        public readonly ?string $storeLogo = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Status Retur Diperbarui (#{$this->returnRequest->return_number}) — {$this->storeName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.returns.status_changed',
            with: [
                'returnRequest' => $this->returnRequest,
                'storeName' => $this->storeName,
                'storeLogo' => $this->storeLogo,
                'appUrl' => config('app.url'),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

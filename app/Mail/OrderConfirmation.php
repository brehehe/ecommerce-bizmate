<?php

namespace App\Mail;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Transaction  $transaction  The order transaction with items, user, address, paymentMethod loaded
     * @param  string  $storeName  Store name from settings
     * @param  string|null  $storeLogo  Store logo path from settings
     */
    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $storeName,
        public readonly ?string $storeLogo = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Pesanan #{$this->transaction->transaction_number} Berhasil Dibuat — {$this->storeName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'transaction' => $this->transaction,
                'storeName' => $this->storeName,
                'storeLogo' => $this->storeLogo,
                'appUrl' => config('app.url'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

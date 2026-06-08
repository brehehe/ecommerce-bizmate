<?php

namespace App\Mail;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DigitalProductDelivered extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly Transaction $transaction,
        public readonly TransactionItem $item,
        public readonly string $storeName,
        public readonly ?string $storeLogo = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Informasi Pengiriman Produk Digital #{$this->transaction->transaction_number} — {$this->item->product_name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.digital-product-delivered',
            with: [
                'transaction' => $this->transaction,
                'item' => $this->item,
                'storeName' => $this->storeName,
                'storeLogo' => $this->storeLogo,
                'appUrl' => config('app.url'),
            ],
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

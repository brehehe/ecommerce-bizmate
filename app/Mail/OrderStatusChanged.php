<?php

namespace App\Mail;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $storeName,
        public readonly ?string $storeLogo = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $statusLabels = [
            'belum_bayar' => 'Belum Bayar',
            'menunggu' => 'Menunggu Konfirmasi',
            'diproses' => 'Sedang Diproses',
            'dikemas' => 'Sedang Dikemas',
            'out_for_pickup' => 'Out for Pickup',
            'dikirim' => 'Sedang Dikirim',
            'selesai' => 'Selesai',
            'batal' => 'Dibatalkan',
        ];

        if ($this->transaction->shipping_courier === 'self_pickup' && $this->transaction->status === 'out_for_pickup') {
            $statusText = 'Siap Diambil di Toko';
            $subject = "Pesanan Siap Diambil di Toko — #{$this->transaction->transaction_number}";
        } else {
            $statusText = $statusLabels[$this->transaction->status] ?? ucfirst($this->transaction->status);
            $subject = "Update Status Pesanan #{$this->transaction->transaction_number} — {$statusText}";
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-status-changed',
            with: [
                'transaction' => $this->transaction,
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

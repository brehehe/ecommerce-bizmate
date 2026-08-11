<?php

namespace App\Http\Controllers\Storefront;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Submit a review for a specific product in a transaction.
     * Only allowed when status is selesai.
     */
    public function store(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($transaction->status !== 'selesai') {
            return redirect()->back()->with('error', 'Anda hanya bisa memberikan ulasan untuk pesanan yang telah selesai.');
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'is_anonymous' => 'nullable|boolean',
            'files' => 'nullable|array',
            'files.*' => [
                'required',
                'file',
                'mimes:jpeg,png,jpg,gif,svg,mp4,mov,avi,webp',
                function ($attribute, $value, $fail) {
                    $isImage = str_starts_with($value->getMimeType(), 'image/');
                    if ($isImage && $value->getSize() > 2048 * 1024) {
                        $fail('Ukuran file gambar ulasan maksimal 2MB.');
                    } elseif (! $isImage && $value->getSize() > 20480 * 1024) {
                        $fail('Ukuran file video ulasan maksimal 20MB.');
                    }
                },
            ],
        ]);

        // Check if already reviewed
        $exists = ProductReview::where('user_id', $request->user()->id)
            ->where('transaction_id', $transaction->id)
            ->where('product_id', $validated['product_id'])
            ->where('product_variant_id', $validated['product_variant_id'] ?? null)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'Anda sudah memberikan ulasan untuk produk ini.');
        }

        $mediaPaths = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $isImage = str_starts_with($file->getMimeType(), 'image/');
                if ($isImage) {
                    $path = ImageHelper::compressAndStore($file, 'reviews', 'public');
                } else {
                    $path = $file->store('reviews', 'public');
                }
                $mediaPaths[] = '/storage/'.$path;
            }
        }

        ProductReview::create([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
            'product_variant_id' => $validated['product_variant_id'] ?? null,
            'transaction_id' => $transaction->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'media' => ! empty($mediaPaths) ? $mediaPaths : null,
            'is_anonymous' => (bool) ($validated['is_anonymous'] ?? false),
        ]);

        return redirect()->back()->with('success', 'Terima kasih atas ulasan Anda!');
    }

    /**
     * Report a product review.
     */
    public function report(Request $request, ProductReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($review->is_reported) {
            return redirect()->back()->with('error', 'Ulasan ini sudah pernah dilaporkan.');
        }

        $review->update([
            'is_reported' => true,
            'report_reason' => $validated['reason'],
            'reported_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Ulasan berhasil dilaporkan. Tim kami akan meninjaunya.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\RefundRequest;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DummyReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding dummy refund requests...');
        $this->seedRefunds();

        $this->command->info('Seeding dummy returns...');
        $this->seedReturns();

        $this->command->info('Seeding dummy abandoned carts...');
        $this->seedAbandonedCarts();

        $this->command->info('Seeding dummy voucher usage...');
        $this->seedVoucherUsage();

        $this->command->info('Done seeding report dummy data.');
    }

    // ─────────────────────────────────────────────────────────────
    // REFUNDS
    // ─────────────────────────────────────────────────────────────

    private function seedRefunds(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Admin']))->first();

        // Completed (selesai) transactions that don't yet have a refund request
        $transactions = Transaction::where('status', 'selesai')
            ->whereNotIn('id', RefundRequest::pluck('transaction_id'))
            ->with(['user'])
            ->take(30)
            ->get();

        if ($transactions->isEmpty()) {
            $this->command->warn('No completed transactions found for refunds.');

            return;
        }

        $refundMethods = ['transfer_bank', 'saldo', 'transfer_bank', 'transfer_bank'];
        $reasons = [
            'Produk tidak sesuai dengan deskripsi di website',
            'Produk rusak saat diterima',
            'Salah ukuran / warna yang dikirimkan',
            'Produk tidak sampai dalam waktu yang dijanjikan',
            'Ingin membatalkan pesanan yang sudah dibayar',
            'Produk cacat dan tidak bisa digunakan',
        ];
        $statuses = ['menunggu_konfirmasi', 'diproses', 'selesai', 'ditolak', 'selesai', 'selesai'];
        $bankNames = ['BCA', 'Mandiri', 'BNI', 'BRI', 'CIMB Niaga'];

        foreach ($transactions as $tx) {
            $status = $statuses[array_rand($statuses)];
            $method = $refundMethods[array_rand($refundMethods)];
            $createdAt = Carbon::parse($tx->created_at)->addDays(rand(1, 5));

            $refundAmount = (float) $tx->grand_total * (rand(50, 100) / 100);

            $refund = new RefundRequest;
            $refund->id = (string) Str::uuid();
            $refund->refund_number = 'RFN-'.$createdAt->format('Ymd').'-'.strtoupper(Str::random(5));
            $refund->transaction_id = $tx->id;
            $refund->user_id = $tx->user_id;
            $refund->status = $status;
            $refund->refund_method = $method;
            $refund->reason = $reasons[array_rand($reasons)];
            $refund->refund_amount = $refundAmount;

            if ($method === 'transfer_bank') {
                $refund->bank_name = $bankNames[array_rand($bankNames)];
                $refund->account_number = '0'.rand(1000000000, 9999999999);
                $refund->account_name = $tx->user?->name ?? 'Pelanggan';
            }

            if (in_array($status, ['diproses', 'selesai'])) {
                $refund->processed_by = $admin?->id;
                $refund->processed_at = $createdAt->copy()->addHours(rand(2, 24));
                $refund->notes_admin = 'Refund diproses sesuai kebijakan toko';
            }

            if ($status === 'selesai') {
                $refund->refunded_at = $createdAt->copy()->addDays(rand(1, 3));
            }

            $refund->created_at = $createdAt;
            $refund->updated_at = $createdAt;
            $refund->save();
        }

        $this->command->info('  Created '.$transactions->count().' refund requests.');
    }

    // ─────────────────────────────────────────────────────────────
    // RETURNS
    // ─────────────────────────────────────────────────────────────

    private function seedReturns(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Admin']))->first();

        // Use completed transactions that don't have returns yet
        $transactions = Transaction::where('status', 'selesai')
            ->whereNotIn('id', ReturnRequest::pluck('transaction_id'))
            ->with(['items', 'user'])
            ->take(25)
            ->get();

        if ($transactions->isEmpty()) {
            $this->command->warn('No transactions available for returns.');

            return;
        }

        $returnTypes = ['refund', 'replacement', 'refund', 'replacement', 'refund'];
        $returnStatuses = ['menunggu_review', 'disetujui', 'diterima', 'selesai', 'ditolak', 'selesai'];
        $reasons = [
            'Barang rusak saat pengiriman',
            'Produk tidak sesuai pesanan',
            'Produk cacat / tidak berfungsi',
            'Ukuran/warna tidak sesuai',
            'Produk berbeda dari foto',
            'Barang tidak lengkap/kekurangan',
        ];
        $couriers = ['JNE', 'J&T Express', 'SiCepat', 'AnterAja', 'Pos Indonesia'];

        $created = 0;
        foreach ($transactions as $tx) {
            if ($tx->items->isEmpty()) {
                continue;
            }

            $status = $returnStatuses[array_rand($returnStatuses)];
            $type = $returnTypes[array_rand($returnTypes)];
            $createdAt = Carbon::parse($tx->created_at)->addDays(rand(2, 10));

            $returnModel = new ReturnRequest;
            $returnModel->id = (string) Str::uuid();
            $returnModel->return_number = 'RTN-'.$createdAt->format('Ymd').'-'.strtoupper(Str::random(5));
            $returnModel->transaction_id = $tx->id;
            $returnModel->user_id = $tx->user_id;
            $returnModel->status = $status;
            $returnModel->type = $type;
            $returnModel->reason = $reasons[array_rand($reasons)];

            if (in_array($status, ['disetujui', 'diterima', 'selesai'])) {
                $returnModel->approved_by = $admin?->id;
                $returnModel->approved_at = $createdAt->copy()->addHours(rand(4, 48));
                $returnModel->notes_admin = 'Return disetujui, mohon kirim produk ke alamat toko.';
                $returnModel->return_tracking_number = 'RT'.rand(10000000, 99999999);
                $returnModel->return_courier_name = $couriers[array_rand($couriers)];
            }

            if (in_array($status, ['diterima', 'selesai'])) {
                $returnModel->received_by = $admin?->id;
                $returnModel->received_at = $createdAt->copy()->addDays(rand(2, 5));
            }

            if ($status === 'selesai' && $type === 'refund') {
                $returnModel->refund_amount = (float) $tx->grand_total * (rand(70, 100) / 100);
                $returnModel->refunded_at = $createdAt->copy()->addDays(rand(3, 7));
            }

            if ($status === 'selesai' && $type === 'replacement') {
                $returnModel->replacement_tracking_number = 'REP'.rand(10000000, 99999999);
                $returnModel->replacement_courier_name = $couriers[array_rand($couriers)];
            }

            $returnModel->created_at = $createdAt;
            $returnModel->updated_at = $createdAt;
            $returnModel->save();

            // Return Items — pick 1 item from the transaction
            $item = $tx->items->random();
            $qtyReturned = min($item->quantity, rand(1, 2));
            $refundSub = (float) $item->harga_akhir * $qtyReturned;

            ReturnItem::create([
                'id' => (string) Str::uuid(),
                'return_id' => $returnModel->id,
                'transaction_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'quantity_returned' => $qtyReturned,
                'unit_price' => $item->harga_akhir,
                'refund_subtotal' => $refundSub,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $created++;
        }

        $this->command->info("  Created {$created} returns.");
    }

    // ─────────────────────────────────────────────────────────────
    // ABANDONED CARTS
    // ─────────────────────────────────────────────────────────────

    private function seedAbandonedCarts(): void
    {
        // Only keep cart items for users who have NO recent transaction in last 3 days
        $customers = User::role('Customer')->take(20)->get();
        $products = Product::where('active', true)->with(['variants'])->take(30)->get();

        if ($products->isEmpty() || $customers->isEmpty()) {
            $this->command->warn('No products or customers for cart seeding.');

            return;
        }

        $created = 0;
        foreach ($customers as $customer) {
            // Skip if user already has cart items
            if (CartItem::where('user_id', $customer->id)->exists()) {
                continue;
            }

            $cartCount = rand(1, 4);
            $selectedProducts = $products->random(min($cartCount, $products->count()));
            $addedAt = Carbon::now()->subDays(rand(1, 14))->subHours(rand(0, 23));

            foreach ($selectedProducts as $product) {
                $variant = $product->variants->isNotEmpty()
                    ? $product->variants->random()
                    : null;

                CartItem::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $customer->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => rand(1, 3),
                    'is_checked' => (bool) rand(0, 1),
                    'created_at' => $addedAt,
                    'updated_at' => $addedAt,
                ]);

                $created++;
            }
        }

        $this->command->info("  Created {$created} abandoned cart items.");
    }

    // ─────────────────────────────────────────────────────────────
    // VOUCHER USAGE (via promotions.used_count & transaction discount)
    // ─────────────────────────────────────────────────────────────

    private function seedVoucherUsage(): void
    {
        $promotions = Promotion::where('type', 'voucher')->get();

        if ($promotions->isEmpty()) {
            // Create sample vouchers if none exist
            $promoData = [
                ['name' => 'WELCOME10', 'code' => 'WELCOME10', 'discount_type' => 'percentage', 'discount_value' => 10, 'min_purchase' => 50000, 'max_discount' => 30000],
                ['name' => 'HEMAT50K', 'code' => 'HEMAT50K', 'discount_type' => 'fixed', 'discount_value' => 50000, 'min_purchase' => 200000, 'max_discount' => 50000],
                ['name' => 'FLASH20', 'code' => 'FLASH20', 'discount_type' => 'percentage', 'discount_value' => 20, 'min_purchase' => 100000, 'max_discount' => 75000],
                ['name' => 'HARBOL25', 'code' => 'HARBOL25', 'discount_type' => 'percentage', 'discount_value' => 25, 'min_purchase' => 150000, 'max_discount' => 100000],
            ];

            foreach ($promoData as $pd) {
                $promotions->push(Promotion::create([
                    'id' => (string) Str::uuid(),
                    'name' => $pd['name'],
                    'type' => 'voucher',
                    'code' => $pd['code'],
                    'discount_type' => $pd['discount_type'],
                    'discount_value' => $pd['discount_value'],
                    'min_purchase' => $pd['min_purchase'],
                    'max_discount' => $pd['max_discount'],
                    'quota' => 100,
                    'used_count' => 0,
                    'is_active' => true,
                    'start_time' => Carbon::now()->subDays(30),
                    'end_time' => Carbon::now()->addDays(60),
                    'settings' => null,
                ]));
            }
        }

        // Apply vouchers to some completed transactions that have no discount yet
        $transactions = Transaction::where('discount_amount', 0)
            ->whereIn('status', ['selesai', 'dikirim', 'diproses'])
            ->take(50)
            ->get();

        $updatedTx = 0;
        foreach ($transactions as $tx) {
            if (rand(0, 2) === 0) {
                continue; // ~33% skip to keep it realistic
            }

            /** @var Promotion $promo */
            $promo = $promotions->random();

            if ((float) $tx->subtotal < (float) $promo->min_purchase) {
                continue;
            }

            $discount = $promo->discount_type === 'percentage'
                ? min((float) $tx->subtotal * $promo->discount_value / 100, (float) $promo->max_discount)
                : (float) $promo->discount_value;

            $discount = min($discount, (float) $tx->subtotal);

            DB::table('transactions')
                ->where('id', $tx->id)
                ->update([
                    'discount_amount' => $discount,
                    'grand_total' => max(0, (float) $tx->grand_total - $discount),
                    'voucher_code' => $promo->code,
                    'voucher_discount_type' => $promo->discount_type,
                    'voucher_discount_value' => $promo->discount_value,
                ]);

            $promo->increment('used_count');
            $updatedTx++;
        }

        $this->command->info("  Applied vouchers to {$updatedTx} transactions.");
    }
}

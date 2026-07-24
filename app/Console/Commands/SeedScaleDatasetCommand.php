<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
// Wait, let's use Illuminate\Support\Str
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Signature('db:seed-scale {--count=100000 : Jumlah transaksi yang ingin dibuat}')]
#[Description('Seed database dengan skala besar (jutaan data transaksi, refund, retur) untuk uji kinerja')]
class SeedScaleDatasetCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->option('count');

        if ($count <= 0) {
            $this->error('Jumlah data (count) harus lebih besar dari 0.');

            return Command::FAILURE;
        }

        $this->info("Menyiapkan seeding skala besar untuk {$count} transaksi...");

        // Disable query logging to save memory
        DB::disableQueryLog();

        // 1. Check/Prepare base records
        $customers = DB::table('users')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('roles.name', 'Customer');
            })
            ->pluck('id')
            ->toArray();

        if (empty($customers)) {
            $this->info('Membuat 100 customer dummy...');
            $users = [];
            for ($i = 0; $i < 100; $i++) {
                $users[] = [
                    'id' => $this->fastUuid(),
                    'name' => "Customer Scale $i",
                    'email' => "customer-scale-$i-".time().'@example.com',
                    'password' => bcrypt('password'),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('users')->insert($users);
            $customers = array_column($users, 'id');
        }

        // Setup Spatie roles for customers if roles table exists
        try {
            $customerRole = DB::table('roles')->where('name', 'Customer')->first();
            if ($customerRole) {
                $modelHasRoles = [];
                foreach ($customers as $cId) {
                    // Check if already assigned
                    $exists = DB::table('model_has_roles')
                        ->where('role_id', $customerRole->id)
                        ->where('model_id', $cId)
                        ->exists();
                    if (! $exists) {
                        $modelHasRoles[] = [
                            'role_id' => $customerRole->id,
                            'model_type' => 'App\Models\User',
                            'model_id' => $cId,
                        ];
                    }
                }
                if (! empty($modelHasRoles)) {
                    DB::table('model_has_roles')->insert($modelHasRoles);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if roles table is not present
        }

        $paymentMethods = DB::table('payment_methods')->pluck('id')->toArray();
        if (empty($paymentMethods)) {
            $pmId = $this->fastUuid();
            DB::table('payment_methods')->insert([
                'id' => $pmId,
                'name' => 'Transfer Bank BCA (Manual)',
                'type' => 'manual',
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_name' => 'PT Toko Kita Utama',
                'admin_fee' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $paymentMethods = [$pmId];
        }

        $customerAddresses = DB::table('customer_addresses')->pluck('id')->toArray();
        if (empty($customerAddresses)) {
            $addresses = [];
            foreach ($customers as $userId) {
                $addresses[] = [
                    'id' => $this->fastUuid(),
                    'user_id' => $userId,
                    'label' => 'Rumah',
                    'receiver_name' => 'Penerima',
                    'phone_number' => '08123456789',
                    'full_address' => 'Jl. Uji Kinerja No. 1',
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('customer_addresses')->insert($addresses);
            $customerAddresses = DB::table('customer_addresses')->pluck('id')->toArray();
        }

        $categories = DB::table('categories')->pluck('id')->toArray();
        if (empty($categories)) {
            $catId = $this->fastUuid();
            DB::table('categories')->insert([
                'id' => $catId,
                'name' => 'Kategori Uji',
                'slug' => 'kategori-uji',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $categories = [$catId];
        }

        // Fetch products with price and cost
        $dbProducts = DB::table('products')
            ->leftJoin('product_prices', 'products.id', '=', 'product_prices.product_id')
            ->whereNull('product_prices.product_variant_id')
            ->select('products.id', 'products.name', 'products.sku', 'product_prices.price', 'product_prices.cost')
            ->get()
            ->toArray();

        if (empty($dbProducts)) {
            $this->info('Membuat 50 produk dummy...');
            $prods = [];
            $prices = [];
            $stocks = [];
            for ($i = 0; $i < 50; $i++) {
                $pId = $this->fastUuid();
                $prods[] = [
                    'id' => $pId,
                    'name' => "Produk Skala $i",
                    'slug' => "produk-skala-$i",
                    'sku' => "SKU-SKL-$i",
                    'category_id' => $categories[0],
                    'description' => 'Deskripsi produk skala.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $prices[] = [
                    'id' => $this->fastUuid(),
                    'product_id' => $pId,
                    'product_variant_id' => null,
                    'price' => rand(10000, 500000),
                    'cost' => rand(5000, 200000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $stocks[] = [
                    'id' => $this->fastUuid(),
                    'product_id' => $pId,
                    'product_variant_id' => null,
                    'stock' => rand(100, 5000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('products')->insert($prods);
            DB::table('product_prices')->insert($prices);
            DB::table('product_stocks')->insert($stocks);

            $dbProducts = DB::table('products')
                ->leftJoin('product_prices', 'products.id', '=', 'product_prices.product_id')
                ->whereNull('product_prices.product_variant_id')
                ->select('products.id', 'products.name', 'products.sku', 'product_prices.price', 'product_prices.cost')
                ->get()
                ->toArray();
        }

        $adminUser = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->whereIn('roles.name', ['Super Admin', 'Admin'])
            ->select('users.id')
            ->first();
        $adminId = $adminUser?->id;

        $statuses = ['belum_bayar', 'menunggu', 'diproses', 'dikemas', 'dikirim', 'selesai', 'batal'];
        $couriers = ['JNE', 'J&T Express', 'SiCepat', 'AnterAja', 'Pos Indonesia'];
        $refundStatuses = ['menunggu_konfirmasi', 'diproses', 'selesai', 'ditolak', 'selesai'];
        $returnStatuses = ['menunggu_review', 'disetujui', 'diterima', 'selesai', 'ditolak'];
        $refundMethods = ['transfer_bank', 'saldo'];

        $chunkSize = 1000;
        $totalChunks = ceil($count / $chunkSize);

        $progressBar = $this->output->createProgressBar($count);
        $progressBar->start();

        $startingTxId = DB::table('transactions')->count() + 1;
        $startingRefundId = DB::table('refund_requests')->count() + 1;
        $startingReturnId = DB::table('returns')->count() + 1;

        $refundCount = 0;
        $returnCount = 0;

        for ($chunk = 1; $chunk <= $totalChunks; $chunk++) {
            $transactions = [];
            $transactionItems = [];
            $refundRequests = [];
            $returnRequests = [];
            $returnItems = [];

            $currentChunkSize = min($chunkSize, $count - (($chunk - 1) * $chunkSize));

            for ($i = 0; $i < $currentChunkSize; $i++) {
                $txId = $this->fastUuid();
                $userId = $customers[array_rand($customers)];
                $addressId = $customerAddresses[array_rand($customerAddresses)];
                $paymentId = $paymentMethods[array_rand($paymentMethods)];
                $status = $statuses[array_rand($statuses)];
                $createdAt = Carbon::now()->subSeconds(rand(0, 365 * 24 * 60 * 60));

                // 1-3 items
                $numItems = rand(1, 3);
                $subtotal = 0;
                $txItemsList = [];

                for ($itemIdx = 0; $itemIdx < $numItems; $itemIdx++) {
                    $prod = $dbProducts[array_rand($dbProducts)];
                    $price = (float) ($prod->price ?? rand(20000, 150000));
                    $cost = (float) ($prod->cost ?? ($price * 0.7));
                    $qty = rand(1, 3);
                    $itemSub = $price * $qty;
                    $subtotal += $itemSub;

                    $txItemsList[] = [
                        'id' => $this->fastUuid(),
                        'transaction_id' => $txId,
                        'product_id' => $prod->id,
                        'product_variant_id' => null,
                        'product_name' => $prod->name,
                        'product_sku' => $prod->sku,
                        'variant_name' => null,
                        'product_image' => null,
                        'quantity' => $qty,
                        'hpp' => $cost,
                        'harga_jual' => $price,
                        'diskon_item' => 0,
                        'harga_akhir' => $price,
                        'subtotal' => $itemSub,
                        'is_gift_item' => false,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }

                $discount = rand(0, 1) === 1 ? (float) rand(5000, 20000) : 0.0;
                $shippingFee = (float) rand(10000, 35000);
                $adminFee = (float) rand(1000, 2500);
                $grandTotal = max(0.0, $subtotal - $discount + $shippingFee + $adminFee);

                $sequenceNumber = $startingTxId + (($chunk - 1) * $chunkSize + $i);
                $trxNumber = 'TRX-'.$createdAt->format('Ymd').'-'.sprintf('%09d', $sequenceNumber);

                $transactions[] = [
                    'id' => $txId,
                    'transaction_number' => $trxNumber,
                    'user_id' => $userId,
                    'customer_address_id' => $addressId,
                    'payment_method_id' => $paymentId,
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'shipping_fee' => $shippingFee,
                    'shipping_discount' => 0.0,
                    'admin_fee' => $adminFee,
                    'application_fee' => 0.0,
                    'grand_total' => $grandTotal,
                    'shipping_courier' => $couriers[array_rand($couriers)],
                    'shipping_service' => 'Reguler',
                    'shipping_etd' => '2-3 hari',
                    'voucher_code' => $discount > 0 ? 'DISC'.rand(10, 50) : null,
                    'voucher_discount_type' => $discount > 0 ? 'fixed' : null,
                    'voucher_discount_value' => $discount > 0 ? $discount : null,
                    'notes' => 'Catatan uji skala',
                    'cancel_reason' => $status === 'batal' ? 'Dibatalkan oleh sistem uji' : null,
                    'cancelled_at' => $status === 'batal' ? $createdAt->copy()->addHours(1) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                foreach ($txItemsList as $item) {
                    $transactionItems[] = $item;
                }

                // Seeding refund requests for 5% of cancelled/finished transactions
                if (in_array($status, ['batal', 'selesai']) && rand(1, 20) === 1) {
                    $refStatus = $refundStatuses[array_rand($refundStatuses)];
                    $refMethod = $refundMethods[array_rand($refundMethods)];
                    $refSequenceNumber = $startingRefundId + $refundCount++;
                    $refNumber = 'RFN-'.$createdAt->format('Ymd').'-'.sprintf('%09d', $refSequenceNumber);

                    $refundRequests[] = [
                        'id' => $this->fastUuid(),
                        'refund_number' => $refNumber,
                        'transaction_id' => $txId,
                        'user_id' => $userId,
                        'status' => $refStatus,
                        'refund_method' => $refMethod,
                        'reason' => 'Uji coba refund otomatis',
                        'notes_admin' => 'Diproses oleh seeder skala',
                        'bank_name' => $refMethod === 'transfer_bank' ? 'BCA' : null,
                        'account_number' => $refMethod === 'transfer_bank' ? '8830'.rand(100000, 999999) : null,
                        'account_name' => 'Rekening Uji',
                        'refund_amount' => $grandTotal,
                        'processed_by' => $adminId,
                        'processed_at' => $createdAt->copy()->addHours(rand(1, 10)),
                        'refunded_at' => $refStatus === 'selesai' ? $createdAt->copy()->addHours(rand(11, 24)) : null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }

                // Seeding returns for 5% of completed transactions
                if ($status === 'selesai' && rand(1, 20) === 1) {
                    $retId = $this->fastUuid();
                    $retStatus = $returnStatuses[array_rand($returnStatuses)];
                    $retType = rand(1, 2) === 1 ? 'refund' : 'replacement';

                    $retSequenceNumber = $startingReturnId + $returnCount++;
                    $retNumber = 'RTN-'.$createdAt->format('Ymd').'-'.sprintf('%09d', $retSequenceNumber);

                    $returnRequests[] = [
                        'id' => $retId,
                        'return_number' => $retNumber,
                        'transaction_id' => $txId,
                        'user_id' => $userId,
                        'status' => $retStatus,
                        'type' => $retType,
                        'reason' => 'Barang kurang memuaskan saat uji coba',
                        'notes_admin' => 'Return diterima oleh sistem seeder',
                        'return_tracking_number' => 'RET'.rand(100000, 999999),
                        'return_courier_name' => $couriers[array_rand($couriers)],
                        'replacement_tracking_number' => $retType === 'replacement' ? 'REP'.rand(100000, 999999) : null,
                        'replacement_courier_name' => $retType === 'replacement' ? $couriers[array_rand($couriers)] : null,
                        'refund_amount' => $retType === 'refund' ? $grandTotal : 0.0,
                        'approved_by' => $adminId,
                        'received_by' => $adminId,
                        'approved_at' => $createdAt->copy()->addHours(rand(1, 10)),
                        'received_at' => in_array($retStatus, ['diterima', 'selesai']) ? $createdAt->copy()->addHours(rand(11, 24)) : null,
                        'refunded_at' => $retStatus === 'selesai' ? $createdAt->copy()->addHours(rand(25, 48)) : null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];

                    // Seed 1 return item from generated txItemsList
                    $chosenItem = $txItemsList[array_rand($txItemsList)];
                    $returnItems[] = [
                        'id' => $this->fastUuid(),
                        'return_id' => $retId,
                        'transaction_item_id' => $chosenItem['id'],
                        'product_id' => $chosenItem['product_id'],
                        'product_variant_id' => null,
                        'product_name' => $chosenItem['product_name'],
                        'variant_name' => null,
                        'quantity_returned' => $chosenItem['quantity'],
                        'unit_price' => $chosenItem['harga_akhir'],
                        'refund_subtotal' => $chosenItem['subtotal'],
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
            }

            // Insert chunk to DB
            DB::transaction(function () use ($transactions, $transactionItems, $refundRequests, $returnRequests, $returnItems) {
                DB::table('transactions')->insert($transactions);
                DB::table('transaction_items')->insert($transactionItems);

                if (! empty($refundRequests)) {
                    DB::table('refund_requests')->insert($refundRequests);
                }
                if (! empty($returnRequests)) {
                    DB::table('returns')->insert($returnRequests);
                }
                if (! empty($returnItems)) {
                    DB::table('return_items')->insert($returnItems);
                }
            });

            $progressBar->advance($currentChunkSize);

            // Free memory
            unset($transactions, $transactionItems, $refundRequests, $returnRequests, $returnItems);
            gc_collect_cycles();
        }

        $progressBar->finish();
        $this->info("\nSeeding selesai!");

        // Clear dashboard stats cache to force refresh with new data
        Cache::flush();

        return Command::SUCCESS;
    }

    /**
     * Generate standard UUID version 4 quickly.
     */
    private function fastUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DummyTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure Roles and Customers are present
        $customerRole = Role::firstOrCreate(['name' => 'Customer']);

        $customersData = [
            ['name' => 'Budi Santoso', 'email' => 'budi@example.com'],
            ['name' => 'Ani Wijaya', 'email' => 'ani@example.com'],
            ['name' => 'Citra Lestari', 'email' => 'citra@example.com'],
            ['name' => 'Dedi Kurniawan', 'email' => 'dedi@example.com'],
            ['name' => 'Eko Prasetyo', 'email' => 'eko@example.com'],
            ['name' => 'Farida Utami', 'email' => 'farida@example.com'],
            ['name' => 'Guntur Wibowo', 'email' => 'guntur@example.com'],
            ['name' => 'Hesti Putri', 'email' => 'hesti@example.com'],
        ];

        $customers = [];
        foreach ($customersData as $data) {
            $customer = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );
            if (! $customer->hasRole('Customer')) {
                $customer->assignRole($customerRole);
            }
            $customers[] = $customer;
        }

        // 2. Fetch dependencies
        $products = Product::where('active', true)->with(['variants', 'productPrice', 'productStock'])->get();
        if ($products->isEmpty()) {
            $this->command->warn('No products found in the database. Please run CategoryAndProductSeeder first.');

            return;
        }

        $paymentMethod = PaymentMethod::where('is_active', true)->first();
        if (! $paymentMethod) {
            $paymentMethod = PaymentMethod::create([
                'name' => 'Transfer Bank BCA (Manual)',
                'type' => 'manual',
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_name' => 'PT Toko Kita Utama',
                'is_active' => true,
            ]);
        }

        $courier = Courier::where('is_active', true)->first();
        if (! $courier) {
            $courier = Courier::create([
                'code' => 'jne',
                'name' => 'JNE',
                'is_active' => true,
            ]);
        }

        // 3. Statuses and settings
        $statuses = ['belum_bayar', 'menunggu', 'diproses', 'dikemas', 'dikirim', 'selesai', 'batal'];

        // 4. Create exactly 100 random transactions
        for ($count = 0; $count < 100; $count++) {
            $customer = $customers[array_rand($customers)];

            // Create address
            $address = CustomerAddress::firstOrCreate(
                ['user_id' => $customer->id],
                [
                    'label' => 'Rumah',
                    'receiver_name' => $customer->name,
                    'phone_number' => '0812345678'.rand(10, 99),
                    'full_address' => 'Jl. Merdeka No. '.rand(1, 150).', DKI Jakarta',
                    'is_primary' => true,
                ]
            );

            $status = $statuses[array_rand($statuses)];

            // Random date: 25% today, 75% past 60 days
            if (rand(1, 100) <= 25) {
                // Today: random time up to now
                $createdAt = Carbon::now()->subHours(rand(0, Carbon::now()->hour))->subMinutes(rand(0, 59));
            } else {
                // Past days: 1 to 60 days ago
                $createdAt = Carbon::now()->subDays(rand(1, 60))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            }

            // Get 1-3 random products
            $selectedProducts = $products->random(min(rand(1, 3), $products->count()));

            $itemsData = [];
            $subtotal = 0;

            foreach ($selectedProducts as $product) {
                $variant = null;
                if ($product->variants->isNotEmpty()) {
                    $variant = $product->variants->random();
                }

                // Get price and cost
                $priceVal = 150000;
                $costVal = 90000;

                if ($variant) {
                    $pPrice = ProductPrice::where('product_variant_id', $variant->id)->first();
                    if ($pPrice) {
                        $priceVal = (float) $pPrice->price;
                        $costVal = (float) $pPrice->cost;
                    }
                } else {
                    if ($product->productPrice) {
                        $priceVal = (float) $product->productPrice->price;
                        $costVal = (float) $product->productPrice->cost;
                    }
                }

                $qty = rand(1, 2);
                $itemSubtotal = $priceVal * $qty;
                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant ? $variant->id : null,
                    'product_name' => $product->name,
                    'product_sku' => $variant ? $variant->sku : $product->sku,
                    'variant_name' => $variant ? ($variant->sku ?: 'Variant') : null,
                    'product_image' => $product->image,
                    'quantity' => $qty,
                    'hpp' => $costVal,
                    'harga_jual' => $priceVal,
                    'diskon_item' => 0,
                    'harga_akhir' => $priceVal,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $shippingFee = rand(10000, 25000);
            $grandTotal = $subtotal + $shippingFee + 1000; // subtotal + shipping + app fee (1000)

            // Generate Number
            $prefix = 'TRX-'.$createdAt->format('Ymd').'-';
            $last = Transaction::where('transaction_number', 'like', $prefix.'%')
                ->orderByDesc('transaction_number')
                ->value('transaction_number');
            $seq = $last ? (int) substr($last, -5) + 1 : 1;
            $transactionNumber = $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);

            // Create Transaction
            $transaction = new Transaction;
            $transaction->id = (string) Str::uuid();
            $transaction->transaction_number = $transactionNumber;
            $transaction->user_id = $customer->id;
            $transaction->customer_address_id = $address->id;
            $transaction->payment_method_id = $paymentMethod->id;
            $transaction->courier_id = $courier->id;
            $transaction->status = $status;
            $transaction->subtotal = $subtotal;
            $transaction->discount_amount = 0;
            $transaction->shipping_fee = $shippingFee;
            $transaction->shipping_discount = 0;
            $transaction->admin_fee = 0;
            $transaction->application_fee = 1000;
            $transaction->grand_total = $grandTotal;
            $transaction->shipping_courier = $courier->code;
            $transaction->shipping_service = 'Reguler';
            $transaction->shipping_etd = '2-3 Hari';

            if (in_array($status, ['dikirim', 'selesai'])) {
                $transaction->tracking_number = 'RSI'.rand(10000000, 99999999);
            }
            if (in_array($status, ['diproses', 'dikemas', 'dikirim', 'selesai'])) {
                $transaction->booking_code = 'ST-'.$transactionNumber;
            }
            if ($status === 'batal') {
                $transaction->cancel_reason = 'Dibatalkan oleh pelanggan';
                $transaction->cancelled_at = $createdAt->copy()->addMinutes(15);
            }

            $transaction->created_at = $createdAt;
            $transaction->updated_at = $createdAt;
            $transaction->save();

            // Create Items & Stock Movements
            foreach ($itemsData as $item) {
                $tItem = new TransactionItem;
                $tItem->id = (string) Str::uuid();
                $tItem->transaction_id = $transaction->id;
                $tItem->product_id = $item['product_id'];
                $tItem->product_variant_id = $item['product_variant_id'];
                $tItem->product_name = $item['product_name'];
                $tItem->product_sku = $item['product_sku'];
                $tItem->variant_name = $item['variant_name'];
                $tItem->product_image = $item['product_image'];
                $tItem->quantity = $item['quantity'];
                $tItem->hpp = $item['hpp'];
                $tItem->harga_jual = $item['harga_jual'];
                $tItem->diskon_item = $item['diskon_item'];
                $tItem->harga_akhir = $item['harga_akhir'];
                $tItem->subtotal = $item['subtotal'];
                $tItem->created_at = $createdAt;
                $tItem->updated_at = $createdAt;
                $tItem->save();

                // Adjust product stock & record stock movement if order is not pending/unpaid/cancelled
                if (in_array($status, ['diproses', 'dikemas', 'dikirim', 'selesai'])) {
                    $pStock = ProductStock::where('product_id', $item['product_id'])
                        ->where('product_variant_id', $item['product_variant_id'])
                        ->first();

                    if ($pStock) {
                        $stockBefore = (int) $pStock->stock;
                        $stockAfter = max(0, $stockBefore - $item['quantity']);

                        if (! $pStock->is_unlimited) {
                            $pStock->stock = $stockAfter;
                            $pStock->save();
                        }

                        $movement = new StockMovement;
                        $movement->id = (string) Str::uuid();
                        $movement->product_id = $item['product_id'];
                        $movement->product_variant_id = $item['product_variant_id'];
                        $movement->transaction_id = $transaction->id;
                        $movement->type = 'keluar';
                        $movement->quantity = $item['quantity'];
                        $movement->stock_before = $stockBefore;
                        $movement->stock_after = $stockAfter;
                        $movement->notes = 'Penjualan #'.$transactionNumber;
                        $movement->created_at = $createdAt;
                        $movement->updated_at = $createdAt;
                        $movement->save();
                    }
                }
            }

            // Create payment
            if ($status !== 'belum_bayar') {
                $payment = new TransactionPayment;
                $payment->id = (string) Str::uuid();
                $payment->transaction_id = $transaction->id;
                $payment->payment_method_id = $paymentMethod->id;
                $payment->amount = $grandTotal;
                $payment->status = in_array($status, ['batal', 'menunggu']) ? 'pending' : 'confirmed';
                $payment->notes = 'Pembayaran untuk #'.$transactionNumber;
                if ($payment->status === 'confirmed') {
                    $payment->confirmed_at = $createdAt;
                }
                $payment->created_at = $createdAt;
                $payment->updated_at = $createdAt;
                $payment->save();
            }
        }
    }
}

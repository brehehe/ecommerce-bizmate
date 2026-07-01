<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Get or Create Customer
        $customer = User::where('email', 'admin@bizmate.com')->first();
        if (! $customer) {
            $customer = User::create([
                'name' => 'John Doe Customer',
                'email' => 'admin@bizmate.com',
                'password' => bcrypt('password'),
            ]);
            $customer->assignRole('Customer');
        }

        // 2. Get or Create Address for Customer
        $address = CustomerAddress::where('user_id', $customer->id)->first();
        if (! $address) {
            $address = CustomerAddress::create([
                'user_id' => $customer->id,
                'label' => 'Rumah',
                'receiver_name' => 'John Doe',
                'phone_number' => '081234567890',
                'full_address' => 'Jl. Jenderal Sudirman No. 45, Kebayoran Baru',
                'province_id' => '31',
                'province_name' => 'DKI Jakarta',
                'regency_id' => '3174',
                'regency_name' => 'Jakarta Selatan',
                'district_id' => '317404',
                'district_name' => 'Kebayoran Baru',
                'village_id' => '3174041001',
                'village_name' => 'Selong',
                'postal_code' => '12110',
                'is_primary' => true,
            ]);
        }

        // 3. Get Payment Methods & Couriers
        $paymentMethods = PaymentMethod::all();
        if ($paymentMethods->isEmpty()) {
            $paymentMethods = collect([
                PaymentMethod::create([
                    'name' => 'Transfer Bank BCA (Manual)',
                    'type' => 'manual',
                    'bank_name' => 'BCA',
                    'account_number' => '1234567890',
                    'account_name' => 'PT Toko Kita Utama',
                    'admin_fee' => 0,
                    'is_active' => true,
                ]),
            ]);
        }

        $couriers = Courier::all();
        if ($couriers->isEmpty()) {
            $couriers = collect([
                Courier::create([
                    'code' => 'jne',
                    'name' => 'JNE',
                    'is_active' => true,
                    'order' => 1,
                ]),
            ]);
        }

        // 4. Get active products
        $products = Product::where('active', true)->with(['productPrice', 'productStock'])->get();
        if ($products->isEmpty()) {
            $this->command->warn('No active products found to seed transactions. Run CategoryAndProductSeeder first.');

            return;
        }

        // 5. Delete existing transactions to prevent bloat/conflicts (optional, but good for clean seed)
        Transaction::query()->forceDelete();

        // 6. Define statuses list
        $statuses = [
            'belum_bayar',
            'menunggu',
            'diproses',
            'dikemas',
            'out_for_pickup',
            'dikirim',
            'selesai',
            'batal',
        ];

        // 7. Seed 20 Transactions
        for ($i = 1; $i <= 20; $i++) {
            $status = $statuses[($i - 1) % count($statuses)];
            $createdAt = Carbon::now()->subDays(21 - $i)->subHours(rand(1, 10));

            // Select 1 to 3 random products
            $selectedProducts = $products->random(rand(1, 3));
            $subtotal = 0;
            $itemsData = [];

            foreach ($selectedProducts as $prod) {
                $qty = rand(1, 2);
                $price = $prod->productPrice?->price ?? 0;
                $itemSubtotal = $price * $qty;
                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'product_id' => $prod->id,
                    'product_name' => $prod->name,
                    'product_sku' => $prod->sku,
                    'quantity' => $qty,
                    'hpp' => $prod->productPrice?->cost ?? ($price * 0.8),
                    'harga_jual' => $price,
                    'diskon_item' => 0,
                    'harga_akhir' => $price,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $shippingFee = rand(10000, 25000);
            $grandTotal = $subtotal + $shippingFee;

            $pm = $paymentMethods->random();
            $courier = $couriers->random();

            // Create Transaction
            $trxNumber = 'TRX-2026'.str_pad($i, 4, '0', STR_PAD_LEFT).'-'.rand(10, 99);
            $transaction = Transaction::create([
                'transaction_number' => $trxNumber,
                'user_id' => $customer->id,
                'customer_address_id' => $address->id,
                'payment_method_id' => $pm->id,
                'courier_id' => $courier->id,
                'status' => $status,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'shipping_fee' => $shippingFee,
                'shipping_discount' => 0,
                'admin_fee' => 0,
                'application_fee' => 0,
                'grand_total' => $grandTotal,
                'shipping_courier' => $courier->code,
                'shipping_service' => 'REG',
                'shipping_etd' => '2-3 hari',
                'notes' => 'Catatan belanja dummy #'.$i,
                'tracking_number' => in_array($status, ['dikirim', 'selesai']) ? 'TRACK-'.strtoupper(Str::random(10)) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Create Transaction Items
            foreach ($itemsData as $item) {
                $transaction->items()->create($item);
            }

            // Create Payment
            $paymentStatus = 'pending';
            $confirmedAt = null;
            if (in_array($status, ['diproses', 'dikemas', 'out_for_pickup', 'dikirim', 'selesai'])) {
                $paymentStatus = 'confirmed';
                $confirmedAt = $createdAt->copy()->addMinutes(rand(10, 60));
            } elseif ($status === 'batal') {
                $paymentStatus = 'rejected';
            }

            $transaction->payment()->create([
                'payment_method_id' => $pm->id,
                'amount' => $grandTotal,
                'status' => $paymentStatus,
                'proof_image' => ($status === 'menunggu' || $paymentStatus === 'confirmed') ? 'https://images.unsplash.com/photo-1554415707-6e8cfc93fe23?w=600&auto=format&fit=crop&q=80' : null,
                'proof_uploaded_at' => ($status === 'menunggu' || $paymentStatus === 'confirmed') ? $createdAt->copy()->addMinutes(5) : null,
                'confirmed_at' => $confirmedAt,
                'confirmed_by' => $confirmedAt ? User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first()?->id : null,
            ]);

            // Create Status Histories (Chronological Timeline)
            $historyStatuses = [];
            switch ($status) {
                case 'selesai':
                    $historyStatuses = ['belum_bayar', 'menunggu', 'diproses', 'dikemas', 'out_for_pickup', 'dikirim', 'selesai'];
                    break;
                case 'dikirim':
                    $historyStatuses = ['belum_bayar', 'menunggu', 'diproses', 'dikemas', 'out_for_pickup', 'dikirim'];
                    break;
                case 'out_for_pickup':
                    $historyStatuses = ['belum_bayar', 'menunggu', 'diproses', 'dikemas', 'out_for_pickup'];
                    break;
                case 'dikemas':
                    $historyStatuses = ['belum_bayar', 'menunggu', 'diproses', 'dikemas'];
                    break;
                case 'diproses':
                    $historyStatuses = ['belum_bayar', 'menunggu', 'diproses'];
                    break;
                case 'menunggu':
                    $historyStatuses = ['belum_bayar', 'menunggu'];
                    break;
                case 'batal':
                    $historyStatuses = ['belum_bayar', 'batal'];
                    break;
                default:
                    $historyStatuses = ['belum_bayar'];
                    break;
            }

            $timeTracker = $createdAt->copy();
            foreach ($historyStatuses as $hStatus) {
                $transaction->statusHistories()->create([
                    'status' => $hStatus,
                    'created_at' => $timeTracker,
                ]);
                $timeTracker = $timeTracker->addHours(rand(1, 6));
            }
        }
    }
}

<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_number' => Transaction::generateNumber(),
            'user_id' => User::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'status' => 'dikemas',
            'subtotal' => 100000,
            'discount_amount' => 0,
            'shipping_fee' => 10000,
            'shipping_discount' => 0,
            'admin_fee' => 0,
            'application_fee' => 0,
            'grand_total' => 110000,
            'shipping_courier' => 'jne',
            'shipping_service' => 'REG',
            'coins_redeemed' => 0,
            'coins_value' => 0,
            'coins_earned' => 0,
        ];
    }

    /**
     * State for store courier transactions.
     */
    public function storeCourier(): static
    {
        return $this->state(fn (array $attrs) => [
            'shipping_courier' => 'store_courier',
            'booking_code' => 'ST-'.fake()->numerify('TRX-######'),
        ]);
    }
}

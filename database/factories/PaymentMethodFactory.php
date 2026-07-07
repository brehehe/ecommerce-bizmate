<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Bank Transfer '.fake()->bankAccountNumber(),
            'type' => 'manual',
            'bank_name' => fake()->randomElement(['BCA', 'BRI', 'Mandiri', 'BNI']),
            'account_number' => fake()->numerify('##########'),
            'account_name' => fake()->name(),
            'is_active' => true,
            'admin_fee' => 0,
        ];
    }
}

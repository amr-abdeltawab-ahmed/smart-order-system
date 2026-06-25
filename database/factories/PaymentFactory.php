<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'payment_method' => $this->faker->randomElement(['credit_card', 'paypal']),
            'payment_reference' => strtoupper($this->faker->bothify('??-########')),
            'status' => 'successful',
            'gateway_response' => [
                'gateway' => 'credit_card',
                'transaction_id' => $this->faker->uuid(),
                'amount' => $this->faker->randomFloat(2, 10, 500),
                'currency' => 'USD',
            ],
        ];
    }
}

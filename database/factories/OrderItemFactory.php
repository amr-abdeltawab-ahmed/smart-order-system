<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $price    = $this->faker->randomFloat(2, 1.00, 100.00);

        return [
            'order_id'     => Order::factory(),
            'product_name' => $this->faker->words(3, true),
            'quantity'     => $quantity,
            'price'        => $price,
            'subtotal'     => round($quantity * $price, 2),
        ];
    }
}

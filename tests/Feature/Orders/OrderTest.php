<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'items' => [
                ['product_name' => 'Widget A', 'quantity' => 2, 'price' => 9.99],
                ['product_name' => 'Widget B', 'quantity' => 1, 'price' => 19.99],
            ],
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function test_user_can_create_an_order(): void
    {
        $response = $this->withJwt($this->user)
            ->postJson('/api/orders', $this->orderPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'status', 'total',
                'items' => [['id', 'product_name', 'quantity', 'price', 'subtotal']],
            ]);

        $this->assertSame('pending', $response->json('status'));
        $this->assertEqualsWithDelta(39.97, $response->json('total'), 0.001);
    }

    public function test_order_total_is_auto_calculated(): void
    {
        $response = $this->withJwt($this->user)
            ->postJson('/api/orders', [
                'items' => [['product_name' => 'Item', 'quantity' => 3, 'price' => 10.00]],
            ]);

        $response->assertStatus(201);
        $this->assertEqualsWithDelta(30.0, $response->json('total'), 0.001);
    }

    public function test_create_order_validation_fails_without_items(): void
    {
        $this->withJwt($this->user)
            ->postJson('/api/orders', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_create_order_rejects_too_many_items(): void
    {
        $items = array_fill(0, 51, ['product_name' => 'Item', 'quantity' => 1, 'price' => 1.00]);

        $this->withJwt($this->user)
            ->postJson('/api/orders', ['items' => $items])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_unauthenticated_user_cannot_create_order(): void
    {
        $this->postJson('/api/orders', $this->orderPayload())->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function test_user_can_list_own_orders(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $this->withJwt($this->user)
            ->getJson('/api/orders')
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $order->id]);
    }

    public function test_user_cannot_see_other_users_orders(): void
    {
        $otherUser  = User::factory()->create();
        $otherOrder = Order::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->withJwt($this->user)->getJson('/api/orders');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($otherOrder->id));
    }

    public function test_user_can_filter_orders_by_status(): void
    {
        Order::factory()->create(['user_id' => $this->user->id, 'status' => 'pending',   'total' => 10]);
        Order::factory()->create(['user_id' => $this->user->id, 'status' => 'confirmed', 'total' => 20]);

        $response = $this->withJwt($this->user)->getJson('/api/orders?status=confirmed');

        $response->assertStatus(200);
        foreach ($response->json('data') as $order) {
            $this->assertSame('confirmed', $order['status']);
        }
    }

    public function test_index_rejects_invalid_status_filter(): void
    {
        $this->withJwt($this->user)
            ->getJson('/api/orders?status=invalid_status')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_index_rejects_per_page_above_limit(): void
    {
        $this->withJwt($this->user)
            ->getJson('/api/orders?per_page=999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_user_can_view_a_single_order(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id, 'total' => 10]);

        $this->withJwt($this->user)
            ->getJson("/api/orders/{$order->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $order->id]);
    }

    public function test_show_returns_404_for_nonexistent_order(): void
    {
        $this->withJwt($this->user)
            ->getJson('/api/orders/9999')
            ->assertStatus(404);
    }

    public function test_user_cannot_view_other_users_order(): void
    {
        $otherUser  = User::factory()->create();
        $otherOrder = Order::factory()->create(['user_id' => $otherUser->id, 'total' => 10]);

        $this->withJwt($this->user)
            ->getJson("/api/orders/{$otherOrder->id}")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_user_can_update_order_status(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id, 'status' => 'pending', 'total' => 10]);

        $this->withJwt($this->user)
            ->putJson("/api/orders/{$order->id}", ['status' => 'confirmed'])
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'confirmed']);
    }

    public function test_user_can_update_order_items(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id, 'total' => 10]);

        $response = $this->withJwt($this->user)
            ->putJson("/api/orders/{$order->id}", [
                'items' => [['product_name' => 'New Item', 'quantity' => 2, 'price' => 5.00]],
            ]);

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(10.00, $response->json('total'), 0.001);
        $this->assertSame('New Item', $response->json('items.0.product_name'));
    }

    public function test_update_rejects_empty_body(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id, 'total' => 10]);

        $this->withJwt($this->user)
            ->putJson("/api/orders/{$order->id}", [])
            ->assertStatus(422);
    }

    public function test_user_cannot_update_other_users_order(): void
    {
        $otherUser  = User::factory()->create();
        $otherOrder = Order::factory()->create(['user_id' => $otherUser->id, 'status' => 'pending', 'total' => 10]);

        $this->withJwt($this->user)
            ->putJson("/api/orders/{$otherOrder->id}", ['status' => 'cancelled'])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function test_user_can_delete_an_order_without_payments(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id, 'total' => 10]);

        $this->withJwt($this->user)
            ->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_cannot_delete_order_with_payments(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id, 'total' => 10]);
        Payment::factory()->create(['order_id' => $order->id]);

        $this->withJwt($this->user)
            ->deleteJson("/api/orders/{$order->id}")
            ->assertStatus(422);
    }

    public function test_user_cannot_delete_other_users_order(): void
    {
        $otherUser  = User::factory()->create();
        $otherOrder = Order::factory()->create(['user_id' => $otherUser->id, 'total' => 10]);

        $this->withJwt($this->user)
            ->deleteJson("/api/orders/{$otherOrder->id}")
            ->assertStatus(403);
    }
}

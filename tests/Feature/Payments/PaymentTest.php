<?php

namespace Tests\Feature\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Process payment
    // -------------------------------------------------------------------------

    public function test_can_process_payment_for_confirmed_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'confirmed',
            'total'   => 49.99,
        ]);

        $response = $this->withJwt($this->user)
            ->postJson('/api/payments', [
                'order_id'       => $order->id,
                'payment_method' => 'credit_card',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'order_id', 'payment_method', 'payment_reference', 'status', 'created_at',
            ])
            ->assertJsonFragment(['status' => 'successful', 'payment_method' => 'credit_card']);
    }

    public function test_cannot_process_payment_for_pending_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'pending',
            'total'   => 49.99,
        ]);

        $this->withJwt($this->user)
            ->postJson('/api/payments', [
                'order_id'       => $order->id,
                'payment_method' => 'credit_card',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Payments can only be processed for confirmed orders.']);
    }

    public function test_cannot_process_payment_for_cancelled_order(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'cancelled',
            'total'   => 49.99,
        ]);

        $this->withJwt($this->user)
            ->postJson('/api/payments', [
                'order_id'       => $order->id,
                'payment_method' => 'paypal',
            ])
            ->assertStatus(422);
    }

    public function test_can_process_paypal_payment(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'confirmed',
            'total'   => 25.00,
        ]);

        $response = $this->withJwt($this->user)
            ->postJson('/api/payments', [
                'order_id'       => $order->id,
                'payment_method' => 'paypal',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['payment_method' => 'paypal', 'status' => 'successful']);
    }

    public function test_payment_method_validation_rejects_unknown_gateway(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status'  => 'confirmed',
            'total'   => 10.00,
        ]);

        $this->withJwt($this->user)
            ->postJson('/api/payments', [
                'order_id'       => $order->id,
                'payment_method' => 'bitcoin',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_user_cannot_pay_for_another_users_order(): void
    {
        $otherUser  = User::factory()->create();
        $otherOrder = Order::factory()->create([
            'user_id' => $otherUser->id,
            'status'  => 'confirmed',
            'total'   => 10.00,
        ]);

        $this->withJwt($this->user)
            ->postJson('/api/payments', [
                'order_id'       => $otherOrder->id,
                'payment_method' => 'credit_card',
            ])
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    public function test_can_list_own_payments(): void
    {
        Payment::factory()->count(3)->create([
            'order_id' => Order::factory()->create(['user_id' => $this->user->id, 'total' => 10])->id,
        ]);

        $this->withJwt($this->user)
            ->getJson('/api/payments')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_payments_list_excludes_other_users_payments(): void
    {
        $otherUser = User::factory()->create();
        Payment::factory()->count(2)->create([
            'order_id' => Order::factory()->create(['user_id' => $otherUser->id, 'total' => 10])->id,
        ]);

        $myOrder = Order::factory()->create(['user_id' => $this->user->id, 'total' => 10]);
        $myPayment = Payment::factory()->create(['order_id' => $myOrder->id]);

        $response = $this->withJwt($this->user)->getJson('/api/payments');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertCount(1, $ids);
        $this->assertTrue($ids->contains($myPayment->id));
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_can_view_single_payment(): void
    {
        $order   = Order::factory()->create(['user_id' => $this->user->id, 'total' => 10]);
        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $this->withJwt($this->user)
            ->getJson("/api/payments/{$payment->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $payment->id]);
    }

    public function test_user_cannot_view_other_users_payment(): void
    {
        $otherUser  = User::factory()->create();
        $otherOrder = Order::factory()->create(['user_id' => $otherUser->id, 'total' => 10]);
        $payment    = Payment::factory()->create(['order_id' => $otherOrder->id]);

        $this->withJwt($this->user)
            ->getJson("/api/payments/{$payment->id}")
            ->assertStatus(403);
    }

    public function test_gateway_response_is_not_exposed(): void
    {
        $order   = Order::factory()->create(['user_id' => $this->user->id, 'total' => 10]);
        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $response = $this->withJwt($this->user)
            ->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('gateway_response', $response->json());
    }

    // -------------------------------------------------------------------------
    // Order payments
    // -------------------------------------------------------------------------

    public function test_can_list_payments_for_an_order(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id, 'total' => 10]);
        Payment::factory()->count(2)->create(['order_id' => $order->id]);

        $response = $this->withJwt($this->user)
            ->getJson("/api/orders/{$order->id}/payments");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_order_payments_returns_404_for_missing_order(): void
    {
        $this->withJwt($this->user)
            ->getJson('/api/orders/9999/payments')
            ->assertStatus(404);
    }

    public function test_user_cannot_list_payments_for_other_users_order(): void
    {
        $otherUser  = User::factory()->create();
        $otherOrder = Order::factory()->create(['user_id' => $otherUser->id, 'total' => 10]);
        Payment::factory()->count(2)->create(['order_id' => $otherOrder->id]);

        $this->withJwt($this->user)
            ->getJson("/api/orders/{$otherOrder->id}/payments")
            ->assertStatus(403);
    }
}

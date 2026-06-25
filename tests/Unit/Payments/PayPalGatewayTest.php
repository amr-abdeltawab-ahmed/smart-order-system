<?php

namespace Tests\Unit\Payments;

use App\Payments\Gateways\PayPalGateway;
use PHPUnit\Framework\TestCase;

class PayPalGatewayTest extends TestCase
{
    private PayPalGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new PayPalGateway('client_id', 'client_secret', 'sandbox');
    }

    public function test_get_name_returns_paypal(): void
    {
        $this->assertSame('paypal', $this->gateway->getName());
    }

    public function test_process_returns_successful_result(): void
    {
        $result = $this->gateway->process([
            'order_id' => 1,
            'amount' => 99.00,
            'currency' => 'USD',
        ]);

        $this->assertArrayHasKey('reference', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('raw', $result);
        $this->assertSame('successful', $result['status']);
        $this->assertStringStartsWith('PP-', $result['reference']);
    }

    public function test_process_raw_includes_mode(): void
    {
        $result = $this->gateway->process(['order_id' => 1, 'amount' => 10.0, 'currency' => 'USD']);

        $this->assertSame('sandbox', $result['raw']['mode']);
    }

    public function test_process_raw_contains_gateway_name(): void
    {
        $result = $this->gateway->process(['order_id' => 1, 'amount' => 10.0, 'currency' => 'USD']);

        $this->assertSame('paypal', $result['raw']['gateway']);
    }
}

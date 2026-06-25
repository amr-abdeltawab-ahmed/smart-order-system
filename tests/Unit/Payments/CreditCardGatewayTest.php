<?php

namespace Tests\Unit\Payments;

use App\Payments\Gateways\CreditCardGateway;
use PHPUnit\Framework\TestCase;

class CreditCardGatewayTest extends TestCase
{
    private CreditCardGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new CreditCardGateway('test_key', 'test_secret');
    }

    public function test_get_name_returns_credit_card(): void
    {
        $this->assertSame('credit_card', $this->gateway->getName());
    }

    public function test_process_returns_successful_result(): void
    {
        $result = $this->gateway->process([
            'order_id' => 1,
            'amount' => 49.99,
            'currency' => 'USD',
        ]);

        $this->assertArrayHasKey('reference', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('raw', $result);
        $this->assertSame('successful', $result['status']);
        $this->assertStringStartsWith('CC-', $result['reference']);
    }

    public function test_process_raw_contains_gateway_name(): void
    {
        $result = $this->gateway->process(['order_id' => 1, 'amount' => 10.0, 'currency' => 'USD']);

        $this->assertSame('credit_card', $result['raw']['gateway']);
    }

    public function test_process_raw_reflects_amount(): void
    {
        $result = $this->gateway->process(['order_id' => 1, 'amount' => 75.50, 'currency' => 'USD']);

        $this->assertSame(75.50, $result['raw']['amount']);
    }
}

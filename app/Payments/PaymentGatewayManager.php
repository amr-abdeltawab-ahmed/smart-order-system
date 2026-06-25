<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGatewayInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGatewayInterface>> */
    private array $gateways = [];

    public function __construct(private readonly Container $app) {}

    /**
     * Register a gateway implementation for the given method name.
     *
     * @param  class-string<PaymentGatewayInterface>  $gatewayClass
     */
    public function extend(string $method, string $gatewayClass): void
    {
        $this->gateways[$method] = $gatewayClass;
    }

    /**
     * Resolve a gateway instance by payment method name.
     */
    public function driver(string $method): PaymentGatewayInterface
    {
        if (! isset($this->gateways[$method])) {
            throw new InvalidArgumentException("Payment gateway [{$method}] is not supported.");
        }

        return $this->app->make($this->gateways[$method]);
    }

    /**
     * Return all registered method names.
     *
     * @return list<string>
     */
    public function supported(): array
    {
        return array_keys($this->gateways);
    }
}

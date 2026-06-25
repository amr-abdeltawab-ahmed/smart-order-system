<?php

namespace App\Payments\Gateways;

use App\Payments\Contracts\PaymentGatewayInterface;

class PayPalGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $mode,
    ) {}

    public function process(array $data): array
    {
        // Simulated PayPal processing — replace with real PayPal SDK call
        $reference = 'PP-'.strtoupper(uniqid());

        return [
            'reference' => $reference,
            'status' => 'successful',
            'raw' => [
                'gateway' => $this->getName(),
                'order_id' => $reference,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'mode' => $this->mode,
            ],
        ];
    }

    public function getName(): string
    {
        return 'paypal';
    }
}

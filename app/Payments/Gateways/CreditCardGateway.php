<?php

namespace App\Payments\Gateways;

use App\Payments\Contracts\PaymentGatewayInterface;

class CreditCardGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
    ) {}

    public function process(array $data): array
    {
        // Simulated credit card processing — replace with real SDK call
        $reference = 'CC-'.strtoupper(uniqid());

        return [
            'reference' => $reference,
            'status' => 'successful',
            'raw' => [
                'gateway' => $this->getName(),
                'transaction_id' => $reference,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
            ],
        ];
    }

    public function getName(): string
    {
        return 'credit_card';
    }
}

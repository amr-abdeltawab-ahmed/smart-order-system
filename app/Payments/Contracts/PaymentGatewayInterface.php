<?php

namespace App\Payments\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Process a payment.
     *
     * @param  array{amount: float, currency: string, metadata: array}  $data
     * @return array{reference: string, status: string, raw: array}
     */
    public function process(array $data): array;

    public function getName(): string;
}

<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentFailedException extends RuntimeException
{
    public function __construct(string $reason = 'Payment processing failed. Please try again.')
    {
        parent::__construct($reason);
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;

class PaymentNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct("Payment #{$id} not found.");
    }
}

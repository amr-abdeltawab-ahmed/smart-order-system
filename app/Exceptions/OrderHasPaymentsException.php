<?php

namespace App\Exceptions;

use RuntimeException;

class OrderHasPaymentsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cannot delete an order that has associated payments.');
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;

class OrderNotConfirmedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Payments can only be processed for confirmed orders.');
    }
}

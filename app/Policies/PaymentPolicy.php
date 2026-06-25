<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        $ownerId = $payment->relationLoaded('order')
            ? $payment->order?->user_id
            : $payment->order()->value('user_id');

        return $user->id === $ownerId;
    }
}

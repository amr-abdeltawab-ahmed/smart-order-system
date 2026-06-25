<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Payment::with('order')
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id): ?Payment
    {
        return Payment::with('order')->find($id);
    }

    public function findByOrder(int $orderId, int $perPage = 15): LengthAwarePaginator
    {
        return Payment::where('order_id', $orderId)
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);

        return $payment->fresh('order');
    }
}

<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface
{
    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id): ?Payment;

    public function findByOrder(int $orderId, int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Payment;

    public function update(Payment $payment, array $data): Payment;
}

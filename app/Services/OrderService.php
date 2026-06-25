<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\OrderHasPaymentsException;
use App\Exceptions\OrderNotFoundException;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {}

    public function listOrders(int $userId, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->orderRepository->paginateForUser($userId, $status, $perPage);
    }

    public function findOrder(int $id): Order
    {
        $order = $this->orderRepository->findById($id);

        if (! $order) {
            throw new OrderNotFoundException($id);
        }

        return $order;
    }

    public function createOrder(int $userId, array $items): Order
    {
        return DB::transaction(function () use ($userId, $items) {
            $total = $this->calculateTotal($items);

            $order = $this->orderRepository->create([
                'user_id' => $userId,
                'status'  => OrderStatus::Pending->value,
                'total'   => $total,
            ]);

            $order->items()->createMany(
                array_map(fn ($item) => [
                    'product_name' => $item['product_name'],
                    'quantity'     => $item['quantity'],
                    'price'        => $item['price'],
                    'subtotal'     => round($item['quantity'] * $item['price'], 2),
                ], $items)
            );

            return $order->load(['items', 'payments']);
        });
    }

    public function updateOrder(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $updateData = [];

            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            if (isset($data['items'])) {
                $order->items()->delete();

                $total = $this->calculateTotal($data['items']);
                $updateData['total'] = $total;

                $order->items()->createMany(
                    array_map(fn ($item) => [
                        'product_name' => $item['product_name'],
                        'quantity'     => $item['quantity'],
                        'price'        => $item['price'],
                        'subtotal'     => round($item['quantity'] * $item['price'], 2),
                    ], $data['items'])
                );
            }

            return $this->orderRepository->update($order, $updateData);
        });
    }

    public function deleteOrder(Order $order): void
    {
        if ($order->hasPayments()) {
            throw new OrderHasPaymentsException;
        }

        $this->orderRepository->delete($order);
    }

    private function calculateTotal(array $items): float
    {
        return round(
            array_reduce($items, fn ($carry, $item) => $carry + ($item['quantity'] * $item['price']), 0),
            2
        );
    }
}

<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Exceptions\OrderNotConfirmedException;
use App\Exceptions\PaymentFailedException;
use App\Exceptions\PaymentNotFoundException;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentGatewayManager;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentGatewayManager $gatewayManager,
    ) {}

    public function listPayments(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paymentRepository->paginateForUser($userId, $perPage);
    }

    public function listOrderPayments(int $orderId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paymentRepository->findByOrder($orderId, $perPage);
    }

    public function findPayment(int $id): Payment
    {
        $payment = $this->paymentRepository->findById($id);

        if (! $payment) {
            throw new PaymentNotFoundException($id);
        }

        return $payment;
    }

    public function processPayment(Order $order, string $paymentMethod): Payment
    {
        if (! $order->isConfirmed()) {
            throw new OrderNotConfirmedException;
        }

        $gateway = $this->gatewayManager->driver($paymentMethod);

        try {
            $result = $gateway->process([
                'order_id' => $order->id,
                'amount'   => $order->total,
                'currency' => 'USD',
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment gateway failure', [
                'gateway'    => $paymentMethod,
                'order_id'   => $order->id,
                'user_id'    => $order->user_id,
                'error'      => $e->getMessage(),
            ]);

            $this->paymentRepository->create([
                'order_id'         => $order->id,
                'payment_method'   => $paymentMethod,
                'payment_reference' => null,
                'status'           => PaymentStatus::Failed->value,
                'gateway_response' => ['error' => $e->getMessage()],
            ]);

            throw new PaymentFailedException;
        }

        $payment = $this->paymentRepository->create([
            'order_id'          => $order->id,
            'payment_method'    => $paymentMethod,
            'payment_reference' => $result['reference'],
            'status'            => $result['status'],
            'gateway_response'  => $result['raw'],
        ]);

        Log::info('Payment processed', [
            'user_id'    => $order->user_id,
            'order_id'   => $order->id,
            'method'     => $paymentMethod,
            'reference'  => $result['reference'],
            'status'     => $result['status'],
        ]);

        return $payment;
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\ProcessPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderService $orderService,
    ) {}

    #[OA\Get(
        path: '/payments',
        summary: 'List authenticated user\'s payments (paginated)',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 100)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of payments'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $payments = $this->paymentService->listPayments(
            userId: auth('api')->id(),
            perPage: $perPage,
        );

        return PaymentResource::collection($payments);
    }

    #[OA\Post(
        path: '/payments',
        summary: 'Process a payment for a confirmed order',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['order_id', 'payment_method'],
                properties: [
                    new OA\Property(property: 'order_id', type: 'integer', example: 1),
                    new OA\Property(property: 'payment_method', type: 'string', enum: ['credit_card', 'paypal'], example: 'credit_card'),
                ],
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Payment processed', content: new OA\JsonContent(ref: '#/components/schemas/PaymentResource')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Order not confirmed or validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(ProcessPaymentRequest $request): JsonResponse
    {
        $order = $this->orderService->findOrder($request->validated('order_id'));
        $this->authorize('view', $order);

        $payment = $this->paymentService->processPayment($order, $request->validated('payment_method'));

        return response()->json(new PaymentResource($payment), 201);
    }

    #[OA\Get(
        path: '/payments/{id}',
        summary: 'Get a single payment',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Payment detail', content: new OA\JsonContent(ref: '#/components/schemas/PaymentResource')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $payment = $this->paymentService->findPayment($id);
        $this->authorize('view', $payment);

        return response()->json(new PaymentResource($payment));
    }

    #[OA\Get(
        path: '/orders/{order}/payments',
        summary: 'List payments for a specific order',
        security: [['bearerAuth' => []]],
        tags: ['Payments'],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated payments for the order'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Order not found'),
        ]
    )]
    public function orderPayments(Request $request, int $order): AnonymousResourceCollection
    {
        $orderModel = $this->orderService->findOrder($order);
        $this->authorize('view', $orderModel);

        $perPage = min((int) $request->query('per_page', 15), 100);

        $payments = $this->paymentService->listOrderPayments(
            orderId: $order,
            perPage: $perPage,
        );

        return PaymentResource::collection($payments);
    }
}

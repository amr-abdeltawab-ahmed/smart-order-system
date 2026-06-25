<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\IndexOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    #[OA\Get(
        path: '/orders',
        summary: 'List authenticated user\'s orders (paginated)',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'confirmed', 'cancelled'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15, maximum: 100)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of orders'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(IndexOrderRequest $request): AnonymousResourceCollection
    {
        $orders = $this->orderService->listOrders(
            userId: auth('api')->id(),
            status: $request->validated('status'),
            perPage: (int) $request->validated('per_page', 15),
        );

        return OrderResource::collection($orders);
    }

    #[OA\Post(
        path: '/orders',
        summary: 'Create a new order',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['items'],
                properties: [
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            required: ['product_name', 'quantity', 'price'],
                            properties: [
                                new OA\Property(property: 'product_name', type: 'string', example: 'Widget A'),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                new OA\Property(property: 'price', type: 'number', format: 'float', example: 9.99),
                            ],
                            type: 'object',
                        ),
                    ),
                ],
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Order created', content: new OA\JsonContent(ref: '#/components/schemas/OrderResource')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createOrder(
            userId: auth('api')->id(),
            items: $request->validated('items'),
        );

        return response()->json(new OrderResource($order), 201);
    }

    #[OA\Get(
        path: '/orders/{id}',
        summary: 'Get a single order',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Order detail', content: new OA\JsonContent(ref: '#/components/schemas/OrderResource')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $order = $this->orderService->findOrder($id);
        $this->authorize('view', $order);

        return response()->json(new OrderResource($order));
    }

    #[OA\Put(
        path: '/orders/{id}',
        summary: 'Update an order\'s status or items',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'cancelled']),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'product_name', type: 'string'),
                                new OA\Property(property: 'quantity', type: 'integer'),
                                new OA\Property(property: 'price', type: 'number', format: 'float'),
                            ],
                            type: 'object',
                        ),
                    ),
                ],
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Order updated', content: new OA\JsonContent(ref: '#/components/schemas/OrderResource')),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function update(UpdateOrderRequest $request, int $id): JsonResponse
    {
        $order = $this->orderService->findOrder($id);
        $this->authorize('update', $order);

        $order = $this->orderService->updateOrder($order, $request->validated());

        return response()->json(new OrderResource($order));
    }

    #[OA\Delete(
        path: '/orders/{id}',
        summary: 'Delete an order (only if no payments are associated)',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Order deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Order has payments and cannot be deleted'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $order = $this->orderService->findOrder($id);
        $this->authorize('delete', $order);

        $this->orderService->deleteOrder($order);

        return response()->json(['message' => 'Order deleted successfully.']);
    }
}

# Smart Order System — API Reference

Base URL: `http://localhost:8090/api`  
All protected endpoints require: `Authorization: Bearer <token>`  
All requests/responses use `Content-Type: application/json`.

---

## Auth

### POST /auth/register
Create a new account and receive a JWT.

**Request**
```json
{
  "name": "Alice",
  "email": "alice@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```
**Response `201`**
```json
{
  "user": { "id": 1, "name": "Alice", "email": "alice@example.com", "created_at": "…" },
  "token": "<jwt>",
  "token_type": "bearer"
}
```

---

### POST /auth/login
**Request**
```json
{ "email": "alice@example.com", "password": "password123" }
```
**Response `200`**
```json
{ "token": "<jwt>", "token_type": "bearer" }
```
**Response `401`** — wrong credentials
```json
{ "message": "Invalid credentials." }
```

---

### GET /auth/me  *(auth)*
**Response `200`**
```json
{ "id": 1, "name": "Alice", "email": "alice@example.com", "created_at": "…" }
```

---

### POST /auth/refresh  *(auth)*
Invalidates current token and issues a new one.

**Response `200`**
```json
{ "token": "<new-jwt>", "token_type": "bearer" }
```

---

### POST /auth/logout  *(auth)*
**Response `200`**
```json
{ "message": "Successfully logged out." }
```

---

## Orders

### GET /orders  *(auth)*
Paginated list of the authenticated user's orders.

**Query params**
| Param      | Values                           | Default |
|------------|----------------------------------|---------|
| `status`   | `pending` `confirmed` `cancelled`| —       |
| `per_page` | integer                          | 15      |

**Response `200`**
```json
{
  "data": [
    {
      "id": 1,
      "status": "pending",
      "total": 39.97,
      "items": [ … ],
      "created_at": "…",
      "updated_at": "…"
    }
  ],
  "links": { "first": "…", "last": "…", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 }
}
```

---

### POST /orders  *(auth)*
Total is auto-calculated from `quantity × price` per item.

**Request**
```json
{
  "items": [
    { "product_name": "Widget A", "quantity": 2, "price": 9.99 },
    { "product_name": "Widget B", "quantity": 1, "price": 19.99 }
  ]
}
```
**Response `201`**
```json
{
  "id": 1,
  "status": "pending",
  "total": 39.97,
  "items": [
    { "id": 1, "product_name": "Widget A", "quantity": 2, "price": 9.99, "subtotal": 19.98 },
    { "id": 2, "product_name": "Widget B", "quantity": 1, "price": 19.99, "subtotal": 19.99 }
  ],
  "created_at": "…",
  "updated_at": "…"
}
```
**Response `422`** — validation error
```json
{ "message": "…", "errors": { "items": ["The items field is required."] } }
```

---

### GET /orders/{id}  *(auth)*
**Response `200`** — same shape as POST response, includes `payments` array.  
**Response `404`** — order not found.

---

### PUT /orders/{id}  *(auth)*
Update status and/or replace items.

**Request** (all fields optional)
```json
{
  "status": "confirmed",
  "items": [
    { "product_name": "Widget A", "quantity": 3, "price": 9.99 }
  ]
}
```
**Response `200`** — updated order resource.

---

### DELETE /orders/{id}  *(auth)*
**Response `200`** — `{ "message": "Order deleted." }`  
**Response `422`** — order has associated payments
```json
{ "message": "Cannot delete an order that has payments." }
```

---

### GET /orders/{id}/payments  *(auth)*
List all payments for the given order.

**Response `200`**
```json
{
  "data": [ { "id": 1, "order_id": 1, "payment_method": "credit_card", "payment_reference": "CC-…", "status": "successful", "gateway_response": { … }, "created_at": "…" } ],
  "links": { … },
  "meta": { … }
}
```
**Response `404`** — order not found.

---

## Payments

### GET /payments  *(auth)*
Paginated list of all payments (supports `?per_page=`).

**Response `200`** — same paginated shape as orders list.

---

### POST /payments  *(auth)*
Process a payment. The order must be in `confirmed` status.

**Request**
```json
{
  "order_id": 1,
  "payment_method": "credit_card"
}
```
Supported `payment_method` values: `credit_card`, `paypal`

**Response `201`**
```json
{
  "id": 1,
  "order_id": 1,
  "payment_method": "credit_card",
  "payment_reference": "CC-ABC123",
  "status": "successful",
  "gateway_response": {
    "gateway": "credit_card",
    "transaction_id": "cc-…",
    "amount": 39.97,
    "currency": "USD"
  },
  "created_at": "…"
}
```
**Response `422`** — order not confirmed
```json
{ "message": "Payments can only be processed for confirmed orders." }
```
**Response `422`** — unknown gateway
```json
{ "message": "…", "errors": { "payment_method": ["The selected payment method is invalid."] } }
```

---

### GET /payments/{id}  *(auth)*
**Response `200`** — single payment resource.  
**Response `404`** — payment not found.

---

## Common Error Shapes

| Status | Shape |
|--------|-------|
| `401`  | `{ "message": "Unauthenticated." }` |
| `403`  | `{ "message": "Forbidden." }` |
| `404`  | `{ "message": "Not found." }` |
| `422`  | `{ "message": "…", "errors": { "field": ["reason"] } }` |
| `500`  | `{ "message": "Server error." }` |

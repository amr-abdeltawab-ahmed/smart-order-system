# Smart Order System API

A RESTful API for order and payment management built with Laravel 12. Features JWT authentication, an extensible payment gateway system using the Strategy pattern, Swagger documentation, and a Controller → Service → Repository architecture.

---

## Table of Contents

- [Requirements](#requirements)
- [Local Setup (without Docker)](#local-setup-without-docker)
- [Docker Setup](#docker-setup)
- [Running Tests](#running-tests)
- [API Documentation (Swagger)](#api-documentation-swagger)
- [Payment Gateway Extensibility](#payment-gateway-extensibility)
- [API Overview](#api-overview)
- [Assumptions & Notes](#assumptions--notes)

---

## Requirements

- PHP 8.2+
- Composer
- MySQL 8.0+
- (Optional) Docker & Docker Compose

---

## Local Setup (without Docker)

```bash
# 1. Clone the repository
git clone <repository-url>
cd smart-order-system

# 2. Install dependencies
composer install

# 3. Copy environment file and configure
cp .env.example .env

# Edit .env — set your MySQL credentials:
# DB_DATABASE=smart_order
# DB_USERNAME=root
# DB_PASSWORD=your_password

# 4. Generate application key
php artisan key:generate

# 5. Generate JWT secret
php artisan jwt:secret

# 6. Run migrations
php artisan migrate

# 7. Start the development server
php artisan serve
```

The API will be available at `http://localhost:8000/api`.

---

## Docker Setup

```bash
# 1. Copy and configure environment
cp .env.example .env

# Set these values in .env for Docker:
# DB_HOST=db
# DB_DATABASE=smart_order
# DB_USERNAME=laravel
# DB_PASSWORD=secret

# 2. Build and start containers
docker compose up -d --build

# 3. Generate application key and JWT secret
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret

# 4. Run migrations
docker compose exec app php artisan migrate
```

The API will be available at `http://localhost:8090/api`.

**Services:**

| Service    | Container            | Port        |
|------------|----------------------|-------------|
| PHP-FPM    | `smart_order_app`    | internal    |
| Nginx      | `smart_order_nginx`  | `8090:80`   |
| MySQL 8.0  | `smart_order_db`     | `3307:3306` |

---

## Running Tests

Tests use an in-memory SQLite database — no additional setup needed.

```bash
# Local
php artisan test

# Docker
docker compose exec app php artisan test

# Run only a specific suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
```

### API smoke test (curl)

Requires a running server and `python3` on PATH:

```bash
# Against Docker (default)
bash scripts/test-api.sh

# Against a custom URL
bash scripts/test-api.sh http://localhost:8000/api
```

The script registers a user, exercises every endpoint in a realistic flow (create → confirm → pay → delete guard), and prints a colour-coded pass/fail summary.

**Test coverage:**

| Suite   | File                    | What it tests                                        |
|---------|-------------------------|------------------------------------------------------|
| Unit    | `CreditCardGatewayTest` | Gateway name, reference format, response shape       |
| Unit    | `PayPalGatewayTest`     | Gateway name, sandbox mode, response shape           |
| Feature | `AuthTest`              | Register, login, logout, profile, bad credentials    |
| Feature | `OrderTest`             | CRUD, total calculation, status filter, delete guard |
| Feature | `PaymentTest`           | Process payment, confirmed-status guard, list, show  |

---

## API Documentation (Swagger)

Generate the Swagger spec, then open the UI:

```bash
php artisan l5-swagger:generate
```

Swagger UI: **`http://localhost:8000/api/documentation`** (or `http://localhost:8090/api/documentation` with Docker)

The raw JSON spec is served at `/api/docs`.

---

## Payment Gateway Extensibility

The payment system uses the **Strategy Pattern** via `PaymentGatewayManager`. Adding a new gateway requires **three steps and zero changes to existing code**:

### Step 1 — Implement the interface

```php
// app/Payments/Gateways/StripeGateway.php

namespace App\Payments\Gateways;

use App\Payments\Contracts\PaymentGatewayInterface;

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly string $secretKey) {}

    public function process(array $data): array
    {
        // Call the Stripe SDK here
        $reference = 'ST-' . strtoupper(uniqid());

        return [
            'reference' => $reference,
            'status'    => 'successful',
            'raw'       => ['gateway' => $this->getName(), 'amount' => $data['amount']],
        ];
    }

    public function getName(): string
    {
        return 'stripe';
    }
}
```

### Step 2 — Add credentials to config

```php
// config/services.php
'stripe' => [
    'secret_key' => env('STRIPE_SECRET_KEY'),
],
```

```dotenv
# .env
STRIPE_SECRET_KEY=sk_test_...
```

### Step 3 — Register in AppServiceProvider

```php
// app/Providers/AppServiceProvider.php  →  boot()

$this->app->bind(StripeGateway::class, fn () => new StripeGateway(
    config('services.stripe.secret_key'),
));

$this->app->make(PaymentGatewayManager::class)->extend('stripe', StripeGateway::class);
```

The `stripe` method is now accepted by `POST /api/payments` automatically — `ProcessPaymentRequest` reads supported methods dynamically from the manager.

---

## API Overview

All endpoints (except register and login) require:
```
Authorization: Bearer <token>
```

### Auth

| Method | Endpoint             | Description               |
|--------|----------------------|---------------------------|
| POST   | `/api/auth/register` | Register a new user       |
| POST   | `/api/auth/login`    | Login and receive a token |
| POST   | `/api/auth/logout`   | Invalidate the token      |
| POST   | `/api/auth/refresh`  | Refresh the token         |
| GET    | `/api/auth/me`       | Get authenticated user    |

### Orders

| Method | Endpoint           | Description                            |
|--------|--------------------|----------------------------------------|
| GET    | `/api/orders`      | List orders (`?status=`, `?per_page=`) |
| POST   | `/api/orders`      | Create order (items array required)    |
| GET    | `/api/orders/{id}` | Show order with items and payments     |
| PUT    | `/api/orders/{id}` | Update status or replace items         |
| DELETE | `/api/orders/{id}` | Delete (fails if payments exist)       |

### Payments

| Method | Endpoint                    | Description                               |
|--------|-----------------------------|-------------------------------------------|
| GET    | `/api/payments`             | List all payments (`?per_page=`)          |
| POST   | `/api/payments`             | Process payment (order must be confirmed) |
| GET    | `/api/payments/{id}`        | Show payment detail                       |
| GET    | `/api/orders/{id}/payments` | List payments for a specific order        |

### Example: Create and pay for an order

```bash
# 1. Register
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice","email":"alice@example.com","password":"password123","password_confirmation":"password123"}'

# 2. Create an order
curl -X POST http://localhost:8000/api/orders \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"items":[{"product_name":"Widget","quantity":2,"price":19.99}]}'

# 3. Confirm the order
curl -X PUT http://localhost:8000/api/orders/1 \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"status":"confirmed"}'

# 4. Process payment
curl -X POST http://localhost:8000/api/payments \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"order_id":1,"payment_method":"credit_card"}'
```

---

## Assumptions & Notes

- **Payment simulation** — both `CreditCardGateway` and `PayPalGateway` simulate successful charges. Replace their `process()` methods with real SDK calls to go live.
- **Order ownership** — `GET /orders` returns only the authenticated user's orders. The show/update/delete endpoints do not enforce ownership; add a policy if strict multi-tenant isolation is required.
- **Payment status** — payments are always created as `successful` in simulation. A real gateway may return `failed`; the schema supports it.
- **Pagination** — all list endpoints return Laravel's standard paginated response with `data`, `links`, and `meta` keys.
- **Token expiry** — JWT TTL defaults to 60 minutes (configurable in `config/jwt.php` via `JWT_TTL`). Use `POST /auth/refresh` before expiry.
- **Swagger generation** — run `php artisan l5-swagger:generate` after any annotation change. The output lands in `storage/api-docs/api-docs.json`.

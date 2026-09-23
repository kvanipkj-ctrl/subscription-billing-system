# Subscription Billing & Usage-Metering System

Laravel MVP for a multi-tenant subscription billing system. Merchants own plans and customers; customers subscribe to plans, submit usage, and receive invoices with included-unit and overage pricing.

## Architecture

- **Eloquent models:** merchants, plans, customers, subscriptions, usage events, daily usage, invoices, and invoice lines.
- **Form Requests:** validate API input and relationship identifiers.
- **Services:** keep usage ingestion, daily aggregation, subscription lifecycle, and invoice generation out of controllers.
- **Database constraints:** enforce tenant-scoped customer identifiers, usage idempotency, daily aggregates, and invoice-period uniqueness.
- **Test suite:** PHPUnit feature and unit tests using SQLite in-memory where applicable.

## Requirements

- PHP 8.3+
- Laravel 13.x (the current project uses Laravel 13.33)
- Composer
- MySQL 8+
- SQLite PHP extensions for the default test configuration (`pdo_sqlite` and `sqlite3`)

Redis, queues, and authentication are not required for this MVP.

## Installation

From the project root:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Edit `.env` with local database settings. Never commit `.env` or credentials.

## Database Setup

Create a MySQL database named `subscription_billing`, then configure:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=subscription_billing
DB_USERNAME=root
DB_PASSWORD=your-local-password
```

Run migrations:

```powershell
php artisan config:clear
php artisan migrate
php artisan migrate:status
```

The migration set creates merchants, plans, customers, subscriptions, usage events, daily usage, invoices, and invoice lines. It includes foreign keys, tenant-scoped indexes, usage idempotency uniqueness, daily aggregate uniqueness, and invoice billing-period uniqueness.

## Tests

```powershell
php artisan test
```

The test environment uses SQLite in-memory where applicable. Ensure the PHP CLI has `pdo_sqlite` and `sqlite3` enabled.

## API

There is no authentication in this MVP. `merchant_id` is supplied explicitly as temporary tenant context and is checked against related records.

### Create a customer

`POST /api/customers`

```json
{
  "merchant_id": 1,
  "external_id": "customer-001",
  "name": "Ada Lovelace",
  "email": "ada@example.com"
}
```

Response: `201 Created` with `status` and the created customer in `data`.

### Create a plan

`POST /api/plans`

```json
{
  "merchant_id": 1,
  "name": "Pro",
  "description": "Professional plan",
  "currency": "USD",
  "billing_interval": "monthly",
  "base_price": "29.00",
  "included_units": 1000,
  "overage_unit_price": "0.05",
  "active": true
}
```

### Create a subscription

`POST /api/subscriptions`

```json
{
  "merchant_id": 1,
  "customer_id": 1,
  "plan_id": 1,
  "starts_at": "2026-09-01 00:00:00"
}
```

Response: `201 Created` with `status` and the created subscription in `data`.

### Submit usage

`POST /api/usage`

```json
{
  "merchant_id": 1,
  "customer_id": 1,
  "subscription_id": 1,
  "occurred_at": "2026-09-23 12:00:00",
  "quantity": 150,
  "idempotency_key": "usage-2026-09-23-001",
  "metadata": {
    "source": "meter"
  }
}
```

New request: `201 Created`.

```json
{
  "status": "ok",
  "data": {
    "id": 1,
    "merchant_id": 1,
    "customer_id": 1,
    "subscription_id": 1,
    "quantity": 150,
    "idempotency_key": "usage-2026-09-23-001"
  },
  "replayed": false
}
```

An exact retry returns `200 OK` with the existing event and `replayed: true`. Reusing the same key with different request data returns `409 Conflict`. The database unique constraint on `(merchant_id, idempotency_key)` is the final duplicate protection.

### Generate an invoice

`POST /api/invoices`

```json
{
  "merchant_id": 1,
  "subscription_id": 1,
  "billing_period_start": "2026-09-01 00:00:00",
  "billing_period_end": "2026-10-01 00:00:00"
}
```

Response: `201 Created` for a new invoice, or `200 OK` when the same subscription and billing period already has an invoice.

```json
{
  "status": "ok",
  "data": {
    "id": 1,
    "subscription_id": 1,
    "billing_period_start": "2026-09-01T00:00:00.000000Z",
    "billing_period_end": "2026-10-01T00:00:00.000000Z",
    "subtotal": "36.50",
    "total": "36.50",
    "currency": "USD",
    "invoice_lines": [
      {
        "type": "base",
        "quantity": 1,
        "unit_price": "29.00",
        "amount": "29.00"
      }
    ]
  },
  "replayed": false
}
```

### Retrieve an invoice

`GET /api/invoices/{invoice}?merchant_id=1`

Response: `200 OK` with the invoice, customer, subscription, plan, invoice lines, and totals. A merchant mismatch returns `404 Not Found`.

### Query customer usage

`GET /api/customers/{customer}/usage?merchant_id=1&date_from=2026-09-01&date_to=2026-09-30&subscription_id=1`

Response: `200 OK`:

```json
{
  "status": "ok",
  "data": {
    "customer_id": 1,
    "merchant_id": 1,
    "usage": [
      {"usage_date": "2026-09-23", "quantity": 150}
    ]
  }
}
```

## Billing Rules

- Usage is counted for the half-open period `[billing_period_start, billing_period_end)`.
- The plan base price is always billed.
- Usage up to `included_units` is included.
- Usage above the allowance is charged at `overage_unit_price` per unit.
- Money is stored as fixed-precision `DECIMAL(15,2)` values and calculated using integer cents internally.
- Repeated invoice generation for the same merchant, subscription, and billing period returns the existing invoice.

## Tenant Isolation

Domain records carry `merchant_id` where appropriate. Customer external IDs are unique per merchant, usage idempotency keys are unique per merchant, and all ingestion, subscription, invoice, and usage queries verify merchant ownership. Authentication is intentionally deferred, so callers must provide the temporary merchant context.

## Testing Strategy

Tests cover model relationships, API validation, tenant ownership, usage ingestion and replay, database uniqueness, repeatable daily aggregation, included and overage billing, duplicate invoices, invoice retrieval, and date-filtered usage queries.

## Known Limitations / Deferred Work

- Authentication and authorization are not included in the MVP.
- Multi-process concurrency testing requires a shared MySQL test database.
- Current automated tests use SQLite in-memory where applicable.
- Redis, queues, asynchronous aggregation workers, rate limiting, proration, taxes, payments, refunds, and dashboard APIs are deferred.

# FreelanceFlow

A freelance project management API built with Laravel 13. Track clients, projects, and time logs — then generate invoices with PDF creation offloaded to a Go microservice via Redis.

## Authentication

All API endpoints (except the Go worker callback) are protected with **Laravel Sanctum** token authentication. Tokens expire after **24 hours**.

**Login to get a token:**
```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "freelancer@example.com", "password": "password"}'
```

Response:
```json
{ "token": "1|abc...", "expires_at": "2026-03-17T12:00:00.000000Z" }
```

**Use the token on subsequent requests:**
```bash
curl http://localhost:8080/api/invoices/1 \
  -H "Authorization: Bearer 1|abc..."
```

**Logout (revoke token):**
```bash
curl -X POST http://localhost:8080/api/auth/logout \
  -H "Authorization: Bearer 1|abc..."
```

## Stack

| Layer | Technology |
|---|---|
| API | PHP 8.5 + Laravel 13 |
| Database | PostgreSQL 17 |
| Queue / IPC | Redis 7 |
| File storage | MinIO (S3-compatible) |
| PDF worker | Go (separate service, reads from Redis) |
| Infrastructure | Docker Compose |

## Features

### Clients
Create and manage clients with contact details, company name, address, and tax ID.

### Projects
Attach projects to clients with an hourly rate and a status (`active`, `paused`, `completed`).

### Time Logs
Log time against a project with a description, hours worked, and date. Time logs are considered unbilled until they appear on an invoice.

### Invoices
Trigger invoice generation for a project over a given date range. The API:
- Pulls all unbilled time logs within the range
- Calculates subtotal, tax, and total
- Creates the invoice and its line items
- Pushes a structured JSON payload to the `queues:invoice_generation` Redis key for the Go worker to consume
- Accepts a callback from the Go worker when PDF generation completes or fails

### PDF Generation (Go worker)
The Go worker reads from `queues:invoice_generation`, renders a PDF, uploads it to MinIO, and calls back to `POST /api/invoices/{invoice}/callback`. On success the invoice status becomes `completed` and the PDF path is stored. On failure the status becomes `failed`. Email notifications are sent to the client (success) or freelancer (failure) in both cases.

## API Endpoints

Authentication endpoints (public):
```
POST   /api/auth/login                        Obtain an API token
POST   /api/auth/logout                       Revoke the current token  [requires token]
```

Protected endpoints (require `Authorization: Bearer <token>`):
```
POST   /api/clients                           Create a client
GET    /api/clients/{client}/projects         List projects for a client
POST   /api/clients/{client}/projects         Create a project
POST   /api/projects/{project}/time-logs      Log time on a project
POST   /api/projects/{project}/invoices       Generate an invoice
GET    /api/invoices/{invoice}                Get invoice status and details
```

Go worker endpoint (authenticated via `X-Callback-Secret` header, not a user token):
```
POST   /api/invoices/{invoice}/callback       Go worker callback (PDF done)
```

## Getting Started

### Requirements
- Docker + Docker Compose

### Start the stack

```bash
docker compose up --build -d
```

The `--build` flag triggers the `laravel-setup` service which automatically runs migrations and seeds the database on first start. Subsequent runs skip setup if the database has already been initialised.

Services:

| Service | URL |
|---|---|
| API | http://localhost:8080 |
| MinIO console | http://localhost:9001 |
| PostgreSQL | localhost:5432 |
| Redis | localhost:6379 |

The seeder creates 3 clients, 2–3 active projects each, and 10–20 unbilled time logs per project spread across the last 3 months.

To force a re-run (e.g. after wiping the database), remove the sentinel volume and restart:

```bash
docker volume rm freelance-flow_laravel_setup
docker compose up --build -d
```

### Trigger a test invoice

```bash
# First, get a token (seeder creates this user)
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "freelancer@example.com", "password": "password"}' | jq -r .token)

curl -X POST http://localhost:8080/api/projects/1/invoices \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"start_date": "2026-01-01", "end_date": "2026-03-31"}'
```

Invoice endpoints return [JSON:API](https://jsonapi.org) responses (`Content-Type: application/vnd.api+json`):

```json
{
    "data": {
        "id": "1",
        "type": "Invoices",
        "attributes": {
            "invoice_number": "INV-2026-0001",
            "status": "processing",
            "subtotal": "337.50",
            "tax_rate": "0.1900",
            "tax_amount": "64.13",
            "total": "401.63",
            "due_date": "2026-04-15",
            "pdf_path": null,
            "pdf_generated_at": null,
            "created_at": "2026-03-18T10:00:00+00:00"
        }
    }
}
```

Relationships (`client`, `project`, `items`) are loaded on demand via the `include` query parameter:

```bash
curl http://localhost:8080/api/invoices/1?include=client,project,items \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
    "data": {
        "id": "1",
        "type": "Invoices",
        "attributes": { "...": "..." },
        "relationships": {
            "client": { "data": { "id": "1", "type": "Clients" } },
            "project": { "data": { "id": "1", "type": "Projects" } },
            "items": { "data": [{ "id": "1", "type": "InvoiceItems" }] }
        }
    },
    "included": [
        { "id": "1", "type": "Clients", "attributes": { "name": "Acme Corp", "...": "..." } },
        { "id": "1", "type": "Projects", "attributes": { "name": "Website Redesign", "...": "..." } },
        { "id": "1", "type": "InvoiceItems", "attributes": { "description": "...", "total": "337.50" } }
    ]
}
```

### Inspect the Redis queue

```bash
docker compose exec redis redis-cli LRANGE queues:invoice_generation 0 -1
```

## Go Worker Integration

The Laravel API pushes a JSON payload directly to `queues:invoice_generation` in Redis (no Laravel queue worker required). The Go service should `BLPOP` from that key.

**Payload structure:**

```json
{
    "invoice_id": 1,
    "invoice_number": "INV-2026-0001",
    "client": {
        "name": "Acme Corp",
        "email": "billing@acme.com",
        "company_name": "Acme Corporation",
        "address": "123 Main St",
        "tax_id": "900123456"
    },
    "project": {
        "name": "Website Redesign",
        "hourly_rate": 75.00
    },
    "items": [
        {
            "description": "Homepage design",
            "quantity": 4.5,
            "unit_price": 75.00,
            "total": 337.50
        }
    ],
    "subtotal": 337.50,
    "tax_rate": 0.19,
    "tax_amount": 64.13,
    "total": 401.63,
    "due_date": "2026-04-15",
    "callback_url": "http://nginx/api/invoices/1/callback",
    "callback_secret": "..."
}
```

**Callback** — when done, POST to `callback_url` with `X-Callback-Secret` header:

```json
// Success
{ "status": "completed", "pdf_path": "invoices/INV-2026-0001.pdf" }

// Failure
{ "status": "failed", "error": "reason" }
```

## Running Tests

Tests run against a dedicated `freelanceflow_test` PostgreSQL database (created automatically on first startup).

```bash
docker compose exec php php artisan test --compact
```

## Environment Variables

Key variables in `.env`:

```ini
DB_CONNECTION=pgsql
DB_HOST=postgresql
DB_DATABASE=freelanceflow

QUEUE_CONNECTION=redis
REDIS_HOST=redis

FILESYSTEM_DISK=s3
AWS_ENDPOINT=http://minio:9000
AWS_BUCKET=freelanceflow
AWS_USE_PATH_STYLE_ENDPOINT=true

SANCTUM_TOKEN_EXPIRATION=1440   # token lifetime in minutes (default: 24h)
INVOICE_CALLBACK_SECRET=        # shared secret between Laravel and Go worker
MAIL_MAILER=log                 # emails go to laravel.log in development
```

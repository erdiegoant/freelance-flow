# FreelanceFlow

A freelance project management API built with Laravel 12. Track clients, projects, and time logs — then generate invoices with PDF creation offloaded to a Go microservice via Redis.

## Authentication

All API endpoints (except the Go worker callback and PDF download) are protected with **Laravel Sanctum** token authentication. Tokens expire after **24 hours**.

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
| API | PHP 8.5 + Laravel 12 |
| Database | PostgreSQL 17 |
| Queue / IPC | Redis 7 |
| File storage | MinIO (S3-compatible) |
| PDF worker | Go 1.23 (separate service, reads from Redis) |
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
- Assigns a globally unique sequential invoice number (`INV-YYYY-NNNN`) via an atomic PostgreSQL sequence
- Creates the invoice and its line items
- Pushes a structured JSON payload to the `queues:invoice_generation` Redis key for the Go worker to consume
- Accepts a callback from the Go worker when PDF generation completes or fails

### PDF Generation (Go worker)
The Go worker reads from `queues:invoice_generation`, renders a PDF using `go-pdf/fpdf`, uploads it to MinIO, and calls back to `POST /api/invoices/{invoice}/callback`. On success the invoice status becomes `completed` and the PDF path is stored. On failure the status becomes `failed`. Email notifications are sent to the client (success) or freelancer (failure) in both cases.

The worker runs a configurable pool of goroutines (`WORKER_POOL_SIZE`, default `5`). Each goroutine independently `BLPOP`s from Redis, processes the job, and sends the callback — no shared state between workers.

### PDF Download
On success, the client receives an email with a **temporary signed download link** (`GET /api/invoices/{invoice}/download`) valid for 48 hours. No login is required to use the link — it is cryptographically signed by Laravel and cannot be forged or reused after expiry. The file is streamed from MinIO through the Laravel API.

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

PDF download (public, requires valid signed URL from email):
```
GET    /api/invoices/{invoice}/download       Stream the generated PDF
```

Go worker endpoint (authenticated via `X-Callback-Secret` header + IP check):
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
| MinIO console | http://localhost:9001 (minioadmin / minioadmin) |
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

### Watch the worker

```bash
docker compose logs -f go-worker
```

### Inspect the Redis queue

```bash
docker compose exec redis redis-cli LRANGE queues:invoice_generation 0 -1
```

## Go Worker

The Go worker lives in `go-worker/` and is built and started automatically by Docker Compose.

### Structure

```
go-worker/
├── main.go
├── go.mod
└── internal/
    ├── config/      # Env-based config with required-var validation
    ├── queue/       # Redis BLPOP consumer
    ├── pdf/         # PDF generation (go-pdf/fpdf)
    ├── storage/     # MinIO upload (minio-go/v7)
    ├── callback/    # HTTP POST back to Laravel
    └── worker/      # Goroutine pool + job orchestration
```

### Environment variables (`go-worker/.env`)

```ini
REDIS_ADDR=redis:6379
REDIS_QUEUE_KEY=queues:invoice_generation
MINIO_ENDPOINT=minio:9000
MINIO_ACCESS_KEY=           # required
MINIO_SECRET_KEY=           # required
MINIO_BUCKET=freelanceflow
CALLBACK_SECRET=            # must match Laravel's INVOICE_CALLBACK_SECRET
WORKER_POOL_SIZE=5
```

For local development outside Docker, copy `.env.example` to `.env` and fill in the values. The worker loads `.env` automatically when `DOCKER_ENV` is not set.

### Go worker integration

The Laravel API pushes a JSON payload directly to `queues:invoice_generation` in Redis (no Laravel queue worker required). The Go service `BLPOP`s from that key.

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

SANCTUM_TOKEN_EXPIRATION=1440        # token lifetime in minutes (default: 24h)
INVOICE_CALLBACK_SECRET=             # shared secret between Laravel and Go worker
WORKER_CALLBACK_BASE_URL=http://nginx  # internal Docker hostname for Go worker callbacks

MAIL_MAILER=smtp
MAIL_HOST=host.docker.internal       # use host.docker.internal to reach a local mail client (e.g. Mailpit) running on your machine outside Docker
MAIL_PORT=2525
```

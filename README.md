# CurrencyFlow

CurrencyFlow is a Laravel 12 backend for managing multi-currency payment requests.

Employees can create payment requests in local currencies, the API captures the EUR exchange rate snapshot at creation time, and finance users can approve, reject, or let pending requests expire.

## Features

- OAuth 2.0 Authorization Code Grant with PKCE using Laravel Passport.
- Employee and finance roles.
- UUID public identifiers for API resources.
- Country and currency reference data.
- Payment request creation with live EUR-to-local exchange rate capture.
- Immutable exchange rate snapshots for financial auditability.
- Approval, rejection, and expiration workflows.
- Scheduled expiration of pending requests after the review window passes.
- RESTful JSON API with OpenAPI and Postman documentation.
- Docker Compose setup with PostgreSQL.
- Feature, integration, PostgreSQL, and optional external API tests.

## Stack

- PHP 8.2+
- Laravel 12
- PostgreSQL 16
- Laravel Passport
- Docker Compose
- PHPUnit
- OpenAPI 3.1

## Architecture

CurrencyFlow uses a pragmatic Clean Architecture adapted to Laravel:

- `app/Domain`: business concepts, enums, value objects, data objects, and contracts.
- `app/Application`: use cases and application orchestration.
- `app/Infrastructure`: Eloquent persistence, transactions, and external providers.
- `app/Http`: controllers, form requests, resources, and HTTP entry points.

Important decisions:

- Controllers stay thin and delegate business flows to application use cases.
- Repository contracts live in the domain when persistence behavior protects business rules.
- Eloquent implementations live in infrastructure.
- Payment request approval, rejection, and expiration use transactions and row-level locking to avoid race conditions.
- Exchange rate snapshots are protected both in the Eloquent model and by a PostgreSQL trigger.
- `payment_requests.eur_exchange_rate` stores the EUR to local currency rate used at creation time.

More details:

```text
docs/architecture.md
docs/database-diagram.md
```

## Requirements

- Git
- Docker
- Docker Compose

Local PHP and Composer are optional because the preferred workflow runs inside Docker.

## Quick Start

Clone the repository and enter the project directory.

Copy the environment file if needed:

```bash
cp .env.example .env
```

Start the environment:

```bash
docker compose up -d --build
```

Run migrations and seed local data:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Open the application:

```text
http://localhost:8000
```

## Seeded Access

All seeded users use this password:

```text
password
```

Seeded users:

| Email | Role | Country | Currency |
| --- | --- | --- | --- |
| `test@example.com` | employee | Portugal | EUR |
| `ana.silva@example.com` | employee | Brazil | BRL |
| `john.carter@example.com` | employee | United States | USD |
| `emily.brown@example.com` | employee | United Kingdom | GBP |
| `yuki.tanaka@example.com` | employee | Japan | JPY |
| `marta.kowalska@example.com` | finance | Poland | PLN |

Seeded OAuth public client:

```text
Client ID: 019ec29e-86dc-70bd-9de9-157bc6e2f735
Redirect URI: http://localhost:3000/auth/callback
```

## OAuth PKCE

CurrencyFlow uses Laravel Passport with OAuth 2.0 Authorization Code Grant and PKCE.

The backend provides:

- browser login through `GET /login` and `POST /login`;
- OAuth authorization through `GET /oauth/authorize`;
- token exchange through `POST /oauth/token`;
- authenticated API access through Bearer tokens;
- token revocation through `DELETE /api/tokens/current`.

Finance users can create additional public PKCE clients from the web portal:

```text
http://localhost:8000/developer/oauth-clients
```

The portal creates public clients without `client_secret`, intended for browser-based frontends using Authorization Code with PKCE. Confidential clients, homepage URLs, and secret rotation are intentionally outside this first portal version.

Revoking a client in this portal disables that client for future OAuth flows and also revokes every access token and refresh token already issued to it. This follows Laravel Passport's token revocation guidance and avoids leaving previously issued credentials active after a client has been disabled.

The frontend or API client is responsible for the PKCE flow:

1. Generate a temporary `code_verifier`.
2. Derive a `code_challenge`.
3. Redirect the user to `/oauth/authorize` with the public client ID, redirect URI, requested scopes, code challenge, and state.
4. Receive the authorization `code` on the callback URL.
5. Exchange the authorization code and `code_verifier` at `/oauth/token`.
6. Use `Authorization: Bearer <access_token>` for protected API requests.

Available scopes:

- `payments:read`
- `payments:create`
- `payments:approve`

Postman instructions:

```text
docs/postman/oauth-pkce.md
```

## API Documentation

OpenAPI specification:

```text
docs/openapi.json
```

Postman collection:

```text
docs/postman/CurrencyFlow.postman_collection.json
```

Main API endpoints:

| Method | Endpoint | Auth |
| --- | --- | --- |
| `GET` | `/api/countries` | public |
| `GET` | `/api/currencies` | public |
| `POST` | `/api/register` | public |
| `GET` | `/api/user` | Bearer token |
| `DELETE` | `/api/tokens/current` | Bearer token |
| `POST` | `/api/payment-requests` | `payments:create` |
| `GET` | `/api/payment-requests` | `payments:read` |
| `GET` | `/api/payment-requests/{id}` | `payments:read` |
| `POST` | `/api/payment-requests/{id}/approval` | `payments:approve` + finance role |
| `POST` | `/api/payment-requests/{id}/rejection` | `payments:approve` + finance role |

List payment requests by status:

```http
GET /api/payment-requests?status=pending
GET /api/payment-requests?status=approved
GET /api/payment-requests?status=rejected
GET /api/payment-requests?status=expired
```

## Quick API Examples

Create a payment request:

```bash
curl -X POST "http://localhost:8000/api/payment-requests" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <access_token>" \
  -d '{
    "title": "Conference reimbursement",
    "description": "Hotel and local transportation costs.",
    "amount": "123.45",
    "currency_code": "BRL"
  }'
```

List pending payment requests:

```bash
curl "http://localhost:8000/api/payment-requests?status=pending" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <access_token>"
```

Approve a payment request:

```bash
curl -X POST "http://localhost:8000/api/payment-requests/<payment_request_id>/approval" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <access_token>" \
  -d '{"review_note":"Approved for reimbursement."}'
```

## Scheduler

Pending payment requests expire automatically after the review window passes.

The Docker setup starts a dedicated scheduler container automatically:

```bash
docker compose up -d --build
```

Run expiration manually:

```bash
docker compose exec app php artisan payment-requests:expire-pending
```

If you are not using Docker Compose, run the Laravel scheduler manually:

```bash
php artisan schedule:work
```

The scheduler runs `payment-requests:expire-pending` every minute. The command processes requests in batches and does not overwrite approved or rejected requests.

## Tests

Run the full test suite:

```bash
docker compose exec app php artisan test
```

Run feature tests:

```bash
docker compose exec app php artisan test --testsuite=Feature
```

Useful focused checks:

```bash
docker compose exec app php artisan test --filter=AuthFlowTest
docker compose exec app php artisan test --filter=PaymentRequestApiTest
docker compose exec app php artisan test --filter=PaymentRequestPersistenceTest
docker compose exec app php artisan test --filter=ExpirePendingPaymentRequestsCommandTest
docker compose exec app php artisan test --filter=ExchangeRateProviderTest
```

Run the optional external exchange rate smoke test:

```bash
docker compose exec -e RUN_EXTERNAL_API_TESTS=true app php artisan test --testsuite=External
```

The external smoke test is skipped by default to avoid making the standard suite depend on internet access, provider uptime, or rate limits.

## Useful Commands

Check Laravel:

```bash
docker compose exec app php artisan --version
```

Check PHP:

```bash
docker compose exec app php -v
```

Run migrations and seeders:

```bash
docker compose exec app php artisan migrate --seed
```

Rebuild the local database:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Reference countries and currencies are seeded from `config/reference_data.php`. The currency snapshot is based on the official ExchangeRate-API supported currencies page: https://www.exchangerate-api.com/docs/supported-currencies. Countries are derived from the listed country/region names where a safe two-letter country or territory code is available.

Seeders use upserts, so existing records are updated or inserted, not deleted. This avoids breaking users or payment requests that reference older data.

Reference data is refreshed by updating the versioned snapshot and running:

```bash
docker compose exec app php artisan migrate --seed
```

List routes:

```bash
docker compose exec app php artisan route:list
```

Clear configuration cache:

```bash
docker compose exec app php artisan config:clear
```

## Troubleshooting

### Docker cannot connect to PostgreSQL

Make sure the containers are running:

```bash
docker compose ps
```

If needed, rebuild the environment:

```bash
docker compose up -d --build
```

### Missing application key

The app container generates `APP_KEY` automatically when `.env` does not contain one. If needed, run:

```bash
docker compose exec app php artisan key:generate
```

### Environment values are not reflected

Clear Laravel configuration cache:

```bash
docker compose exec app php artisan config:clear
```

### Database state is stale

Recreate the local schema and seed data:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

### OAuth authorization redirects to localhost:3000

That is the seeded frontend callback URL. Until a separate frontend exists, use the Postman guide to copy the authorization `code` from the callback URL:

```text
docs/postman/oauth-pkce.md
```

### Exchange rate provider fails

Payment request creation returns `503` if the external provider is unavailable or returns an unexpected payload. This is intentional because fake exchange rates must not be used in production flows.

## Demo Script

Suggested walkthrough for a short demo:

1. Start Docker and run `migrate:fresh --seed`.
2. Show the OpenAPI spec and Postman collection.
3. Use Postman to complete OAuth PKCE and store the access token.
4. Call `GET /api/user`.
5. Create a payment request as an employee.
6. List payment requests as employee and show ownership filtering.
7. Authenticate as finance.
8. List all payment requests.
9. Approve or reject a pending request.
10. Show that the scheduler container runs automatically and that finalized requests are not overwritten by expiration.
11. Run `docker compose exec app php artisan test`.

## Development Workflow

- Use one branch per delivery or feature.
- Use branch names in English and kebab-case, such as `feat/payment-request-api`.
- Use Conventional Commits.
- Keep code, docs, API responses, seed data, and commit messages in English.

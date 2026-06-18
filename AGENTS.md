# AGENTS.md

## Purpose

This file is the operational guide for Codex sessions working on CurrencyFlow.

Use it to quickly understand how to run, test, validate, and safely change the project. Keep broader product documentation in `README.md`, API contract details in `docs/openapi.json`, and manual API testing details in `docs/postman/`.

## Project Snapshot

CurrencyFlow is a Laravel 12 backend for multi-currency payment requests.

Everything in the project must be written in English, including code, commit messages, API responses, seed data, README, OpenAPI, and Postman docs.

Core decisions:

- PHP 8.2+.
- PostgreSQL as the main database.
- Docker Compose as the preferred local workflow.
- Laravel Passport with OAuth 2.0 Authorization Code Grant and PKCE.
- RESTful API design principles.
- Pragmatic Clean Architecture adapted to Laravel.
- UUID public identifiers for API resources.
- Employee and finance roles.
- EUR to local currency exchange rate snapshots stored on payment request creation.

## Architecture Map

- `app/Domain`: business concepts, enums, value objects, data objects, and contracts.
- `app/Application`: use cases and application orchestration.
- `app/Infrastructure`: Eloquent repositories, transactions, and external providers.
- `app/Http`: controllers, form requests, resources, and HTTP entry points.
- `app/Models`: Eloquent persistence models.
- `docs`: architecture notes, OpenAPI, database diagram, and Postman files.

Key references:

```text
README.md
docs/architecture.md
docs/database-diagram.md
docs/openapi.json
docs/postman/CurrencyFlow.postman_collection.json
docs/postman/oauth-pkce.md
```

## Git Workflow

- Use one branch per delivery or feature.
- Use English, kebab-case branch names with prefixes such as `chore/`, `feat/`, `test/`, `docs/`, and `ci/`.
- Use Conventional Commits.
- Keep commits scoped to the current branch objective.
- Do not include unrelated local changes.

Examples:

```text
chore: configure docker and postgres
feat: add payment request api endpoints
test: cover critical payment api flows
docs: finalize readme and delivery guide
```

## Local Commands

Run commands from the project root:

```bash
docker compose up -d --build
docker compose exec app php artisan --version
docker compose exec app php -v
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan route:list
docker compose exec app php artisan config:clear
docker compose exec app php artisan test
```

Reference data:

- Countries and currencies come from the versioned snapshot in `config/reference_data.php`.
- The currency snapshot is based on the official ExchangeRate-API supported currencies page.
- Countries are derived from the listed country/region names where a safe two-letter country or territory code is available.
- Seeders upsert records and do not delete existing countries or currencies because users and payment requests may reference them.

Scheduler and expiration:

```bash
docker compose exec app php artisan payment-requests:expire-pending
docker compose ps scheduler
```

The Docker Compose setup starts the Laravel scheduler automatically through the `scheduler` service. Use `php artisan schedule:work` manually only when running the project outside Docker Compose.

Optional external provider smoke test:

```bash
docker compose exec -e RUN_EXTERNAL_API_TESTS=true app php artisan test --testsuite=External
```

Use local PHP only for quick syntax checks when Docker is not needed:

```bash
php -l path/to/file.php
```

## Verification Rules

Run the most specific tests first, then the full suite before finishing meaningful backend changes.

Common focused checks:

```bash
docker compose exec app php artisan test --filter=AuthFlowTest
docker compose exec app php artisan test --filter=PaymentRequestApiTest
docker compose exec app php artisan test --filter=PaymentRequestPersistenceTest
docker compose exec app php artisan test --filter=ExpirePendingPaymentRequestsCommandTest
docker compose exec app php artisan test --filter=ExchangeRateProviderTest
docker compose exec app php artisan test --filter=UserReferenceDataTest
```

Full suite:

```bash
docker compose exec app php artisan test
```

When API routes change, also run:

```bash
docker compose exec app php artisan route:list --path=api
```

When OAuth behavior changes, run:

```bash
docker compose exec app php artisan test --filter=AuthFlowTest
```

When payment request state transitions change, run:

```bash
docker compose exec app php artisan test --filter=PaymentRequestApiTest
docker compose exec app php artisan test --filter=PaymentRequestPersistenceTest
docker compose exec app php artisan test --filter=ExpirePendingPaymentRequestsCommandTest
```

When documentation JSON changes, validate it:

```powershell
@('docs\openapi.json','docs\postman\CurrencyFlow.postman_collection.json') | ForEach-Object { Get-Content -Raw $_ | ConvertFrom-Json | Out-Null; Write-Output "valid json: $_" }
```

If Docker, credentials, network, or permissions prevent a command from running, report the blocker clearly and state which validation is missing.

## Seed Data

All seeded users use password:

```text
password
```

Users:

- `test@example.com` - employee, Portugal, EUR
- `ana.silva@example.com` - employee, Brazil, BRL
- `john.carter@example.com` - employee, United States, USD
- `emily.brown@example.com` - employee, United Kingdom, GBP
- `yuki.tanaka@example.com` - employee, Japan, JPY
- `marta.kowalska@example.com` - finance, Poland, PLN

OAuth public client:

```text
Client ID: 019ec29e-86dc-70bd-9de9-157bc6e2f735
Redirect URI: http://localhost:3000/auth/callback
```

## API And OAuth Notes

Payment request endpoints:

- `GET /api/payment-requests` requires `payments:read`.
- `GET /api/payment-requests/{id}` requires `payments:read`.
- `POST /api/payment-requests` requires `payments:create`.
- `POST /api/payment-requests/{id}/approval` requires `payments:approve` and finance role.
- `POST /api/payment-requests/{id}/rejection` requires `payments:approve` and finance role.

Finance users list all payment requests by default. Employee users list only their own requests.

Use status filters instead of separate state-specific routes:

```text
GET /api/payment-requests?status=pending
GET /api/payment-requests?status=approved
GET /api/payment-requests?status=rejected
GET /api/payment-requests?status=expired
```

Manual OAuth PKCE testing is documented in:

```text
docs/postman/oauth-pkce.md
```

Postman Preview is not a reliable place to complete the login and consent flow. Copy the resolved authorization URL into a real browser.

Finance users can manage public OAuth PKCE clients at:

```text
http://localhost:8000/developer/oauth-clients
```

The portal creates public clients without `client_secret`. Confidential clients, homepage URLs, and secret rotation are outside the current implementation.

## Business Rules To Preserve

- Payment request exchange rate snapshot data is immutable after creation.
- `amount_eur` is calculated from local amount divided by the stored EUR to local currency rate.
- Monetary conversion uses BCMath decimal arithmetic and controlled rounding instead of truncation.
- Payment requests can only be approved, rejected, or expired while they are pending.
- Expired payment requests cannot be approved or rejected even if the scheduler has not run yet.
- Expiration must not overwrite approved or rejected requests.
- Docker Compose must keep the `scheduler` service running so pending payment requests are checked every minute.
- Approval, rejection, and expiration must stay safe against race conditions through transactions and row-level locks.
- Fake exchange rates must not be used in production flows.

## Documentation Maintenance

Update these files when behavior changes:

- `README.md` for setup, delivery, troubleshooting, and demo instructions.
- `docs/openapi.json` for API contract changes.
- `docs/postman/CurrencyFlow.postman_collection.json` for manual API testing changes.
- `docs/postman/oauth-pkce.md` for OAuth/Postman workflow changes.
- `docs/architecture.md` for architecture decisions.
- `docs/database-diagram.md` for data model changes.
- `AGENTS.md` for operational commands, seed users, URLs, ports, and validation rules.

## Recurring Gotchas

- The OAuth redirect URI is currently `http://localhost:3000/auth/callback`; this can show a browser error until a frontend exists. The authorization `code` is still in the callback URL.
- Postman request URLs copied from the request bar may still contain `{{variables}}`; use the resolved URL from the Postman Console or replace variables manually.
- If environment changes are not reflected, run `docker compose exec app php artisan config:clear`.
- If database state is stale, run `docker compose exec app php artisan migrate:fresh --seed`.
- `AGENTS.md` should stay short and operational. Move long explanations to README or docs.

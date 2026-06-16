# CurrencyFlow

CurrencyFlow is a Laravel 12 backend for managing multi-currency payment requests.

It supports authenticated employees creating payment requests in local currencies, exchange rate capture at creation time, and finance approval workflows.

## Current Status

Authentication foundation phase.

Implemented so far:

- Laravel 12 project initialized.
- PHP 8.2+ requirement verified locally with PHP 8.4.19.
- PostgreSQL environment variables prepared.
- Docker Compose local environment configured.
- Local migrations and tests can run inside Docker.
- Laravel Passport configured for OAuth 2.0 Authorization Code Grant with PKCE.
- UUIDs configured for public user identifiers.
- Minimal registration, login, logout, and protected user endpoints implemented.

## Planned Stack

- Laravel 12
- PHP 8.2+
- PostgreSQL
- Laravel Passport with OAuth 2.0 Authorization Code Grant and PKCE
- RESTful API design
- OpenAPI documentation
- Docker Compose for local development

## Requirements

- PHP 8.2 or higher
- Composer
- Git
- Docker
- Docker Compose

## Local Setup

Copy the environment file if needed:

```bash
cp .env.example .env
```

Start the Docker environment:

```bash
docker compose up -d --build
```

Run migrations and seed local development data:

```bash
docker compose exec app php artisan migrate --seed
```

Open:

```text
http://127.0.0.1:8000
```

The application container creates `.env`, installs Composer dependencies, and generates `APP_KEY` when needed.

The local seeders create:

- a public OAuth client for the future frontend;
- a development user:
  - email: `test@example.com`
  - password: `password`

## Local Setup Without Docker

The preferred local workflow uses Docker. If PHP and Composer are installed locally, the application can also be started with:

```bash
composer install
php artisan key:generate
php artisan serve
```

```text
http://127.0.0.1:8000
```

## Environment Notes

The project is configured for PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=currencyflow
DB_USERNAME=currencyflow
DB_PASSWORD=secret
```

For host tools outside Docker, PostgreSQL is forwarded to:

```env
DB_HOST=127.0.0.1
DB_PORT_FORWARD=5432
```

Sessions use the database driver:

```env
SESSION_DRIVER=database
```

The database session driver keeps local behavior close to production and supports the web login and OAuth authorization consent flow.

## OAuth Authentication

CurrencyFlow uses Laravel Passport with OAuth 2.0 Authorization Code Grant and PKCE.

The backend owns:

- country and currency discovery through `GET /api/countries` and `GET /api/currencies`;
- user registration through `POST /api/register`;
- browser login through `GET /login` and `POST /login`;
- OAuth authorization through `GET /oauth/authorize`;
- token exchange through `POST /oauth/token`;
- authenticated API access through Bearer tokens at endpoints such as `GET /api/user`.
- token revocation through `DELETE /api/tokens/current`.

The frontend will own the PKCE client flow:

1. Generate a temporary `code_verifier`.
2. Derive a `code_challenge` from the verifier.
3. Redirect the user to `/oauth/authorize` with the public client ID, redirect URI, requested scopes, `code_challenge`, and `state`.
4. Receive the authorization `code` on the frontend callback URL.
5. Exchange the authorization code and `code_verifier` at `/oauth/token`.
6. Use the returned `access_token` as `Authorization: Bearer <token>` for protected API requests.

The frontend may request these initial scopes:

- `payments:read`
- `payments:create`
- `payments:approve`

## Useful Commands

Check Laravel:

```bash
docker compose exec app php artisan --version
```

Check PHP:

```bash
docker compose exec app php -v
```

Run migrations:

```bash
docker compose exec app php artisan migrate --seed
```

Rebuild the local database:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Run tests:

```bash
docker compose exec app php artisan test
```

Expire pending payment requests manually:

```bash
docker compose exec app php artisan payment-requests:expire-pending
```

Run the Laravel scheduler locally:

```bash
docker compose exec app php artisan schedule:work
```

The scheduler runs `payment-requests:expire-pending` hourly. The command processes expired pending requests in batches and leaves approved or rejected requests unchanged.

Clear Laravel configuration cache if environment changes are not reflected:

```bash
docker compose exec app php artisan config:clear
```

## API Documentation

The OpenAPI specification is available at:

```text
docs/openapi.json
```

The Postman collection for local API testing is available at:

```text
docs/postman/CurrencyFlow.postman_collection.json
```

Instructions for testing OAuth PKCE with Postman are available at:

```text
docs/postman/oauth-pkce.md
```

## Development Workflow

- Use one branch per delivery or feature.
- Use branch names in English and kebab-case, such as `feat/payment-request-api`.
- Use Conventional Commits.
- Keep documentation and code in English.

# CurrencyFlow

CurrencyFlow is a Laravel 12 backend for managing multi-currency payment requests.

It supports authenticated employees creating payment requests in local currencies, exchange rate capture at creation time, and finance approval workflows.

## Current Status

Bootstrap phase.

Implemented so far:

- Laravel 12 project initialized.
- PHP 8.2+ requirement verified locally with PHP 8.4.19.
- PostgreSQL environment variables prepared.
- Docker Compose local environment configured.
- Local migrations and tests can run inside Docker.

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

Run migrations:

```bash
docker compose exec app php artisan migrate
```

Open:

```text
http://127.0.0.1:8000
```

The application container creates `.env`, installs Composer dependencies, and generates `APP_KEY` when needed.

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

Sessions currently use the file driver:

```env
SESSION_DRIVER=file
```

This will be reviewed after Docker, PostgreSQL, and Passport are configured.

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
docker compose exec app php artisan migrate
```

Run tests:

```bash
docker compose exec app php artisan test
```

Clear Laravel configuration cache if environment changes are not reflected:

```bash
docker compose exec app php artisan config:clear
```

## Development Workflow

- Use one branch per delivery or feature.
- Use branch names in English and kebab-case, such as `feat/payment-request-api`.
- Use Conventional Commits.
- Keep documentation and code in English.

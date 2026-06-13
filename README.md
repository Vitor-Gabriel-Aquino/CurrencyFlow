# CurrencyFlow

CurrencyFlow is a Laravel 12 backend for managing multi-currency payment requests.

It supports authenticated employees creating payment requests in local currencies, exchange rate capture at creation time, and finance approval workflows.

## Current Status

Bootstrap phase.

Implemented so far:

- Laravel 12 project initialized.
- PHP 8.2+ requirement verified locally with PHP 8.4.19.
- PostgreSQL environment variables prepared.
- Local development can run with Laravel's built-in server.
- Initial Codex project instructions added in `AGENTS.md`.

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

Docker and PostgreSQL setup will be added in a later delivery.

## Local Setup

Install dependencies:

```bash
composer install
```

Copy the environment file if needed:

```bash
cp .env.example .env
```

Generate the application key if needed:

```bash
php artisan key:generate
```

Start the local development server:

```bash
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

## Environment Notes

The project is configured for PostgreSQL:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=currencyflow
DB_USERNAME=currencyflow
DB_PASSWORD=secret
```

During the bootstrap phase, sessions use the file driver so the default Laravel page can load before PostgreSQL is configured:

```env
SESSION_DRIVER=file
```

This will be reviewed after Docker, PostgreSQL, and Passport are configured.

## Useful Commands

Check Laravel:

```bash
php artisan --version
```

Check PHP:

```bash
php -v
```

Run tests:

```bash
php artisan test
```

## Development Workflow

- Use one branch per delivery or feature.
- Use branch names in English and kebab-case, such as `feat/payment-request-api`.
- Use Conventional Commits.
- Keep documentation and code in English.
- Keep `AGENTS.md` updated when project commands, ports, URLs, or verification steps change.

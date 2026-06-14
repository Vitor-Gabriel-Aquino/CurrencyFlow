# Architecture

CurrencyFlow uses a pragmatic Clean Architecture adapted to Laravel.

## Layers

- `app/Domain`: business concepts, enums, value objects, and contracts.
- `app/Application`: use cases and application orchestration.
- `app/Infrastructure`: framework, database, external services, and implementation details.
- `app/Http`: controllers, requests, resources, and HTTP entry points.

Laravel Eloquent models currently remain in `app/Models` and are treated as persistence models. Domain-heavy features should avoid placing business rules directly in Eloquent models.

## Dependency Direction

Outer layers may depend on inner layers, but inner layers should not depend on Laravel HTTP, controllers, routes, or concrete infrastructure implementations.

Preferred direction:

```text
Http -> Application -> Domain
Infrastructure -> Domain contracts
```

## Use Cases

Use cases live in `app/Application/<Feature>`.

Guidelines:

- Keep controllers thin.
- Validate HTTP input before calling a use case.
- Keep business decisions inside use cases or domain services.
- Return explicit application results instead of raw request objects.

## Repositories

Repository contracts should be introduced when a feature has meaningful persistence behavior or business rules.

Guidelines:

- Define contracts in `app/Domain/<Feature>/Contracts`.
- Implement Eloquent repositories in `app/Infrastructure/Persistence/Eloquent`.
- Bind contracts to implementations in a service provider.
- Avoid repository interfaces for trivial CRUD until they protect real domain behavior.

## Value Objects and Enums

Value objects and enums live in `app/Domain`.

Examples already in place:

- `App\Domain\Users\Enums\UserRole`
- `App\Domain\Shared\ValueObjects\CountryCode`
- `App\Domain\Shared\ValueObjects\CurrencyCode`

## Transactions and Concurrency

Business flows that change important state should use a transaction boundary through `App\Domain\Shared\Contracts\TransactionManager`.

Payment approval, rejection, and expiration must use transactions plus an atomic update or row-level lock to avoid race conditions.

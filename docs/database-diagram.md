# Database Diagram

This document records the initial database design direction before adding user roles and seed data.

## Current Decision

Roles, countries, currencies, exchange rate sources, payment request statuses, and payment request event types are stored in dedicated lookup tables.

Each user belongs to exactly one role through `users.role_id`, one country through `users.country_id`, and one preferred currency through `users.preferred_currency_id`.

This keeps repeated fixed values normalized without introducing many-to-many complexity before the project needs it.

```mermaid
erDiagram
    roles {
        uuid id PK
        string name UK
        string description
        timestamp created_at
        timestamp updated_at
    }

    countries {
        uuid id PK
        char code UK
        string name
        timestamp created_at
        timestamp updated_at
    }

    currencies {
        uuid id PK
        char code UK
        string name
        smallint exponent
        timestamp created_at
        timestamp updated_at
    }

    payment_request_statuses {
        uuid id PK
        string name UK
        string description
        timestamp created_at
        timestamp updated_at
    }

    payment_request_event_types {
        uuid id PK
        string name UK
        string description
        timestamp created_at
        timestamp updated_at
    }

    exchange_rate_sources {
        uuid id PK
        string name UK
        string description
        string base_url
        timestamp created_at
        timestamp updated_at
    }

    users {
        uuid id PK
        uuid role_id FK
        uuid country_id FK
        uuid preferred_currency_id FK
        string name
        string email UK
        string password
        timestamp email_verified_at
        timestamp created_at
        timestamp updated_at
    }

    payment_requests {
        uuid id PK
        uuid requester_id FK
        uuid currency_id FK
        uuid status_id FK
        uuid exchange_rate_source_id FK
        string title
        text description
        decimal amount
        decimal exchange_rate_to_eur
        decimal amount_eur
        timestamp exchange_rate_fetched_at
        uuid reviewed_by FK
        timestamp reviewed_at
        text review_note
        timestamp expires_at
        timestamp created_at
        timestamp updated_at
    }

    payment_request_events {
        uuid id PK
        uuid payment_request_id FK
        uuid actor_id FK
        uuid event_type_id FK
        json metadata
        timestamp created_at
    }

    roles ||--o{ users : assigned_to
    countries ||--o{ users : located_in
    currencies ||--o{ users : preferred_by
    currencies ||--o{ payment_requests : requested_in
    payment_request_statuses ||--o{ payment_requests : classifies
    exchange_rate_sources ||--o{ payment_requests : provides
    users ||--o{ payment_requests : requests
    users ||--o{ payment_requests : reviews
    users ||--o{ payment_request_events : performs
    payment_requests ||--o{ payment_request_events : records
    payment_request_event_types ||--o{ payment_request_events : classifies
```

## Notes

- `roles.name` starts with `employee` and `finance`.
- `countries.code` stores ISO 3166-1 alpha-2 country codes.
- `currencies.code` stores ISO 4217 currency codes.
- `currencies.exponent` stores the number of decimal places normally used by the currency.
- `payment_request_statuses.name` starts with `pending`, `approved`, `rejected`, and `expired`.
- `payment_request_event_types.name` starts with `created`, `approved`, `rejected`, and `expired`.
- `exchange_rate_sources.name` starts with the selected external provider, such as `ExchangeRate-API`.
- `payment_requests.exchange_rate_to_eur`, `amount_eur`, `exchange_rate_source_id`, and `exchange_rate_fetched_at` must be immutable after creation.
- `payment_request_events` provides basic audit history for create, approve, reject, and expire actions.

Only `roles`, `countries`, `currencies`, and the additional user relationships are part of the current delivery. Payment request, exchange rate source, status, and event type tables are documented here to validate the model direction before later migrations.

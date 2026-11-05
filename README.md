# Todo

A small PHP todo application with a deliberately simple task domain. The interesting part is the surrounding backend: authentication, account workflows, authorization, and explicit application boundaries.

Built to demonstrate the [Oasys](https://github.com/kweensey) packages in a working application.

## Features

- Personal tasks: create, edit, complete, reopen, and delete; access is scoped to the owner
- User administration: roles, invitations, account management, and blocking
- Registration with email verification, account recovery, and email/password changes
- Email one-time passwords (OTPs) for registration and email changes
- Browser sessions and bearer-token API authentication with RS256 access JWTs and rotating refresh tokens
- Twig-rendered pages and an HTTP API described by OpenAPI

## Stack

PHP 8.3+, MySQL, Eloquent ORM, Twig, PHP-DI, PHPUnit, PHPMailer, and the Oasys core/config/validator packages.

HTTP components use PSR interfaces.

## Requirements

- PHP with `pdo_mysql`, `mbstring`, and `openssl`
- Composer
- MySQL
- SMTP credentials for email-dependent account workflows

## Setup

Install dependencies and create local configuration files and JWT signing keys:

```bash
composer install
composer setup
```

---

Create a new MySQL database, then edit the generated configuration files:

- `config/db.php` - database connection
- `config/app.php` - application name, host (public origin, including port), app ID, and a **random HMAC key**. Replace the example `secret` value. For example:

   ```bash
   php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
   ```

- `config/mail.php` - SMTP host, port, credentials, and sender address. The current mail transport uses authenticated SMTP with STARTTLS

---

Initialize the schema in the empty database:

```bash
composer bootstrap:db
```

This imports `schema.sql` into the configured database.

_The database must not contain any tables or views._

---

Create the first administrator:

```bash
composer bootstrap:admin -- admin@example.com
```

This creates an active administrator and prints a randomly generated initial password once.

Run it from a trusted terminal, store the password securely, and change it after signing in.

_Runs only on an empty user database._

---

Run the local development server:

```bash
composer serve
```

This serves `public/` at `http://localhost:8000`.

Set `app.host` to `http://localhost:8000/` for local use.

The built-in PHP server is for development, **not production**.

## Tests

Database integration tests and Bruno seeding require `config/db.php` to point to a disposable MySQL database whose name ends in `_test` (for example, `todo_test`).

### PHPUnit

Run the suites:

```bash
composer test:unit
composer test:integration
```

Unit tests cover input validation, state transitions, authorization policies, authentication limits, and token invariants.

Database integration tests verify task persistence and ownership, session revocation, token replay detection, and isolation between users.

Fixtures are rolled back between tests.

_CI runs both PHPUnit suites with a MySQL service._

---

### Bruno

In `bruno/environments/local.bru`, set `baseUrl` to the running application's URL.

Open `bruno/` in [Bruno](https://www.usebruno.com/) and select the `local` environment.

Create the two accounts expected by the collection:

```bash
composer seed:bruno
```

HTTP-level tests exercise authentication, task CRUD and state transitions, request validation, refresh-token rotation and revocation, and cross-user access restrictions.

## Design notes

- Controllers adapt HTTP requests and responses; use cases coordinate application operations.
- Policies and use cases authorize operations; task queries are restricted to the authenticated owner.
- Models and database constraints maintain persistence and relevant invariants.
- Access JWTs are short-lived and independently verifiable. Refresh tokens are persisted, rotated, and checked for reuse. Individual access tokens are valid until expiry by design.
- The project favors explicit responsibilities and focused dependencies over additional abstraction layers.
- Application events and errors are logged through Monolog to daily files in `log/`. The directory must be writable by the PHP process.

This is a demonstration application, **not a production deployment template**. Secrets, backups, observability, mail delivery, and deployment processes must be configured appropriately for a real environment.

## Repository guide

| Path | Purpose |
|---|---|
| `src/Controllers/` | HTTP endpoints and responses |
| `src/UseCases/` | Application workflows |
| `src/Models/`, `src/Policies/` | Persistence and authorization |
| `template/`, `public/` | Twig pages and web assets |
| `scripts/` | Setup, database/admin bootstrapping, Bruno user seeding |
| `tests/` | PHPUnit unit and integration tests |
| `bruno/` | HTTP test collection |
| `docs/openapi.yaml` | API specification |
| `schema.sql` | Initial MySQL schema |

## API documentation

See [`docs/openapi.yaml`](docs/openapi.yaml) for HTTP API request and response contracts. HTML page routes are intentionally not included.

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Hours Ledger System — Backend API**

Laravel 12 REST API implementing a ledger-based hour tracking system.

### Tech Stack
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: PostgreSQL 16
- **Cache**: Redis 7
- **Authentication**: Laravel Sanctum
- **Authorization**: Spatie Laravel Permission
- **Code Style**: PSR-12 (Laravel Pint)

## Critical Domain Rules

This system follows strict **append-only ledger** principles:

- **NO balance columns** — Balance is always calculated via `SUM(ledger_entries.hours)`
- **NO deletes** — All changes are insertions (including adjustments)
- **NO soft deletes** — Data is immutable
- `LedgerEntry.hours` is signed (positive = credit, negative = debit)

### Domain Entities
- **Client** → has many Wallets
- **Wallet** → belongs to Client, has many LedgerEntries
- **LedgerEntry** → immutable record of hour changes
- **Tag** → optional classification for entries (many-to-many with LedgerEntry)

## Development Commands

```bash
# Run migrations
php artisan migrate

# Run tests
php artisan test

# Run specific test
php artisan test --filter=TestName

# Code formatting (PSR-12)
./vendor/bin/pint

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear

# List routes
php artisan route:list --path=api

# Tinker (REPL)
php artisan tinker

# Seed roles and permissions
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Docker Commands
```bash
# From project root
docker compose --env-file .env.docker exec backend php artisan migrate
docker compose --env-file .env.docker exec backend php artisan test
docker compose --env-file .env.docker exec backend ./vendor/bin/pint
```

## Architecture

### Directory Structure
```
app/
├── Http/
│   ├── Controllers/Api/    # Thin controllers
│   └── Requests/           # Form request validation
├── Models/                 # Eloquent models
├── Policies/               # Authorization policies
└── Services/               # Business logic
    ├── BalanceCalculatorService.php
    ├── LedgerService.php
    └── ReportService.php
```

### Principles
- **Thin controllers** — Business logic in Services
- **Services for logic** — BalanceCalculatorService, LedgerService, ReportService
- Use `$request->input('field')` instead of `$request->field`
- PSR-12 code style enforced via Pint

### API Endpoints
All endpoints require `auth:sanctum` middleware.

| Resource | Endpoints |
|----------|-----------|
| Clients | `GET/POST /api/clients`, `GET/PUT/DELETE /api/clients/{id}` |
| Wallets | `GET/POST /api/wallets`, `GET/PUT/DELETE /api/wallets/{id}` |
| Wallet Balance | `GET /api/wallets/{id}/balance` |
| Wallet Entries | `GET /api/wallets/{id}/entries` |
| Ledger Entries | `GET/POST /api/ledger-entries`, `GET /api/ledger-entries/{id}` |
| Tags | `GET/POST /api/tags`, `GET/PUT/DELETE /api/tags/{id}` |
| Reports | `GET /api/reports`, `/api/reports/summary`, `/api/reports/by-wallet`, `/api/reports/by-client` |

### Permissions
Managed via Spatie Laravel Permission:
- **admin**: Full access (clients, wallets, credits, adjustments)
- **user**: View access + insert debits + view reports

## Code Style Guideline (Mandatory)

All code generated, modified, or refactored **must strictly follow** the rules defined in:

**UNIVERSAL-CODE-STYLE-RULES.md**

### Enforcement Rules

- The rules in `UNIVERSAL-CODE-STYLE-RULES.md` are **authoritative and non-negotiable**
- No framework convention, language idiom, or AI default may override these rules
- Brevity, shortcuts, and one-liners are explicitly forbidden when they reduce clarity
- Explicit control flow, block scoping, and early returns are mandatory
- Logical sections must be separated by blank lines
- If multiple valid implementations exist, choose the **most explicit and readable**

### Conflict Resolution

If any instruction, suggestion, or generated code conflicts with the rules in
`UNIVERSAL-CODE-STYLE-RULES.md`, **that file always takes precedence**.

Any output that violates these rules must be considered **invalid and corrected**.

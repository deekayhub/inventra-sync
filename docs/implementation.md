# Implementation Guide

This guide explains how to build Inventra Sync from the current Laravel starter kit. Read [`PRD.md`](../PRD.md), [`DEVELOPMENT_PLAN.md`](../DEVELOPMENT_PLAN.md), [`architecture.md`](architecture.md), and [`database.md`](database.md) first.

## 1. Ground rules

- Extend the existing Laravel 13 application. Do not create a new app, auth system, tenancy system, RBAC system, or activity log.
- Tenant == workspace == organization in MVP UI and code. Reuse `tenant()`, `users.tenant_id`, and existing tenant middleware.
- Every business table carries `tenant_id`; every query, job, webhook, and report is tenant-scoped.
- Authorization uses existing Spatie Permission teams and `permission:` middleware. Add new permissions to `PermissionSeeder::GROUPS`.
- Business activity uses `spatie/laravel-activitylog`. Stock quantities come from the immutable inventory ledger.
- All stock/reservation changes run in one DB transaction with row locks and idempotency keys. Emit synchronization events after commit or through the outbox.
- External API integrations sit behind contracts/adapters. No provider-specific code in controllers, models, or core inventory services.
- All user-facing branding reads `config('app.name')`.
- Do not add dependencies or new top-level namespaces without approval.

## 2. Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan test --compact
```

Configure `APP_URL` to the central domain (for example `http://saas.test`) and add wildcard DNS for tenant subdomains. The site runs with MySQL, a database queue worker, a scheduler, and Vite in the local lerd environment.

Useful commands:

```bash
php artisan route:list --except-vendor
php artisan make:migration --no-interaction create_<table>_table
php artisan make:model --no-interaction <Model> -f
php artisan make:test --pest <Name>Test
vendor/bin/pest --filter=<testName>
vendor/bin/pint --dirty --format agent
npm run build
```

## 3. Resolve local drift before Phase 1

The local database currently has Phase 1 inventory tables and extra permissions that are not backed by migration/seeder files in the checkout. Reconcile this first:

1. Compare `database/migrations/` with `SELECT migration FROM migrations ORDER BY migration`.
2. Recover or recreate missing migrations, or reset the development database from code.
3. Align `PermissionSeeder::GROUPS` with the permission names the code will actually use. Choose one naming set and keep DB, seeder, routes, policies, and tests consistent.
4. Re-run migrations and the full Pest suite before adding new domain code.

Recommended permission set from PRD §6.3:

```text
products.view / products.create / products.update / products.delete
inventory.view / inventory.adjust / inventory.manage / inventory.view_ledger
orders.view / orders.manage / orders.fulfill
channels.view / channels.manage / channels.sync
reports.view / settings.manage
```

If the existing database uses `products.edit` or `inventory.movements.view`, either keep those names everywhere or add an intentional alias migration. Do not leave both sets unexplained.

## 4. Code layout

Follow the existing flat model namespace and Laravel generators.

```text
app/
  Models/                       Product, ProductVariant, Warehouse, ...
  Http/Controllers/Inventory/   ProductController, InventoryController, ...
  Http/Requests/Inventory/      StoreProductRequest, AdjustStockRequest, ...
  Policies/                     ProductPolicy, InventoryPolicy, ...
  Actions/ or Services/         Transactional business operations (approve namespace first)
  Events/                       Domain events
  Listeners/                    Secondary reactions
  Jobs/                         Queued work
  Contracts/                    ChannelAdapter, AllocationRule, ...
  Adapters/Shopify/             Provider-specific implementations
  Adapters/WooCommerce/         Provider-specific implementations
resources/views/tenant/         Inventory Blade pages
routes/tenant.php               Tenant UI routes
routes/api.php                  Webhooks and API routes when needed
```

Controllers stay thin: Form Request validation → policy/permission check → one action/service call → response. Views never perform stock arithmetic or authorization-sensitive queries.

## 5. Tenant and permission checklist for every new feature

- [ ] Route lives in `routes/tenant.php` (UI) or a webhook route that explicitly resolves the tenant.
- [ ] Route has the right `permission:` middleware or policy check.
- [ ] Controller/action resolves `tenant()` or receives an explicit tenant ID from trusted context.
- [ ] Every model query includes `tenant_id`, either through a global scope or an explicit `where`.
- [ ] Record IDs from requests are loaded with tenant scope; another tenant's ID returns 404, not 403 with data leakage.
- [ ] Jobs capture `tenant_id` and re-validate it before writes.
- [ ] Activity logs include actor and tenant context but exclude credentials and tokens.
- [ ] Navigation links and page buttons use the same permission names as routes.

## 6. Phase implementation order

### Phase 0 — Foundation

Goal: clean, reproducible baseline.

- Reconcile database drift (section 3).
- Agree on the action/service namespace before writing business services.
- Confirm `QUEUE_CONNECTION=database` locally and a continuous queue worker in production.
- Add the PRD permission names to `PermissionSeeder::GROUPS` without renaming existing permissions.
- Decide a shared tenant-scoping trait/pattern; verify Stancl concerns do not already cover it.
- Exit: migrations run cleanly, routes list works, existing tests pass.

### Phase 1 — Product catalog and default warehouse

Goal: tenant-scoped products, variants, SKUs, and one default warehouse.

- Models/migrations: `Product`, `ProductVariant`, `ProductCategory`, `Warehouse`, `ProductImage` if needed.
- Unique `(tenant_id, sku)` on variants; unique `(tenant_id, code)` on warehouses; one default per tenant.
- Form Requests and controllers under `Inventory/`; pagination, search, and filters.
- Routes such as `products.*`, `categories.*`, `warehouses.*` in `routes/tenant.php` with `products.*` / `warehouses.*` permissions.
- Queued chunked import/export jobs rather than synchronous bulk requests.
- Blade pages using `<x-app-layout>`, `<x-settings-layout>`, `glass-card`, shared tables/badges, and permission-aware sidebar entries.
- Exit: cross-tenant ID access is impossible; archived products keep historical references.

### Phase 2 — Inventory engine

Goal: accurate stock with a complete ledger and no overselling.

- Tables: `inventory_stocks`, `inventory_ledger`/`inventory_movements`, `inventory_reservations`.
- Actions for adjusting, opening stock, reserving, releasing, committing, and returning stock.
- `DB::transaction()` + `lockForUpdate()` on the stock row; write the ledger row in the same transaction.
- One calculation source for available and channel-available stock.
- Domain events: `InventoryAdjusted`, `InventoryReserved`, `InventoryReservationReleased`, `InventoryCommitted`, `InventoryChanged`.
- Permissions `inventory.view`, `inventory.adjust`, `inventory.manage`, `inventory.view_ledger`.
- Exit: no negative available stock, no silent overwrites, no duplicate ledger rows on retry.

### Phase 3 — Channels and mappings

Goal: connect a store securely and map listings without leaking stock to unmapped listings.

- Tables: channel connections, encrypted credentials, listings, product mappings, inventory settings.
- `ChannelAdapter` contract plus Shopify and WooCommerce adapters; HTTP client timeouts and retries.
- Credential validation before activation; `Crypt::encryptString()` at rest; never log or serialize secrets.
- Listing import and mapping UI with conflict detection; per-listing buffer and pause controls.
- Permissions `channels.view`, `channels.manage`, `channels.sync`.
- Exit: invalid credentials handled safely; unmapped listings skipped by sync.

### Phase 4 — Orders and fulfilment

Goal: import external orders exactly once and manage their lifecycle.

- Tables: orders, order items, fulfilments, shipments, cancellations, returns, return items.
- Webhook endpoint: verify signature → persist raw event with dedupe key → acknowledge → dispatch job.
- Order import action: unique `(channel_connection_id, external_order_id)`, create snapshots, call reservation service.
- Statuses: pending, confirmed, processing, partially fulfilled, fulfilled, cancelled, failed.
- Fulfilment commits reservations and deducts stock; cancellation releases reservations; returns classify condition before creating ledger entries.
- Permissions `orders.view`, `orders.manage`, `orders.fulfill`.
- Exit: duplicate webhooks and retries have no duplicate effects.

### Phase 5 — Synchronization engine

Goal: reliable, ordered, retryable propagation of stock changes.

- Tables: outbox events, webhook events, channel sync logs.
- Dispatcher job reads pending outbox events, resolves mapped listings, calls adapters, records results.
- Retry temporary failures with backoff; permanent failures are visible and manually retryable.
- Stale-update guard uses event sequence/version against the stock row.
- Reconciliation skeleton records discrepancies only; it never silently rewrites stock.
- Permissions `channels.sync`, `reports.view`.
- Exit: provider failures do not lose internal inventory changes.

### Phase 6 — Reports and hardening

Goal: operators can see and investigate stock health and integration failures.

- Reports: current inventory, low stock, out of stock, movement, orders by channel, failed sync, connection status/last sync.
- Replace placeholder dashboard KPIs with scoped, permission-checked inventory metrics.
- Index tenant/SKU/external ID/status/timestamp columns; use pagination and chunked exports.
- Security review: tenant checks, signature validation, encrypted credentials, rate limits, no secrets in logs, mass-assignment safety.
- Run browser and request diagnostics on real traffic and fix N+1/slow-query findings.

## 7. Naming and state conventions

- Use constants or enums for statuses rather than scattered string literals.
- Reservation statuses: `Reserved`, `Released`, `Committed`, `Expired`.
- Sync statuses: `Pending`, `Processing`, `Successful`, `Failed`, `Retrying`, `Skipped`.
- Product statuses: `draft`, `active`, `archived`.
- Keep UI labels title case where the PRD defines them; database values can use lowercase snake_case if consistent.
- Use descriptive method names: `reserveForOrder()`, not `reserve()`.

## 8. Activity, events, and logs

- Use the existing activity log for product changes, adjustments, reservations, channel connection changes, mapping changes, and order status changes.
- Keep technical sync request/response summaries in sync logs, not in the business activity log.
- Dispatch domain events after commit or via the outbox. Never dispatch an event inside an uncommitted transaction when reliable delivery is required.
- Never include credentials, tokens, webhook signatures, or raw secrets in logs or event payloads.

## 9. UI implementation checklist

1. Confirm the correct shell: guest, tenant app, or central admin.
2. Reuse semantic tokens and shared classes from `design.md`.
3. Gate links and data by tenant and permission.
4. Verify active states in both sidebar and topbar/dropdown.
5. Verify mobile drawer, collapsed rail, focus rings, and table overflow.
6. Gate navigation entries in `resources/views/components/app-sidebar.blade.php`.
7. Run `npm run build`, narrow Pest tests, Pint, and broader tests before finishing.

## 10. Definition of done for a module

- Requirements from the corresponding PRD section are implemented.
- Tenant and permission rules are enforced on every route, query, and job.
- Validation uses Form Requests; errors are handled; no secrets are logged.
- Migrations are committed, reversible where practical, and indexed.
- Pest tests cover success and important failure paths.
- Stock changes are transactional, idempotent, and recorded in the ledger.
- Relevant events and activity logs are emitted.
- Blade UI follows `design.md`; `npm run build` succeeds.
- `vendor/bin/pint --dirty --format agent` has been run after PHP changes.
- Browser/request diagnostics show no new JavaScript errors, N+1 queries, or slow queries.

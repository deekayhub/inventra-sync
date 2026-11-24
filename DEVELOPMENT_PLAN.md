# Inventra Sync — Development Plan

Companion to [`PRD.md`](PRD.md). This document turns the PRD milestones into ordered, testable phases. Each phase must be merged with its tests green before the next phase starts.

## Ground rules (apply to every phase)

- Extend the existing Laravel 13 / PHP 8.4 application. Do not create a new app, auth system, tenancy system, RBAC system, or activity log.
- The tenant (`tenant_id`) is the organization. Every business table carries `tenant_id`, every query is tenant-scoped, and every request to manipulate a record by ID is authorized against the active tenant.
- Authorization uses the existing Spatie Permission teams setup (`config/permission.php`) and `can()` / `permission:` checks. Add new permissions to `PermissionSeeder::GROUPS`; `SeedTenantPermissions` will grant them to the protected `Admin` role for each tenant.
- Business activity uses `spatie/laravel-activitylog`. Stock movements use a dedicated immutable ledger — the ledger, not the activity log, is the source of truth for quantities.
- All stock/reservation changes run in a single DB transaction with row locks (`lockForUpdate`) and an idempotency key. Emit sync events after commit via a transactional outbox.
- No provider-specific API code in core models/services. Put Shopify/WooCommerce logic behind a contract + adapter.
- Follow existing conventions: `php artisan make:* --no-interaction`, Form Requests for validation, Blade + existing `<x-*>` components and design tokens from `resources/css/app.css` / `tailwind.config.js`, Pest feature tests, `vendor/bin/pint --dirty --format agent`, `npm run build`.
- New top-level namespaces need approval. Prefer `app/Models/` (flat, like today), `app/Http/Controllers/Inventory/`, `app/Http/Requests/Inventory/`, and domain events/jobs under `app/Events/`, `app/Jobs/` when they are first needed.
- `APP_NAME` in `.env` is the only branding source; all UI copy reads `config('app.name')`.

## Current baseline (verified)

- Installed: Laravel 13.17, PHP 8.3+ (running 8.4), `stancl/tenancy` 3.10, `spatie/laravel-permission` 8, `spatie/laravel-activitylog` 5, Breeze 2, Pest 5, Tailwind 3 + Vite 8.
- Single-database tenancy: `users.tenant_id`, `tenants`, `domains`, Spatie team key `tenant_id` (`app/Models/Tenant.php`, `config/tenancy.php`).
- Existing permissions: `workspace.view/update`, `roles.view/create/edit/delete`, `users.view/create/edit/delete`, `activity-logs.view` (`database/seeders/PermissionSeeder.php:14`).
- Existing tests: 89 passing (`vendor/bin/pest --compact`, 377 assertions).
- The configured `inventra_sync` MySQL database currently has **no tables**; run `php artisan migrate` before feature migrations are added.

---

## Phase 0 — Foundation, audit and conventions

**PRD milestone:** 0 (Existing Project Audit). **Status:** complete on 2026-11-24; Phase 1 is unblocked.

**Goals:** establish a clean, runnable baseline and agree on the boundaries before domain code lands.

**Tasks**

1. Run `php artisan migrate` on the local `inventra_sync` database; confirm `php artisan route:list` and the existing suite still pass.
2. Record the architecture decisions already verified above in the project docs; do not re-audit on every phase.
3. Decide (and get approval for) the service/action namespace for transactional business operations. Keep controllers thin from day one.
4. Decide the "organization" label in UI: tenant == workspace == organization. Keep the existing workspace settings screen as the place for organization settings.
5. Extend `PermissionSeeder::GROUPS` with the inventory permission groups from PRD §6.3, keeping existing names untouched. Add the optional role templates (Inventory Manager, Order Manager, Warehouse Staff, Viewer) only if the MVP needs them; `Admin` stays protected and untouched.
6. Add a shared tenant-scoping trait/base pattern for new models (e.g. `BelongsToTenant`-style booted scope) unless an existing Stancl trait already covers it — verify first.
7. Confirm queue driver and worker command: `QUEUE_CONNECTION=database` locally, Redis in production, `queue:listen`/`queue:work` running continuously.

**Files touched:** `database/seeders/PermissionSeeder.php`, `app/Models/` (shared concern if needed), `.env.example`, docs.

**Exit criteria:** migrations run cleanly, seeded permissions include the new names for every tenant, existing 89 tests still pass, no duplicate tenancy/RBAC/activity infrastructure.

---

## Phase 1 — Product catalog, variants and default warehouse

**PRD milestone:** 1 (Inventory Foundation). **Effort:** M. **Depends on:** Phase 0. **Status:** complete on 2026-11-24; Phase 2 is unblocked.

**Goals:** authorized users can create organization-scoped products/SKUs/variants and manage one default warehouse.

**Scope**

- Tables/migrations: `products`, `product_variants`, `product_categories`, `warehouses` (one default per tenant, unique partial constraint enforced in the service layer), `product_images` if needed.
- Models: `Product`, `ProductVariant`, `ProductCategory`, `Warehouse`, all `tenant_id`-scoped. Unique `(tenant_id, sku)` on variants. Archiving keeps historical order snapshots intact.
- Form Requests + controllers under `app/Http/Requests/Inventory/` and `app/Http/Controllers/Inventory/`; list/search/filter with pagination.
- Routes in `routes/tenant.php` under a `products.*`, `categories.*`, `warehouses.*` prefix, gated by `permission:products.*` / `settings.manage` as appropriate.
- Blade pages using `<x-app-layout>`, `<x-settings-layout>`, `glass-card`, shared table/badge patterns; navigation entries added to `resources/views/components/app-sidebar.blade.php` behind the new permissions.
- Import/export: queued chunked jobs, not a synchronous request.

**Tests:** tenant isolation, SKU uniqueness per tenant, archive preserves historic records, permission gating, default-warehouse creation and deletion protection.

**Exit criteria:** all PRD Module 1–3 acceptance criteria met; no cross-tenant access by ID manipulation; existing starter-kit tests still pass. Verified with `vendor/bin/pest --compact` (101 passing tests, 436 assertions), `vendor/bin/pint --dirty --format agent`, `npm run build`, and `git diff --check`.

---

## Phase 2 — Inventory engine: stock, ledger and reservations

**PRD milestone:** 2 (Inventory Engine). **Effort:** L. **Depends on:** Phase 1.

**Goals:** accurate on-hand / reserved / available quantities with a complete audit trail and no overselling.

**Scope**

- Tables: `inventory_stocks` (unique tenant + warehouse + variant), `inventory_ledger` (immutable, append-only; reason, reference, actor, idempotency key), `inventory_reservations` (status reserved/released/committed/expired, external reference, uniqueness).
- Transactional service/action layer for adjustments, opening stock, reservation, release, commit, and returns. Use `lockForUpdate` on stock rows; record every change in the ledger in the same transaction.
- Available = on-hand − reserved; channel-available = max(0, available − buffer), calculated in one place (a value object or model accessor), never duplicated in views.
- Domain events: `InventoryAdjusted`, `InventoryReserved`, `InventoryReservationReleased`, `InventoryCommitted`, `InventoryChanged`. Dispatch after commit or write to the outbox table (outbox table itself lands in Phase 5; until then use `afterCommit`).
- Permissions `inventory.view`, `inventory.adjust`, `inventory.manage`, `inventory.view_ledger`; UI for stock list, adjustment form, ledger table, reservations.

**Tests:** concurrent reservation simulation, idempotent retries, negative-stock protection, ledger entry on every movement, transaction rollback on failure, permission gates.

**Exit criteria:** PRD Module 4–5 acceptance criteria met; no silent overwrites, no duplicate stock movements, no partial updates.

---

## Phase 3 — Sales channels, credentials and product mapping

**PRD milestone:** 3 (Channel Integration Foundation). **Effort:** L. **Depends on:** Phase 1, Phase 2 (stock calculation).

**Goals:** connect a store securely and map external listings to internal variants without leaking stock to unmapped listings.

**Scope**

- Tables: `sales_channels` (provider registry, can be seeded config instead of a table if static), `channel_connections` (tenant, provider, external account/store IDs, status, last sync, settings), `channel_credentials` (Laravel `Crypt` encrypted at rest, never logged/serialized), `channel_listings`, `channel_product_mappings`, `channel_inventory_settings` (buffer, threshold, paused flag).
- Contracts: `ChannelAdapter` (validate credentials, fetch listings, push inventory, fetch orders, push fulfilment) plus `ShopifyAdapter` and `WooCommerceAdapter`. No provider calls from controllers.
- HTTP client with explicit timeouts and retries; validate credentials before activating a connection; disconnect cancels future jobs.
- Permissions `channels.view`, `channels.manage`, `channels.sync`; UI for connection list, connect/disconnect flow, listing import, mapping screen with conflict detection, per-listing buffer settings.
- Activity log entries for connection and mapping changes, with credentials excluded.

**Tests:** encryption round-trip, credential never exposed in API/activity output, tenant scoping, one listing mapped to unrelated variants rejected, unmapped listings skipped by sync.

**Exit criteria:** PRD Module 6–7 and §12 acceptance criteria met; Shopify and WooCommerce adapters satisfy the same contract; no provider-specific code in core inventory services.

---

## Phase 4 — Orders, fulfilment, cancellations and returns

**PRD milestone:** 4 (Order Synchronization). **Effort:** L. **Depends on:** Phase 2, Phase 3.

**Goals:** external orders land in one place, reserve stock exactly once, and follow a tracked fulfilment lifecycle.

**Scope**

- Tables: `orders` (tenant, channel connection, external order ID unique per connection, status, payment/fulfilment status, customer/delivery data, totals), `order_items` (variant foreign key where mapped, quantity, price and product snapshot), `reservations` link, `fulfilments`, `shipments`, `cancellations`, `returns`, `return_items`.
- Inbound webhook controller under `routes/api.php` (or tenant routes if the provider needs tenant context): verify signature → persist raw event with dedupe key → acknowledge quickly → dispatch processing job.
- Order import action: deduplicate by `(channel_connection_id, external_order_id)`, create order + items, call the Phase 2 reservation service, emit sync events. Failed processing is retryable without creating duplicates.
- Manual order screens and fulfilment workflow: pending → processing → partially fulfilled → fulfilled; commit reservation and deduct stock on fulfilment; release on cancellation; returns classified sellable/damaged before they create ledger entries.
- Permissions `orders.view`, `orders.manage`, `orders.fulfill`; order tables with channel/status filters and sync status badges.

**Tests:** duplicate webhook, concurrent orders, cancellation idempotency, snapshot preservation, retry after provider failure, status transitions, permission gates.

**Exit criteria:** PRD Module 8–10 and §9 MVP workflow steps 6–10, 14–15 satisfied.

---

## Phase 5 — Real-time synchronization engine

**PRD milestone:** 5 (Real-Time Inventory Synchronization). **Effort:** L. **Depends on:** Phases 2–4.

**Goals:** reliable, ordered, retryable propagation of stock changes to mapped listings, with visible failures.

**Scope**

- Tables: `inventory_outbox_events` (tenant, aggregate, payload, version/sequence, status pending/processing/successful/failed/retrying/skipped, attempts, next_try_at), `webhook_events` (provider, external event ID unique, signature verification result, payload hash, processed_at), `channel_sync_logs` (per listing, request/response summary, status, duration).
- Dispatcher job reads the outbox in order, resolves affected listings, calls the provider adapter, records the result. Retry temporary failures with exponential backoff; route permanent failures to a failed state with manual retry.
- Stale-update guard: compare event sequence/version against the stock row before pushing; discard older queued updates.
- Reconciliation skeleton: scheduled job compares internal available stock with provider quantities and records discrepancies only (no silent stock edits).
- UI: failed sync report, per-listing sync history, last successful sync, manual retry button; permissions `channels.sync`, `reports.view`.

**Tests:** outbox emitted after commit, retry with backoff, stale event never overwrites newer stock, webhook dedupe, failure visibility, adapter fake for both providers.

**Exit criteria:** PRD Module 11–12 acceptance criteria met; no inventory change is lost when a provider API fails.

---

## Phase 6 — Reports, monitoring and reconciliation

**PRD milestone:** 6 (Operational Reports and Reconciliation). **Effort:** M. **Depends on:** Phase 5.

**Goals:** operators can see stock health, order volume by channel and integration failures, and reconcile discrepancies through an authorized correction flow.

**Scope**

- Reports: current inventory, low stock, out of stock, inventory movement (ledger-driven), orders by channel, failed sync, channel connection status / last sync.
- Query performance: indexes on `tenant_id`, SKU, external IDs, statuses, timestamps; pagination and chunked exports; verify N+1 and slow routes with lerd diagnostics after browser testing.
- Reconciliation run + items tables, scheduled comparison, and a correction action that creates a normal ledger adjustment with reason and actor — never a direct quantity overwrite.
- Dashboard: replace placeholder KPI copy with inventory-specific metrics while keeping the existing `<x-app-layout>` / permission-gated navigation conventions.

**Tests:** report scoping by tenant and permission, movement totals match ledger, no N+1 on list pages, reconciliation never mutates stock without an authorized adjustment.

**Exit criteria:** PRD Module 14 and Phase 6 acceptance criteria met; slow routes have no unresolved N+1/slow-query findings.

---

## Phase 7 — Hardening and release readiness

**PRD milestones:** cross-cutting §4 and §12. **Effort:** M. **Depends on:** Phase 6.

**Goals:** production-ready security, reliability and documentation.

**Tasks**

1. Security review: tenant authorization on every route/controller/job, webhook signature checks, encrypted credentials, rate limits, no secrets in logs, mass-assignment safety.
2. Reliability: failed-job monitoring, alerts/notification hooks, backup + restore drill, runbook for webhook/outbox failures.
3. Performance: `lerd diag optimize_route` on real traffic, add indexes, verify 500 ms inventory queries and <1 s webhook acknowledgement targets.
4. Build/ops: `npm run build`, queue worker and scheduler in production, HTTPS, `APP_NAME` from env, cache/session prefixes verified after config cache.
5. Documentation: API/webhook setup for Shopify and WooCommerce, operator runbook, update `README.md` links to this plan and the PRD.
6. Full suite: `vendor/bin/pest`, `vendor/bin/pint --dirty --format agent`, `npm run build`; browser smoke test with lerd browser events and request lenses clean.

**Exit criteria:** PRD §9 MVP success criteria and §12 Definition of Done satisfied for every module.

---

## Per-phase Definition of Done

- [ ] Requirements from the corresponding PRD section implemented.
- [ ] Tenant and permission rules enforced on every new route, query and job.
- [ ] Validation via Form Requests; errors handled; no secrets in logs/activity.
- [ ] Migrations added (reversible where practical); indexes in place.
- [ ] Pest tests added for success and important failure paths; suite green.
- [ ] Stock changes transactional, idempotent and recorded in the ledger.
- [ ] Relevant domain events / activity log entries emitted.
- [ ] Blade UI uses existing shells, components and tokens; `npm run build` succeeds.
- [ ] `vendor/bin/pint --dirty --format agent` run after PHP changes.
- [ ] Browser/request diagnostics show no new JavaScript errors, N+1 or slow queries.

## Deferred to after MVP

Multiple warehouses and transfers, purchase orders and suppliers, POS/custom channel SDK, Amazon/eBay, partial shipments and carrier integrations, advanced returns, demand forecasting, stock valuation and channel analytics (PRD §10). The Phase 1–2 data model must keep these extension points possible without redesign.

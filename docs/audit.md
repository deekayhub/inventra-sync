# Project Audit and Gap Analysis

This document records the verified baseline of Inventra Sync and the gaps that must be closed before or during implementation. It satisfies the Milestone 0 deliverables in [`PRD.md`](../PRD.md) together with [`architecture.md`](architecture.md), [`database.md`](database.md), [`implementation.md`](implementation.md), and [`testing.md`](testing.md).

**Audit status:** complete for Milestone 0. **Date:** 2026-11-24. **Baseline test result:** 89 passing tests, 377 assertions (`vendor/bin/pest --compact`, in-memory SQLite).

## 1. Audit scope

Inspected:

- Installed PHP/Laravel versions and direct Composer dependencies.
- Tenancy, organization/workspace, user, and membership structure.
- Spatie RBAC configuration, seeders, listeners, and middleware aliases.
- Spatie activity logging and existing activity listeners.
- Route surfaces, queue configuration, scheduler, and local lerd workers.
- Frontend stack, Blade shells, design tokens, and build tooling.
- Migrations, configured MySQL schema, and applied migration ledger.
- Existing Pest suite and test bootstrapping.

## 2. Verified baseline

| Concern | Finding |
| --- | --- |
| Application | Existing Laravel 13 (`^13.17`) app; no new application created |
| Runtime | PHP `^8.3`, running PHP 8.4 in lerd; Node 22; site `inventra-sync.test` with TLS |
| Authentication | Laravel Breeze Blade stack: registration, login, password reset, verification, profile |
| Central admin | `is_super_admin` on `users`; `ensure.super_admin` middleware alias in `bootstrap/app.php` |
| Tenancy | `stancl/tenancy` 3.10; single database; `tenants`, `domains`, `users.tenant_id` |
| Tenant resolution | Domain/subdomain middleware in `routes/tenant.php`; suspended tenants blocked by `EnsureTenantIsActive` |
| Tenant features | Cache, filesystem, and queue bootstrappers enabled; no database connection switch |
| Organization label | Existing UI uses "workspace"; PRD "organization" maps to the active tenant |
| RBAC | `spatie/laravel-permission` 8; teams enabled with `tenant_id`; `permission`, `role`, `role_or_permission` aliases registered |
| RBAC lifecycle | `TenancyServiceProvider` wires `SeedTenantPermissions`, `UseTenantPermissionCache`, and `UseCentralPermissionCache` |
| Activity log | `spatie/laravel-activitylog` 5.1.5; `activity_log` table; auth activity and tenant-scoped activity views exist |
| Frontend | Blade + Alpine.js, Tailwind 3 config/tokens, Vite 8, existing `<x-app-layout>` / `<x-settings-layout>` shells |
| Tests | Pest 5; 89 tests pass using in-memory SQLite; helper patterns for tenant login and RBAC already exist |
| Queue | `QUEUE_CONNECTION=database` default; local lerd has `queue`, `schedule`, and `vite` workers running |
| API/webhooks | No `routes/api.php`; no webhook verification or provider endpoints implemented |
| Domain code | No inventory models, services/actions, policies, adapters, events, or jobs yet |

## 3. Reusable components

Reuse these instead of building parallel infrastructure:

- `App\Models\Tenant` with status scopes, subdomain/custom-domain helpers, and `preferredHost()`.
- `App\Models\User` with `BelongsToTenant`, `HasRoles`, and `is_super_admin`.
- `stancl/tenancy` domain middleware and tenant lifecycle events.
- Spatie roles/permissions with `tenant_id` team scoping.
- `PermissionSeeder::GROUPS` and `SeedTenantPermissions`.
- Spatie activity log plus the existing tenant activity screen.
- Breeze auth/profile views and central admin middleware.
- `resources/views/components/` shells, cards, buttons, fields, badges, and `design.md` tokens.
- `tests/Pest.php`, `tenantWithDomain()`, `usingTenantPermissionContext()`, and `assignTenantAdminRole()` patterns.
- Existing database queue, failed jobs, and lerd queue/schedule workers.

## 4. Gap analysis

Severity: **High** blocks the next phase; **Medium** should be resolved inside the phase; **Low** is hygiene.

| # | Gap | Severity | Impact | Minimum compatible action | Phase |
| --- | --- | --- | --- | --- | --- |
| 1 | Seven applied MySQL migrations have no matching files in `database/migrations/`: `2026_11_24_080014_create_products_table`, `2026_11_24_080023_create_product_variants_table`, `2026_11_24_080025_create_warehouses_table`, `2026_11_24_080028_create_inventory_stocks_table`, `2026_11_24_080030_create_inventory_movements_table`, `2026_11_24_080035_seed_default_warehouses`, `2026_11_24_080040_sync_admin_permissions`. | High | Schema cannot be reproduced from the checkout; other environments will not have Phase 1 tables. | Recover the original migrations or author equivalent committed migrations; do not write models against the live-only schema. | 0 |
| 2 | `PermissionSeeder::GROUPS` contains only workspace/roles/members/activity permissions, while the DB also has `products.create`, `products.delete`, `products.edit`, `products.view`, `warehouses.manage`, `warehouses.view`, `inventory.adjust`, `inventory.movements.view`, and `inventory.view`. | High | Permissions are not reproducible and names conflict with PRD §6.3 (`products.update`, `inventory.view_ledger`). | Choose one canonical set, update seeder/routes/policies/tests, and add an intentional alias migration only if old names must remain. | 0 |
| 3 | `routes/api.php` does not exist; no webhook signature verification or dedupe endpoint exists. | High | Shopify/WooCommerce order and inventory webhooks cannot be received. | Add webhook routes only in Phase 4 with signature verification → persist event → acknowledge → dispatch job. | 4 |
| 4 | No inventory service/action namespace is approved; `app/Actions`, `app/Services`, `app/Policies`, `app/Contracts`, `app/Adapters`, `app/Events`, and `app/Jobs` are absent. | Medium | Controllers may become thick and duplicate transactional logic. | Approve `app/Actions` or `app/Services` before the first stock operation; keep models flat as today. | 0 |
| 5 | No shared tenant-scoping concern for inventory models has been verified or added. | Medium | Easy to leak cross-tenant records if every query relies on developer discipline. | Verify existing Stancl/Eloquent concerns, then add one explicit tenant-scope base/trait if needed. | 0–1 |
| 6 | Stock ledger is present only in the live DB as `inventory_movements`; reservations table, outbox, webhook events, and sync logs are absent from the checkout. | High | No reproducible path to no-overselling, idempotency, retry, or sync traceability. | Land committed migrations and tests per Phases 2 and 5 before implementing dependent code. | 2, 5 |
| 7 | No channel credential encryption, adapter contract, Shopify adapter, or WooCommerce adapter exists. | Medium | External API logic would be invented ad hoc and could leak credentials. | Implement `ChannelAdapter` contract and encrypted credential handling before any provider call. | 3 |
| 8 | Existing test suite uses in-memory SQLite; MySQL-specific constraints/cascades are not exercised. | Medium | A passing suite does not prove the production database enforces uniqueness and relational rules. | Add focused MySQL-aware validation during Phase 1/2 migration verification or CI against MySQL. | 1–2 |
| 9 | No inventory tests exist. | Medium | No regression coverage for tenant isolation, SKU uniqueness, stock arithmetic, reservations, or idempotency. | Add Pest tests per phase using the coverage matrix in `docs/testing.md`. | 1–6 |
| 10 | UI label decision is documented as tenant == workspace == organization but not yet confirmed in product copy. | Low | Inconsistent labels can confuse users and tests. | Confirm copy before adding inventory navigation. | 0–1 |
| 11 | `package.json` includes `@tailwindcss/vite` `^4.0.0` while `tailwindcss` is `^3.1.0` and `vite.config.js` uses only `laravel-vite-plugin`. | Low | Unused or conflicting Tailwind tooling can cause build surprises. | Verify `npm run build`; remove the unused plugin only with approval. | 0–7 |
| 12 | No API/webhook setup guide or operator runbook for webhook/outbox failures. | Medium | Phase 7 Definition of Done is not met and operators cannot diagnose sync failures. | Write `docs/api.md` and a runbook when integration work begins. | 7 |
| 13 | No branch-specific database snapshot was taken before this audit; drift was observed but not changed. | Low | Future migration or branch switches could lose the only local copy of the Phase 1 shape. | Take `lerd db snapshot` before reconciling migrations or switching branches. | 0 |

## 5. PRD Milestone 0 acceptance check

| Acceptance criterion | Status | Evidence |
| --- | --- | --- |
| No duplicate core starter kit functionality introduced | Pass | Audit found no new auth, tenancy, RBAC, or activity system; only documentation files added. |
| Existing authentication and tenancy workflows remain functional | Pass | 89 tests pass, including authentication, tenant access, central domain, RBAC, and tenant seeder tests. |
| Proposed changes compatible with current project | Conditional | Architecture and plan reuse installed packages; compatibility depends on resolving gaps 1–2 before Phase 1. |
| Project audit document | Pass | This document. |
| Existing architecture summary | Pass | [`architecture.md`](architecture.md). |
| Gap analysis | Pass | Section 4. |
| Implementation plan | Pass | [`DEVELOPMENT_PLAN.md`](../DEVELOPMENT_PLAN.md) and [`implementation.md`](implementation.md). |

## 6. Decisions already made

- Use the existing Laravel app, auth, tenancy, RBAC, and activity log; do not replace them.
- Tenant == workspace == organization for MVP documentation purposes.
- All business tables carry `tenant_id`; all reads and writes are tenant-scoped.
- Stock quantities come from an append-only ledger; activity logs are for business audit only.
- Inventory writes are transactional, locked, and idempotent; sync events are emitted after commit or via outbox.
- Provider API logic lives behind adapters, never in core inventory code.
- UI follows `design.md`, existing Blade shells, and `config('app.name')` branding.

## 7. Decisions still required

1. Canonical permission names: `products.update` and `inventory.view_ledger`, or intentional aliases for the existing DB names.
2. Action/service namespace for transactional business operations.
3. Whether to recover the missing Phase 1 migrations or reset the local DB from committed migrations.
4. Whether the MySQL-only constraint verification belongs in CI or a manual phase gate.
5. Whether to remove the unused `@tailwindcss/vite` dependency.

## 8. Start gate

Do not begin Phase 1 domain code until:

- [ ] Git state and database are reconciled (gap 1).
- [ ] Permission names are canonical in seeder, DB, routes, policies, and tests (gap 2).
- [ ] Action/service namespace is approved (gap 4).
- [ ] A DB snapshot exists before any migration or branch change (gap 13).
- [ ] The full Pest suite is green after reconciliation.

After these checks, follow [`implementation.md`](implementation.md) Phase 1 and [`testing.md`](testing.md) coverage rules.

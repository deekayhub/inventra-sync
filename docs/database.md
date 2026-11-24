# Database

This document records the current database structure and the schema rules inventory development must follow. It is based on [`PRD.md`](../PRD.md), [`DEVELOPMENT_PLAN.md`](../DEVELOPMENT_PLAN.md), the migrations in `database/migrations/`, and the configured MySQL database.

## 1. Storage model

Inventra Sync uses a **single MySQL database** with shared tables and tenant isolation through `tenant_id`. There are no per-tenant databases and no database connection switching.

Local configuration (`.env.example`):

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=inventra_sync
```

Every tenant-owned business row must carry `tenant_id` with a value matching `tenants.id` (`varchar(255)`, UUID). Application code must scope reads and writes to the active tenant. A valid session or an ID from the request is not proof of tenant ownership.

## 2. Current baseline tables

### 2.1 Tenancy and identity

| Table | Key columns | Notes |
| --- | --- | --- |
| `tenants` | `id` (string, PK), `name`, `email`, `data` (JSON) | `data->status` stores active/suspended state |
| `domains` | `id`, `domain`, `tenant_id` | Subdomain labels and custom hostnames |
| `users` | `id`, `tenant_id`, `name`, `email`, `password`, `is_super_admin` | `tenant_id` FK cascades from `tenants` |
| `tenant_user_impersonation_tokens` | `token`, `tenant_id`, `user_id`, `redirect_url` | Stancl impersonation feature |
| `sessions` | `id`, `user_id`, `last_activity` | Database sessions |
| `password_reset_tokens` | `email`, `token`, `created_at` | Breeze password reset |

### 2.2 RBAC

| Table | Key columns | Notes |
| --- | --- | --- |
| `permissions` | `id`, `name`, `guard_name` | Unique `(name, guard_name)` |
| `roles` | `id`, `tenant_id`, `name`, `guard_name` | Unique `(tenant_id, name, guard_name)` when teams are enabled |
| `model_has_roles` | `role_id`, `model_type`, `model_id`, `tenant_id` | Team-scoped role assignments |
| `model_has_permissions` | `permission_id`, `model_type`, `model_id`, `tenant_id` | Team-scoped direct permissions |
| `role_has_permissions` | `permission_id`, `role_id` | Role permission matrix |

`config/permission.php` sets `teams = true` and `team_foreign_key = tenant_id`. New permissions must be added to `PermissionSeeder::GROUPS`; `SeedTenantPermissions` grants them to each tenant's protected `Admin` role.

### 2.3 Infrastructure and activity

| Table | Purpose |
| --- | --- |
| `activity_log` | Spatie activity records with `log_name`, morphing subject/causer, `event`, and JSON properties |
| `cache`, `cache_locks`, `job_batches`, `jobs`, `failed_jobs` | Cache and queue infrastructure |
| `migrations` | Applied migration ledger |

## 3. Phase 1 inventory tables in the local database

The configured local database currently contains these Phase 1 tables. They are the intended shape for product, warehouse, and stock foundations, but see the drift warning in section 6.

### 3.1 `products`

| Column | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | bigint unsigned | — | Primary key |
| `tenant_id` | varchar(255) | — | FK to `tenants`, cascade delete |
| `name` | varchar(255) | — | Required |
| `description` | text | null | Optional |
| `type` | varchar(255) | `simple` | Simple or variable |
| `status` | varchar(255) | `draft` | Draft, active, archived |
| `low_stock_threshold` | int unsigned | `5` | Report threshold |
| `buffer_stock` | int unsigned | `0` | Product-level fallback buffer |

Indexes: primary `id`, `(tenant_id, name)`, `(tenant_id, status)`.

### 3.2 `product_variants`

| Column | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | bigint unsigned | — | Primary key |
| `tenant_id` | varchar(255) | — | FK to `tenants`, cascade delete |
| `product_id` | bigint unsigned | — | FK to `products`, cascade delete |
| `sku` | varchar(255) | — | Required, unique per tenant |
| `barcode` | varchar(255) | null | Optional |
| `title` | varchar(255) | null | Variant label |
| `is_primary` | boolean | `0` | Primary variant marker |
| `status` | varchar(255) | `active` | Variant status |

Indexes: primary `id`, `(product_id, is_primary)`, unique `(tenant_id, sku)`.

### 3.3 `warehouses`

| Column | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | bigint unsigned | — | Primary key |
| `tenant_id` | varchar(255) | — | FK to `tenants`, cascade delete |
| `name` | varchar(255) | — | Required |
| `code` | varchar(255) | — | Unique per tenant |
| `address` | varchar(255) | null | Optional |
| `is_default` | boolean | `0` | Default warehouse marker |
| `default_marker` | varchar(255) | null | Unique generated marker to enforce one default per tenant |
| `is_active` | boolean | `1` | Active/deactivated state |

Indexes: primary `id`, unique `(tenant_id, code)`, unique `default_marker`.

The generated `default_marker` pattern enforces the PRD rule of one default warehouse per tenant while preserving the ability to add warehouses later. Deletion must be blocked in the service layer when stock or historical movements reference the warehouse.

### 3.4 `inventory_stocks`

| Column | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | bigint unsigned | — | Primary key |
| `tenant_id` | varchar(255) | — | FK to `tenants`, cascade delete |
| `warehouse_id` | bigint unsigned | — | FK to `warehouses`, cascade delete |
| `product_variant_id` | bigint unsigned | — | FK to `product_variants`, cascade delete |
| `on_hand` | int | `0` | Physical stock |
| `reserved` | int | `0` | Reserved stock |
| `low_stock_threshold` | int unsigned | `5` | Stock alert threshold |
| `buffer_stock` | int unsigned | `0` | Safety buffer |

Indexes: primary `id`, unique `(tenant_id, warehouse_id, product_variant_id)`, `(tenant_id, product_variant_id)`, plus FK indexes.

Derived values:

```text
available_stock      = on_hand - reserved
channel_available    = MAX(0, on_hand - reserved - buffer_stock)
```

Calculate these in one shared place (model accessor or value object), never in individual Blade views or reports.

### 3.5 `inventory_movements`

| Column | Type | Default | Notes |
| --- | --- | --- | --- |
| `id` | bigint unsigned | — | Primary key |
| `tenant_id` | varchar(255) | — | FK to `tenants`, cascade delete |
| `warehouse_id` | bigint unsigned | — | FK to `warehouses`, cascade delete |
| `product_variant_id` | bigint unsigned | — | FK to `product_variants`, cascade delete |
| `actor_id` | bigint unsigned | null | FK to `users`, set null on delete |
| `movement_type` | varchar(255) | — | Adjustment, reservation, commitment, return, etc. |
| `quantity_change` | int | — | Signed delta |
| `quantity_after` | int | — | Resulting on-hand quantity |
| `reason` | varchar(255) | — | Business reason |
| `source` | varchar(255) | `manual` | Manual, order, webhook, reconciliation |
| `idempotency_key` | varchar(255) | — | Unique deduplication key |

Indexes: primary `id`, unique `idempotency_key`, `(tenant_id, product_variant_id, created_at)`, plus FK indexes.

This table is the authoritative, append-only stock ledger. Do not add update/delete actions for it in normal application workflows. When historical preservation is enforced, review the current cascade-delete foreign keys: deleting a variant or warehouse must not silently remove historical movements.

## 4. Schema planned for later phases

These tables are requirements, not yet present in the repository. Add them only when their phase starts, with migrations and tests.

| Phase | Tables | Key constraints |
| --- | --- | --- |
| 2 | `inventory_reservations` | Unique external reference; status `reserved`, `released`, `committed`, `expired`; tenant and variant scope |
| 3 | `channel_connections`, `channel_credentials`, `channel_listings`, `channel_product_mappings`, `channel_inventory_settings` | Encrypted credential values; one connection per tenant/provider/account; listing maps to at most one internal variant; per-listing buffer and paused flag |
| 4 | `orders`, `order_items`, `fulfilments`, `shipments`, `cancellations`, `returns`, `return_items` | Unique `(channel_connection_id, external_order_id)`; order items preserve product/price snapshots |
| 5 | `inventory_outbox_events`, `webhook_events`, `channel_sync_logs` | Unique webhook event key; outbox status/attempts/next try; per-listing sync history; stale-event version guard |
| 6 | `reconciliation_runs`, `reconciliation_items` | Record discrepancies only; corrections go through normal ledger adjustments |

`product_categories`, `product_images`, `product_attributes`, and import/export records should be added when the catalog phase needs them; do not create empty tables without a workflow that uses them.

## 5. Migration and integrity conventions

- Create schema only through migrations: `php artisan make:migration --no-interaction create_<table>_table`.
- Use descriptive snake_case table and column names; singular model names in `app/Models/`.
- Use `bigint unsigned` IDs and Laravel timestamps for new business tables; keep `tenants.id` as a string UUID.
- Add `tenant_id` to every business table plus a foreign key to `tenants` where practical.
- Add composite indexes for common tenant-scoped filters: `tenant_id`, external IDs, SKUs, statuses, timestamps.
- Enforce uniqueness in the database, not only in validation: SKU per tenant, stock per tenant/warehouse/variant, external order ID per connection, webhook event key, idempotency key.
- Prefer archival/status changes over hard deletes for referenced records.
- Make migrations reversible where practical; never run destructive database commands as part of routine development.
- Add permissions through `PermissionSeeder`, not direct SQL inserts.
- Recreate factory and seeder data through Pest factories/seeders, not manual database edits.

## 6. Local-state drift warning

The configured `inventra_sync` database has applied a second migration batch containing Phase 1 tables and permission-sync migrations, but the corresponding migration files are **not present in the current working tree**. The database also contains inventory/product/warehouse permission names that the repository's `PermissionSeeder::GROUPS` does not yet define.

Treat the live schema as a useful reference, not as reproducible source code. Before continuing implementation, either:

1. Recover or author migrations that reproduce these tables and permission data, keeping the local database in step with code; or
2. Reset/recreate the development database from the migrations available in the checkout.

Do not write new models or controllers on the assumption that these tables exist in every environment until their migrations are committed.

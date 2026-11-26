# Testing

This document defines the test strategy for Inventra Sync. The project uses Pest 5 with Laravel feature and unit tests. Existing tests are the convention reference; do not rewrite them merely to match general best practices.

## 1. Commands

```bash
php artisan test --compact                 # full suite
vendor/bin/pest                             # direct Pest runner
vendor/bin/pest tests/Feature/TenantRbacTest.php
vendor/bin/pest --filter=duplicate_webhook
vendor/bin/pint --dirty --format agent      # after PHP changes
npm run build                              # after Blade/CSS/JS changes
```

Always run the narrowest relevant test while changing code, then the full suite before declaring a phase done. The current checkout baseline after Phase 7 is 163 passing tests and 803 assertions (`php artisan test --compact`, in-memory SQLite).

## 2. Test structure

| Location | Use |
| --- | --- |
| `tests/Feature/` | HTTP requests, tenant access, RBAC, database effects, jobs/events through real app paths |
| `tests/Unit/` | Framework-independent calculations and value objects only |
| `tests/Pest.php` | Shared expectations and helpers |
| `tests/TestCase.php` | Application test bootstrap |

`tests/Pest.php` binds Feature tests to `Tests\TestCase` with `RefreshDatabase`; keep this existing convention. Helper functions include `usingTenantPermissionContext()` and `assignTenantAdminRole()` for Spatie teams.

Create new test files with:

```bash
php artisan make:test --pest <Name>Test
php artisan make:test --pest <Name>Test --unit
```

Name test files `{ClassName}Test.php` at the same relative path as the class under test when practical. Use one Pest declaration style per file, prefer `it()` for behavior, and name the result plus condition: `it('returns 404 for a product from another tenant', ...)`. Avoid vague names such as `it('works correctly')`.

## 3. Test design rules

- Write a feature test first for behavior reachable through a request.
- Write a unit test only for logic that does not need the framework.
- Test observable behavior, not implementation text or configuration declarations.
- Each test creates its own mutable records; `beforeEach()` is for configuration only.
- Use factories and named factory states for new models; use `create()` when the database is needed and `make()` when it is not.
- Use datasets for enum cases, roles, boundary values, and invalid inputs with identical setup and assertions.
- Use `Event::fake()`, `Queue::fake()`, `Http::fake()`, `Storage::fake()`, `freezeTime()`, `travelTo()`, `Str::createRandomStringsUsing()`, and `Sleep::fake()` as appropriate. Create fakes inside the test that needs them.
- Call `Http::preventStrayRequests()` for adapter tests and fake exact provider endpoints.
- Assert one fact once. For write operations, assert the response, the database state, and the side effects (events/jobs/activity/logs) that matter.
- Use named response assertions (`assertOk()`, `assertForbidden()`, `assertNotFound()`, `assertSessionHasErrors()`) and Laravel database assertions. Use `expect()` chains for returned values.
- Do not calculate expected values with the implementation's own logic.
- Keep each test passing alone and in the full suite in any order.

## 4. Existing patterns to reuse

Tenant HTTP tests create a tenant and domain, then authenticate with a signed token from `App\Http\Controllers\Tenant\LoginController`:

```text
tenantWithDomain('Acme', 'acme')
User::factory()->create(['tenant_id' => $tenant->id])
LoginController::issueToken($user, $tenant)
GET http://acme.saas.test/login/{token}
```

RBAC tests use `rbacTenantWithAdmin()`, `usingTenantPermissionContext()`, and `assignTenantAdminRole()`. Follow these helpers instead of inventing a second tenant-login pattern.

Cross-tenant requests should generally assert `404`, not `403`, so a tenant cannot learn that another tenant's record exists. Permission-denied screens should assert `403`.

## 5. Required coverage by module

### Phase 0 — Foundation

- Migrations run cleanly and route list works.
- Existing authentication, tenancy, and RBAC tests remain green.
- New permissions are granted to each tenant's `Admin` role and isolated between tenants.

### Phase 1 — Product catalog and warehouse

- Authorized create/update/archive/read flows persist the expected state.
- Missing/invalid fields produce the expected validation messages.
- SKU uniqueness is enforced per tenant and allows the same SKU in another tenant.
- Cross-tenant product/variant/warehouse IDs return `404`.
- Archive preserves historic references; no hard delete removes referenced data.
- One default warehouse per tenant; creating a second default is rejected.
- Deleting a warehouse with stock or movements is blocked.
- Import/export jobs are queued and process in chunks.

Covered by `tests/Feature/TenantCatalogTest.php`. Export tests disable `tenancy.filesystem.disks` for `local` so `Storage::fake('local')` survives tenant filesystem bootstrapping.

### Phase 2 — Inventory engine

- Every adjustment creates exactly one ledger row with actor, reason, source, and idempotency key.
- Retried adjustment/reservation does not create a duplicate movement.
- Available stock equals on-hand minus reserved; channel-available clamps at zero.
- Reservation rejects when available stock is insufficient.
- Concurrent reservation attempts cannot oversell the same units.
- Release, commit, and expiration transition reservations correctly.
- Cancellation cannot release the same reservation twice.
- Failed transaction leaves stock and ledger unchanged.
- Users without `inventory.*` permissions cannot view or mutate stock.

Covered by `tests/Feature/TenantInventoryTest.php`: 12 tests and 109 assertions passed. The suite covers ledger/activity/event side effects, idempotent adjustment and reservation retries, negative-stock rejection with no ledger row, oversell prevention, release/commit/expire transitions, permission gates, cross-tenant reservation access returning `404`, page rendering, and validation messages. MySQL-only locking/index verification remains a deferred manual or CI gate.

### Phase 3 — Channels and mappings

- Credentials round-trip through encryption and never appear in responses, arrays, activity logs, or sync logs.
- Invalid credentials fail safely and do not activate a connection.
- One external listing cannot map to unrelated internal variants.
- Unmapped listings are skipped by inventory sync.
- Channel connections are isolated by tenant.
- Adapter tests use `Http::preventStrayRequests()` and fake exact Shopify/WooCommerce endpoints.

### Phase 4 — Orders and fulfilment

- Duplicate webhook events create one order and one reservation.
- External order IDs are unique per channel connection.
- Order items preserve product/SKU/title/price snapshots.
- Failed order import can be retried safely.
- Fulfilment commits reservation and deducts stock exactly once.
- Cancellation releases stock exactly once.
- Returns require condition classification before creating sellable/damaged ledger entries.
- Status transitions reject invalid jumps.

### Phase 5 — Synchronization

- Outbox event is written in the same transaction as the stock change.
- Dispatcher retries temporary failures with backoff and records permanent failures.
- Older queued events cannot overwrite newer stock values.
- Webhook events are deduplicated by external event ID.
- Sync logs record per-listing, per-channel attempts without credentials.
- Manual retry is permission-gated and observable.

### Phase 6 — Reports and diagnostics

- Reports respect tenant and permission scope.
- Movement report totals match ledger rows.
- Low-stock/out-of-stock boundaries are tested with known values.
- Reports do not leak other tenants' counts, names, or order data.
- Reconciliation records discrepancies only; it never silently edits stock.
- List pages have no N+1 or slow-query findings after diagnostics.

## 6. Security and authorization tests

For each new endpoint or action, test the applicable cases:

- Missing authentication.
- Insufficient permission, using a dataset over roles where practical.
- Cross-tenant record access.
- Route/model scope constraint failure.
- Invalid payload and validation message.
- Valid payload with persisted state and side effects.
- Escaping of dangerous user-provided content in HTML and mail.
- Injection into dynamic sort/filter fields.
- Rejection of unexpected mass-assignment keys.

Keep the complete permission matrix in policy tests; keep one HTTP test to prove the endpoint calls authorization. Never log or assert raw credentials, tokens, webhook signatures, or secrets.

## 7. Browser and diagnostics checks

Real-browser Pest tests require a browser package that is not installed. For UI changes, use the lerd diagnostics loop instead:

1. Enable `diag browser_toggle` for the site before exercising the page.
2. Load the affected tenant/central pages in the browser.
3. With dumps enabled, read each response's `X-Lerd-Rid` and check `request lenses`.
4. Read `diag browser_events` for JavaScript errors, failed requests, and SPA navigation errors.
5. Use `route_timing` and `optimize_route` on real traffic; fix new N+1 and slow-query findings.
6. Confirm `npm run build` succeeds and the change renders in the expected shell (guest, tenant, or central admin).

A clean page load is not proof that the frontend is error-free; complete the diagnostics above before finishing browser-facing work.

## 8. Phase exit checklist

- [ ] Narrow tests pass after each change.
- [ ] Full suite passes before the phase is merged.
- [ ] New tests cover success and important failure paths.
- [ ] Tenant isolation and permission gates are explicitly tested.
- [ ] Transactions, idempotency, and ledger behavior are tested for stock operations.
- [ ] External providers are faked; no test reaches the network.
- [ ] `vendor/bin/pint --dirty --format agent` has been run.
- [ ] `npm run build` has been run for frontend changes.
- [ ] Browser/request diagnostics are clean for browser-facing changes.

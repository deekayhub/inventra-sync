# Operations Runbook

This runbook covers the production checks needed after the Phase 7 hardening work.

## 1. Required processes

Inventra Sync needs these long-running processes:

- Queue worker: consumes webhook imports, product imports, exports, inventory outbox pushes, and reconciliation jobs.
- Scheduler: dispatches outbox flushing and channel reconciliation from `routes/console.php`.
- Vite build: generated assets must exist in production; run `npm run build` during deploy.

Verify schedules and routes:

```bash
php artisan schedule:list
php artisan route:list --except-vendor
```

Restart queue workers after deploying code:

```bash
php artisan queue:restart
```

## 2. Production environment checklist

Set these in the production `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.example.com
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database-uuids
FAILED_JOB_LOG_CHANNEL=stack
```

Use an HTTPS `APP_URL` on the central domain; tenant subdomains are derived from it. Keep `SESSION_DOMAIN=null` so the signed handoff token authenticates each tenant domain. If a Slack or other error channel is configured in `config/logging.php`, set `FAILED_JOB_LOG_CHANNEL` to that channel instead of `stack`.

After changing config or env:

```bash
php artisan config:cache
php artisan config:show app.name
php artisan config:show app.url
php artisan config:show cache.prefix
php artisan config:show session.cookie
php artisan config:show queue.default
php artisan config:show queue.failed.driver
php artisan config:clear
```

`cache.prefix` and `session.cookie` must be unique per application instance.

## 3. Webhook and outbox failures

Webhook flow:

1. `POST /webhooks/{provider}` verifies the provider signature.
2. A `webhook_events` row is created with `status=pending`.
3. `ProcessChannelWebhook` imports the order and marks the row `processed`.
4. Raw webhook payloads are cleared after successful processing.

Outbox flow:

1. Stock changes create `inventory_outbox_events` rows with idempotency keys.
2. `DispatchInventoryOutbox` atomically claims up to 50 due rows.
3. `ProcessInventoryOutboxEvent` pushes quantity to the channel adapter.
4. Rows end as `success`, `retrying`, `failed`, or `skipped`.

Triage commands:

```bash
php artisan queue:failed
php artisan queue:retry <failed-job-id>
php artisan queue:forget <failed-job-id>
```

Query the operational tables with normal SQL or the Boost database tools:

- `webhook_events`: filter by `tenant_id`, `provider`, `status`, `external_event_id`.
- `inventory_outbox_events`: filter by `tenant_id`, `channel_listing_id`, `status`, `next_try_at`.
- `channel_sync_logs`: filter by `tenant_id`, `direction`, `status`, `created_at`.
- `reconciliation_runs` and `reconciliation_items`: filter by `tenant_id` and `status`.

Common causes:

- `webhook_events.status=failed`: signature verified but order payload could not be parsed or imported; inspect `last_error`, then re-send from the provider or reset the row to `pending`.
- `inventory_outbox_events.status=retrying`: provider was unavailable or rate-limited; `next_try_at` controls retry time.
- `inventory_outbox_events.status=failed`: permanent adapter/configuration error; fix credentials, listing mapping, or channel status, then create a new inventory event or reset the row to `pending`.
- `inventory_outbox_events.status=skipped`: listing, connection, or inventory setting is inactive/paused; no retry is needed.

Final failures are logged to `config('queue.failed_alert_channel')` with the message `Queue job failed` or `Inventory outbox event failed`. Alert on those messages and include the tenant id, job/event id, and provider in the incident ticket.

Do not replay a webhook unless the original event is `failed` or the provider can prove it was not applied. Duplicate order imports are blocked by external order id uniqueness, but unnecessary replay hides the original failure cause.

## 4. Backups and restore drills

Before each production migration or deploy that changes schema:

1. Take a full database snapshot through the hosting provider or the local lerd `db` `snapshot` tool.
2. Record the git commit and migration batch being deployed.
3. Run the migration on staging first with a copy of production data.
4. Verify tenant subdomains, login handoff, queue processing, and one channel sync after migration.

Local lerd workflow:

- Use the lerd MCP `db` `snapshot` action with a descriptive name.
- List snapshots with `db` `snapshots` and check `auto`, `kept`, and any `expires_at`.
- Restore only with `db` `restore` after confirming the target database; it is destructive.

Production restore drill (at least once per release cycle):

1. Restore the newest snapshot into an isolated staging database.
2. Run `php artisan migrate --force`.
3. Run `php artisan test --compact` against that staging database copy if it is safe to do so.
4. Verify one tenant can log in, view inventory, enqueue a sync, and read a report.
5. Record the drill result and snapshot age in the release checklist.

Do not rely on queue jobs stored in the database as a backup source. Failed jobs are for diagnosis, not recovery.

## 5. Security notes

- Tenant handoff tokens expire after 15 minutes and are single-use; replay returns `404`.
- Login, registration, password reset, tenant operations, and webhooks have named rate limiters in `AppServiceProvider`.
- Admin role assignment is limited to existing Admins; tenant owners cannot escalate themselves through member forms.
- Tenant routes require verified email addresses through `verified:tenant.verification.notice`.
- Provider API credentials are encrypted at rest; never log decrypted credentials or raw webhook payloads.
- WooCommerce and Shopify adapter URLs must be HTTPS public hosts; redirects are not followed.

## 6. Release verification

Before marking a release complete:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run build
php artisan route:list --except-vendor
php artisan schedule:list
git diff --check
```

Then open one tenant dashboard, one inventory page, one channel page, and one report page. Check browser errors and backend logs after each page load. A clean HTTP response alone is not proof that the page rendered without frontend errors.

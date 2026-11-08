## v3.2 managed SMS provider update

See [README_SMS_V3_2_FA.md](README_SMS_V3_2_FA.md) for Kavenegar provider management, encrypted API keys, additive SQL Server migration and chunked delivery command (`journey-builder:sms-dispatch`). Delivery is disabled by default. Older SMS nodes retain their legacy API behavior.

# Laravel Journey Builder — v3.1 preview

General-purpose, drag-and-drop journeys for **Laravel 12 / PHP 8.2+**. Includes SQL Server-aware queries, conditional enrollments, delays, independent journey state, SMS and HTTPS API nodes, dashboard reports, manual retries, and opt-in compact retention for completed users.

> **Preview / staging**: PHP/JS syntax was checked locally, but the package has **not** passed an end-to-end SQL Server, external messaging, concurrency or migration-upgrade test in this environment. Do not enable production sending before a staging trial.

Package name: `hadiabedzadeh/laravel-journey-builder`. Source is suitable to commit to the package's GitHub repository; upgrading consumers requires a push/release to that repository.

Read [README_V3_1_FA.md](README_V3_1_FA.md) for the installation and migration steps, and [README_FA.md](README_FA.md) for older package design context.

## Changes in v3.1

- List view hides internal identifier and successful SMS/API count columns. Internal flow code stays in the server data model for API placeholders; dashboard shows no internal code. Journey editor is now titled **Edit Journey**.
- Failed SMS/API responses **stop at their current node** instead of silently traversing a failure edge. On a retry the original `node_id` remains unchanged. Failures can be retried via the per-run dashboard button or `journey-builder:retry` with explicit external-risk approval; unknown results remain blocked pending reconciliation.
- `journey-builder:prune` has a safe dry-run default. With `JOURNEY_BUILDER_PURGE_ON_EXIT=true` after migration and a stable 32+ character `JOURNEY_BUILDER_ENROLLMENT_HASH_KEY`, exited run details can be compacted automatically. Aggregated flow/node counts are preserved and a keyed HMAC digest replaces raw user IDs for duplicate prevention.
- A new **additive** migration creates `jb_run_hashes`, `jb_flow_totals`, `jb_node_totals`; existing tables are not altered or dropped.

## Typical commands

```bash
php artisan journey-builder:status --flow=3
php artisan journey-builder:retry --run=123
php artisan journey-builder:retry --run=123 --retry-external --ack-duplicate-risk --commit
php artisan journey-builder:prune --flow=3 --batch=200
php artisan journey-builder:prune --flow=3 --batch=200 --commit
```

Retry and prune are operations with side effects only with `--commit`. Retrying does not send immediately: the next `tick` does. Failed/unknown runs are retained. Unknown SMS/API responses cannot be re-sent by the retry action because HTTP uncertainty does not prove non-delivery.

Dashboard requires host authentication/authorization; default is a Laravel Gate, with loopback-local-only development mode. SMS and API senders are disabled by default. The package deliberately does not distribute a font binary: copy your own licensed Vazirmatn font to the published assets path if desired.

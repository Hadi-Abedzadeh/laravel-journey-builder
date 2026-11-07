# Laravel Journey Builder — reusable Composer package (preview)

Drag-and-drop journeys, per-flow enrollment and scheduling, SMS/HTTPS API actions, reports, encrypted per-node API credentials and safe operational commands. Laravel 12 / PHP 8.2+. Primary SQL Server support; migration has MySQL/PostgreSQL fallback. **Preview only: no production SQL Server or SMS end-to-end test has been performed.**

Read **[README_FA.md](README_FA.md)** for installation, migration, schema compatibility, security and publishing to Packagist. The package is **not yet published** on Packagist; change the placeholder `your-vendor` in `composer.json` and publish your own Git repository.

### Feature highlights

- `journey-builder:enroll`, `journey-builder:tick`, `journey-builder:status`, `journey-builder:retry` (dry-run by default)
- Package auto-discovery through `extra.laravel.providers`
- Dashboard routes and views loaded automatically; no built-in login/password/session management
- Authentication delegated to host Laravel app (default `manage-journeys` Gate, optional `auth` mode) or loopback-only in `local` development
- SQL Server initial schemas specify `VARCHAR`, `NVARCHAR`, `BIGINT`, `INT`, `SMALLINT`, `DATETIME2(3)` explicitly
- Existing `jb_*` tables are **not changed** by the new migration; use `sql/sqlsrv_audit.sql` to inspect legacy type mismatches
- No destructive database rollback
- Only HTTP(S) allowlisted host API calls; per-node credentials encrypted with `APP_KEY`

### Recommended release workflow

See the Persian guide. Publish as a **prerelease**, such as `v0.1.0-beta.1`, until deployment and concurrency tests pass.

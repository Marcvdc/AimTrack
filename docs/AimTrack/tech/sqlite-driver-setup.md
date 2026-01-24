# SQLite Driver Setup for Tests
Status: draft – update this doc when the driver has been installed.  
Last updated: 2026-01-24 by Cascade

## Context
The automated test suite (Pest) runs against an in-memory SQLite database (see `phpunit.xml`). On the current dev machine the PHP runtime lacks the `pdo_sqlite`/`sqlite3` extensions, causing test runs to fail with `could not find driver` as soon as PHPUnit tries to boot the database.

## Installation Steps (Ubuntu/Debian)
1. Install the SQLite extensions for both CLI and FPM:
   ```bash
   sudo apt-get update
   sudo apt-get install php8.4-sqlite3
   ```
2. Verify that the extension is enabled:
   ```bash
   php -m | grep -i sqlite
   ```
   You should see `pdo_sqlite` and `sqlite3` in the module list.
3. Restart PHP-FPM / queue workers if applicable (not required for CLI-only).
4. Re-run the test suite:
   ```bash
   php artisan test # or vendor/bin/pest
   ```

## Alternative (If SQLite cannot be installed)
- Switch the test configuration to use PostgreSQL by setting the `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values in `phpunit.xml` or `.env.testing`. This requires a dedicated test database and will run slower than SQLite.

## Next Actions
- [ ] Install/enable `pdo_sqlite` on the dev/test environment.
- [ ] Remove this warning once tests run green with SQLite.

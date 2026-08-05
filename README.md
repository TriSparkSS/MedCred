# Revantage CRM

Laravel + Livewire admin CRM for healthcare credentialing: practices, providers, payer applications, documents, tasks, reports, and a live Microsoft Graph Email Center.

## Requirements

- PHP **8.3+** with extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml`
- [Composer](https://getcomposer.org/) 2.x
- Node.js **18+** and npm (for Vite assets)
- Optional: [Laravel Herd](https://herd.laravel.com/) (recommended on Windows/macOS)

## Quick start

```bash
# 1. Install PHP dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database (default: SQLite)
# Ensure database/database.sqlite exists, or set DB_* in .env for MySQL/SQL Server
touch database/database.sqlite   # Unix / Git Bash
# Windows PowerShell: New-Item -ItemType File -Path database/database.sqlite -Force

php artisan migrate --force
php artisan db:seed --force

# 4. Storage symlink (uploads / public files)
php artisan storage:link

# 5. Frontend assets
npm install
npm run build
```

Or use the Composer setup script (installs deps, `.env`, key, migrate, npm build):

```bash
composer run setup
php artisan db:seed --force
php artisan storage:link
```

## Configure `.env`

| Variable | Purpose |
|----------|---------|
| `APP_URL` | Exact URL you open in the browser (e.g. `http://revantage-crm.test` with Herd, or `http://127.0.0.1:8000`). **Must match** or Livewire actions can fail. |
| `DB_CONNECTION` | `sqlite` (local default), `mysql`, or `sqlsrv` (production target) |
| `GRAPH_TENANT_ID` | Entra tenant ID (Email Center) |
| `GRAPH_CLIENT_ID` | Application (client) ID — GUID, **not** the secret |
| `GRAPH_CLIENT_SECRET` | Client secret value |
| `GRAPH_MAILBOX` | Mailbox to read (e.g. `credentialing@yourdomain.com`) |

SMTP for **sending** mail is configured in Admin → **Settings → Mail** (stored encrypted), not only via `MAIL_*` env.

After changing `.env`:

```bash
php artisan config:clear
```

### Microsoft Graph (Email Center)

Email Center loads Inbox / Sent **live** from Graph (messages are not stored in the DB). Create an Entra app with application permission **`Mail.Read`** + admin consent, then set the `GRAPH_*` variables above.

Optional SMTP-only local work: leave Graph empty; Email Center will show a configuration notice until Graph is set.

## Run the app

### Option A — Laravel Herd (recommended)

1. Park or link the project in Herd.
2. Open `http://revantage-crm.test` (or your Herd site URL).
3. Set `APP_URL` to that same URL.
4. Build assets once (`npm run build`) or run Vite in another terminal: `npm run dev`.

### Option B — Artisan + Vite

```bash
# Terminal 1 — app
php artisan serve

# Terminal 2 — assets (hot reload)
npm run dev
```

Open `http://127.0.0.1:8000` and set `APP_URL=http://127.0.0.1:8000`.

### Option C — All-in-one (server + queue + Vite)

```bash
composer run dev
```

Starts `php artisan serve`, `queue:listen`, and `npm run dev` together.

### Queue worker (optional)

If you use background jobs (imports, mail sync cache clear, etc.):

```bash
php artisan queue:work
```

Ensure `QUEUE_CONNECTION=database` (default in `.env.example`) and migrations have been run.

## Default admin login

After seeding:

| Field | Value |
|-------|--------|
| Login URL | `/sp/login` |
| Username | `superadmin` |
| Email | `admin@hrm.com` |
| Password | `112233` |

Change this password in production.

Other portals:

- Provider: `/provider/login`
- Practice: `/practice/login`

## Useful Artisan commands

```bash
php artisan migrate              # Run migrations
php artisan db:seed              # Seed roles, permissions, master data
php artisan db:seed --class=DummyContentSeeder   # Optional demo data
php artisan db:seed --class=AdminPermissionSeeder # Re-seed admin permissions only
php artisan storage:link
php artisan config:clear
php artisan view:clear
php artisan mailbox:sync         # Clear Graph mailbox list cache
php artisan test                 # Run Pest tests
```

## Project notes

- **Stack:** Laravel 13, Livewire 4, Spatie Permission, Vuexy-style admin assets under `public/assets`.
- **Email Center:** Live Graph read + SMTP send; case links stored as Message-ID → case only (`email_case_links`).
- **Credentialing cases:** Practices need a 3-letter `client_code` for case numbers like `RVA-2026-0001`.
- **Production DB:** Prefer SQL Server (`DB_CONNECTION=sqlsrv`) per deployment target; local default is SQLite.

## License

Proprietary — Revantage / project owners. Laravel framework components remain under the [MIT license](https://opensource.org/licenses/MIT).

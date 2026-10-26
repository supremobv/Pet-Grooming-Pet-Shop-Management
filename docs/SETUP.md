# Installation & configuration

## Requirements

- Windows/Laragon with MySQL 8.0+ (tested on the running Laragon MySQL server).
- PHP 8.2+ with Composer 2. Use a currently security-patched PHP release. The detected local development executable is PHP 8.2.28; it needs upgrading before any production/network deployment. PHP 8.2.35 is a published security fix as of October 26, 2026; check the official PHP downloads for newer fixes.
- PHP extensions: PDO, pdo_mysql, mbstring, openssl, tokenizer, XML/DOM, ctype, fileinfo, curl, session, GD, and zip. SQLite extensions are needed for the default test suite.
- A modern browser. JavaScript enables POS search, totals, print controls, confirmations, and mobile navigation. Server validation remains authoritative.

Node, npm, React, Vite, Docker, Redis, queues, and cloud hosting are not required.

## Fresh setup

1. Open this folder in a Laragon terminal (so PHP and Composer are on PATH). Start MySQL in Laragon.
2. Run `composer install --prefer-dist`. Copy `.env.example` to `.env` if it does not exist. Never overwrite an existing `.env` or regenerate its APP_KEY during normal updates.
3. In HeidiSQL/phpMyAdmin/MySQL, create a dedicated database:

```sql
CREATE DATABASE paw_polish CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

4. Configure `.env`:

```dotenv
APP_NAME="Paw and Polish"
APP_ENV=local
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Asia/Singapore
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=paw_polish
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Laragon's empty root password is only a local default. Use your actual credentials if different. Use a dedicated least-privilege MySQL user for deployment. Do not commit `.env`.

5. Initialize:

```powershell
php artisan key:generate
php artisan migrate
php artisan app:admin owner@yourbusiness.com --name="Business Owner"
```

The administrator command prompts privately for a password: 12–72 characters, upper/lowercase, number, symbol. There is no public account registration or committed default password. Re-run the command to recover an administrator; it asks before resetting an existing account.

Alternatively, after configuring `.env` and creating the database, run:

```powershell
.\scripts\setup.ps1 -AdminEmail owner@yourbusiness.com
```

6. Optional sample data, only on an empty local shop database after creating an admin:

```powershell
php artisan db:seed --class=DemoSeeder
```

DemoSeeder refuses a nonempty shop and refuses production mode. `DatabaseSeeder` intentionally creates no accounts or default business data. For local demonstrations, `php artisan app:admin admin@example.test --generate` creates a random password in ignored `storage/app/local-access.txt`; do not use `--generate` in production.

7. Run:

```powershell
.\scripts\start.ps1
# Or:
composer dev
# Or:
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Visit http://127.0.0.1:8000. Stop the development server with Ctrl+C. If PowerShell blocks scripts, run the plain PHP command instead; changing machine-wide execution policy is unnecessary.

8. Sign in, customize **Business settings**, set currency/tax before the first sale, then create team accounts, services, customers/pets, suppliers/products, and opening stock.

## Laragon Apache virtual host

The project can remain in its current directory. Create an Apache virtual host whose **DocumentRoot is the project's `public` directory**, not the project root. For this workspace:

```apache
<VirtualHost *:80>
    ServerName paw-polish.test
    DocumentRoot "C:/Users/primo/Desktop/Pet Grooming & Pet Shop Management/public"
    <Directory "C:/Users/primo/Desktop/Pet Grooming & Pet Shop Management/public">
        AllowOverride All
        Require local
    </Directory>
</VirtualHost>
```

Enable Apache `mod_rewrite`, add `127.0.0.1 paw-polish.test` to your Windows hosts file if Laragon does not create it, and reload Apache. Set `APP_URL=http://paw-polish.test`, then run `php artisan optimize:clear`. `Require local` keeps this example local. For an HTTPS deployment use a valid certificate and follow SECURITY.md. Never serve `.env`, storage files, or vendor source directly. No storage symlink is needed; uploaded logos are served through authenticated routes.

## Normal updates

Back up first. Install dependencies from `composer.lock`, run `php artisan migrate`, then `php artisan optimize:clear`. Optionally run `php artisan optimize` after confirming correct production configuration. Preserve `.env`, APP_KEY, database records, and `storage/app/logos`. Do not run `migrate:fresh` against real business data.

## Troubleshooting

| Symptom | Resolution |
| --- | --- |
| Connection refused / SQLSTATE 2002 | Start MySQL in Laragon; check port/host and active MySQL version |
| Unknown database | Create the exact DB_DATABASE value in MySQL |
| Access denied | Check username/password and database grants; clear configuration cache |
| Could not find driver | Enable pdo_mysql in the PHP used by the terminal and the web server |
| No application encryption key | On initial setup only, run `php artisan key:generate` |
| 419 session expired | Sign in again; ensure cookies are allowed; do not set secure cookies on plain HTTP |
| Assets missing | Serve the public directory at the host root; this app is not configured for a subfolder URL |
| Date/time unexpected | Set APP_TIMEZONE, run `php artisan optimize:clear`, restart PHP; keep one timezone throughout the business |
| Cannot book | Check active pet/owner/service/groomer, future date, opening hours/days, conflicts, and service duration |
| Cannot edit old appointment | Only booked appointments can be rescheduled; other statuses preserve their workflow history |
| Logo rejected | Use PNG/JPEG/WebP under 2 MB and 2400 px per dimension; enable GD/fileinfo |
| PHP can't be found | Use Laragon Terminal or add the chosen PHP folder to PATH |
| Port 8000 is busy | Run `scripts/start.ps1 -Port 8001`; open that port and update APP_URL if needed |
| Generic error page | Check storage/logs/laravel.log. Keep APP_DEBUG=false for normal use. Share a redacted error with the maintainer |

The local demo was installed against `paw_polish`; tests use `paw_polish_test` or in-memory SQLite. Start real operations in a newly named database and create a new admin there. Preserve the demo database if you want a training environment.

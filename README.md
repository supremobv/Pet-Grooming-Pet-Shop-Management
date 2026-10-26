# Paw & Polish — Pet Grooming & Pet Shop Management

A working, customizable single-business MVP built with Laravel 12, PHP, Blade, plain CSS/JavaScript, and traditional MySQL. Designed for Laragon on Windows. No Node build, cloud account, or internet connection is needed to use the installed application.

## Start here

This workspace is already configured with the local `paw_polish` MySQL database and sample records. Start MySQL in Laragon, then run:

```powershell
.\scripts\start.ps1
```

Open **http://127.0.0.1:8000**. The generated local administrator credentials are in **`storage/app/local-access.txt`**. That file is ignored by Git. Change the password from **My account**. A sample brush sale was created during browser verification; all seeded contacts and transactions are demonstration data.

For a new installation, follow [Setup & Laragon configuration](docs/SETUP.md). Use a fresh database for real business records; do not mix demo data with live records.

## Features

| Area | Implemented behavior |
| --- | --- |
| Dashboard | Today's appointments, paid sales, customer/pet totals, low stock, seven-day chart, recent receipts |
| Customers | Create, edit, search, archive/restore, owner profile, pets, visit history, permitted purchase history |
| Pets | Owner link, species, breed, birth date, weight, vaccination due, medical/allergy and handling notes, visit history |
| Grooming | Service catalog, duration/price, groomer assignment, opening-day/hour checks, groomer/pet conflict prevention, rescheduling, lifecycle, handover notes |
| Point of sale | Product search/SKU lookup, quantities, appointment billing, discounts, tax, cash/change, external card/wallet references, duplicate-submission protection |
| Receipts | Print layout, historical business/pricing/logo snapshot, payment details, full refund with stock return |
| Inventory | SKU uniqueness, supplier/category/cost/price, low-stock thresholds, receipts/adjustments, stock movement history, transactional stock deduction |
| Administration | Six server-enforced roles, account enable/disable, password changes/admin reset, activity log |
| Business configuration | Name, logo upload, contact details, address, tagline, currency, tax, hours, days, receipt footer |
| Reporting | Date range, paid revenue, discounts/tax, refunded-sale total, top items, payment breakdown, CSV export |

## Documentation

- [Installation, configuration & troubleshooting](docs/SETUP.md)
- [User manual](docs/USER_MANUAL.md)
- [HTTP endpoint reference](docs/HTTP_API.md)
- [Architecture, database & extension guide](docs/ARCHITECTURE.md)
- [Security, deployment & backup operations](docs/SECURITY.md)
- [Verification record & acceptance checklist](docs/TESTING.md)

## Verification

```powershell
php artisan test
# Dedicated MySQL test database must already exist; its name must end in _test.
.\scripts\test-mysql.ps1
composer audit
```

The automated suite uses in-memory SQLite by default and has also been run against Laragon MySQL in `paw_polish_test`. It covers authorization, CSRF, login throttling, inactive users, booking conflicts, workflow transitions, price tampering, stock rollback, idempotency, tax, refunds, safe uploads, escaping, and reports. See the verification record for exact results and limitations.

## MVP boundaries

This is a single-location staff application. It records payments made through cash/external terminals; it does not process card data. It includes full refunds with all products returned to stock, not partial refunds or damaged-return workflows. There is no public booking portal, SMS/email delivery, staff shift/leave calendar, payroll, purchasing approval workflow, multi-branch support, accounting ledger, MFA, or token-based third-party API. Vaccination dates are reminders for staff review, not veterinary verification or automatic booking blocks. Opening hours are one continuous interval per day. Bookings have one service; create service packages for combined treatments.

No application can be guaranteed immune to every attack. Implemented controls and remaining production requirements are described in [Security](docs/SECURITY.md). The existing local PHP is 8.2.28; use a currently patched supported PHP runtime before production or network exposure. Laravel's official [deployment guide](https://laravel.com/docs/12.x/deployment) and PHP's [security release notice](https://www.php.net/releases/8_2_35.php) are linked for operations reference.

The application source uses the MIT license inherited from the Laravel project skeleton. The sample SVG logo and CSS illustration are original project assets and can be replaced.

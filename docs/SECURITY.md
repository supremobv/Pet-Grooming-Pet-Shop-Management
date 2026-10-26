# Security & operations

## Implemented protections

| Concern | Control |
| --- | --- |
| Authentication | Laravel password hashing, private admin provisioning, no public registration, session rotation on login/logout/password change |
| Brute force | Email/IP failed-login limiter and route-level throttles |
| Authorization | Server-side permission middleware, allowlisted roles/modules, ownership check for groomer actions, active-account enforcement |
| CSRF | Framework tokens on every mutating form; same-site cookies |
| SQL injection | Bound query builder parameters; allowlisted table names/fields; no raw SQL from request text |
| Stored/reflected XSS | Escaped Blade output and textContent for JS cart display; strict self-only CSP; no inline event handlers/scripts |
| Clickjacking | DENY frame headers and CSP frame-ancestors none |
| Upload attacks | Auth/admin-only logo upload, MIME/size/dimension validation, GD decode/re-encode, randomized PNG filename, private storage; no user SVG/PHP uploads |
| Sensitive caching | No-store/private responses for authenticated web pages; no business records in browser localStorage |
| Session theft mitigation | HttpOnly + SameSite cookie, encrypted DB session payload, configurable Secure flag, 60-minute idle lifetime |
| Financial tampering | Server prices/totals/tax, appointment price snapshot, stock row locks, atomic writes, unique checkout key and appointment billing constraint |
| Refund abuse | Manager/admin-only, reason required, full-sale lock/status checks, stock movement history, one refund per sale |
| Privilege mistakes | Cannot disable/demote own admin; maintain at least one active admin; validated account fields |
| Error disclosure | APP_DEBUG=false by default, custom common error pages, safe unique-conflict message |
| Auditability | Actor/entity/action/IP/timestamp with transaction-integrated writes; no password logging |

These controls reduce common risks; they do not prove immunity to every attack. Infrastructure patching, operational controls, independent penetration testing, and periodic dependency review remain necessary.

## Runtime finding on this machine

Local development used PHP **8.2.28**, which predates the published PHP **8.2.35 security release**. Upgrade Laragon's PHP before production or network exposure; do not assume application middleware can protect a vulnerable interpreter. Check the [official release announcement](https://www.php.net/releases/8_2_35.php) and [downloads](https://www.php.net/downloads.php) for the newest supported patched runtime. Verify both CLI and Apache/FastCGI PHP, since they may use different executables.

The project does not automatically modify the user's Laragon installation. Its dev server binds only to 127.0.0.1. The existing MySQL root/empty-password connection is a local development setting, not deployment guidance.

## Before production

1. Use patched supported PHP, MySQL, web server and OS; install locked PHP dependencies with `composer install --no-dev --optimize-autoloader`. Run `composer audit` periodically and review updates. Laravel guidance: [deployment](https://laravel.com/docs/12.x/deployment).
2. Serve only `public/` through Apache/Nginx, never `php artisan serve` as production hosting. Require valid HTTPS; configure trusted proxies only for actual trusted proxy IPs if using one. Do not indiscriminately trust forwarded headers.
3. Set APP_ENV=production, APP_DEBUG=false, correct APP_URL, SESSION_SECURE_COOKIE=true, SESSION_ENCRYPT=true, SESSION_HTTP_ONLY=true, SESSION_SAME_SITE=lax, SESSION_DRIVER=database. Set an appropriate short session lifetime and one business timezone.
4. Provision a least-privilege database user scoped to this database. Keep MySQL on loopback/private network. Use a deployment account for migrations rather than giving the runtime global database privileges. Never deploy with root/empty password.
5. Generate APP_KEY once on initial installation. Preserve it securely; rotation can invalidate encrypted sessions and other framework ciphertext. Keep `.env`, local-access.txt, logs, backups and private logos outside the served document root and outside public repositories.
6. Create named staff accounts with unique passwords and minimum roles. Remove demo/training credentials/data from the live database by starting from a fresh business database. Delete the generated credential note after securely handing credentials to the owner. For internet-facing use, add and test MFA/SSO as a subsequent hardening feature.
7. Allow writes only to storage/bootstrap cache as needed. Block directory listing and unwanted executable uploads at the server. Set upload/post limits appropriate to the 2 MB logo rule. PHP GD/fileinfo must be enabled.
8. Back up and restore-test the database and private files. Establish retention, restricted access, monitoring and incident response with the business owner. Avoid keeping unnecessary medical/contact details.
9. Run the acceptance tests in a separate test database, verify role behavior and TLS/session headers, review tax/receipt rules for the actual jurisdiction, then perform independent security testing before accepting real customer data over a network.

No jurisdiction or formal regulatory requirements were supplied. This MVP does not claim tax-invoice, privacy-law, veterinary, payment-processor or accessibility certification. Card/wallet references are plain transaction identifiers; never store PAN, CVV, passwords, or tokens there.

## Backup and recovery

Back up MySQL with HeidiSQL/Workbench export or mysqldump. Also copy `storage/app/logos`, the protected `.env`/APP_KEY, and application source/lockfile. Historical receipts reference retained logo files, so database-only backups are incomplete. Backups contain personal data and must be encrypted and access-controlled. Keep copies on a separate protected device/location and test a restore regularly.

An example using MySQL's login-path credentials avoids putting database passwords in shell history (tools must be on PATH):

```powershell
mysql_config_editor set --login-path=paw-backup --host=127.0.0.1 --user=backup_user --password
mysqldump --login-path=paw-backup --single-transaction --no-tablespaces --result-file=C:/secure-backups/paw-polish.sql paw_polish
```

Choose a new backup filename per run; the example output file will be overwritten if reused. The operator must provision the backup user's grants and protect the destination. Pause business writes during a coordinated files/database snapshot if branding is being changed. Automated backup scheduling is not configured by this project.

Recovery: restore into a **new empty recovery database**, import the SQL with your MySQL tool, restore the private logos, point a separate `.env` at the recovery database with the preserved APP_KEY, run `php artisan optimize:clear`, then verify login, counts, stock, receipts, and historical logos before switching users. Do not test restoration on the live database.

## Maintenance and incident handling

- Check storage/logs/laravel.log for unexpected failures, review Activity log for staff/refund/stock changes, and check dependency audits. Logs may include operational details; restrict access and retention.
- Disable compromised accounts, reset their passwords, and review their activity. Admin password reset clears that user's database sessions; disabled accounts are rejected on their next request.
- To recover the sole administrator, use the trusted server terminal: `php artisan app:admin owner@business.com`. This is a privileged recovery mechanism; OS shell access is equivalent to administrative application access.
- To correct stock, record an opposite adjustment with a reason instead of editing the database. To correct a paid sale use the documented full refund workflow; do not delete ledger rows.
- Apply migrations with backups and a tested rollback/recovery plan. Never run destructive test/reset commands against the business database.

Audit logs are not externally signed or immutable. The MVP has no security-monitoring service, WAF, malware scanner, hardware key support, rate-limit distributed cache deployment, or independent penetration-test report. Do not label it “secured from all attacks.”

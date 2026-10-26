# Verification & acceptance record

Verified locally on **October 26, 2026**, using the installed PHP 8.2.28 CLI, Laravel 12.70.1, the running Laragon MySQL instance, and in-memory SQLite for portable tests. See SECURITY.md for the required PHP upgrade before deployment.

## Executed checks

| Check | Result |
| --- | --- |
| `php artisan migrate --force` against dedicated `paw_polish` local database | Passed; all application tables created |
| Local admin generation + explicit DemoSeeder | Passed; no default password committed |
| `php artisan test` | 28 passed, 175 assertions, SQLite |
| `scripts/test-mysql.ps1` against `paw_polish_test` | 28 passed, 175 assertions, MySQL |
| `composer validate --strict` | Passed |
| `composer audit` / locked dependency audit | No known security advisories reported at verification time |
| `php artisan view:cache` | Passed |
| `node --check public/assets/app.js` | Passed |
| PHP source formatting with Pint | Completed |
| Browser: sign-in and dashboard | Passed with local administrator and MySQL records |
| Browser: POS → product quantity → customer → cash → receipt | Passed; sample 250.00 sale, 300.00 tendered, 50.00 change |
| Browser: operational report for the sample sale | Passed; one paid receipt and 250.00 revenue |
| Responsive preview at 390×844 | Reports layout verified; mobile navigation checked |

The local `paw_polish` database includes one sample sale created by browser QA. All demonstration records are fictional. The test suite uses a separate database; test transactions do not touch the demo/business database. MySQL tests require a name ending in `_test` before migration/reset is allowed. Tests can delete/reset tables in that dedicated test database.

## Automated scenarios

- Guest redirected to login; active administrator pages render, including profile/history screens.
- Customer creation/edit/search/archive/restore without physical record deletion.
- Server rejection of forbidden staff routes and mutation attempts.
- Inactive user cannot sign in or keep an active session.
- Successful/failed sign-in and rate limiting.
- Frame, content-type, cache and session security behavior.
- Missing CSRF token rejected with 419 on a request outside testing bypass mode.
- Groomer/pet booking conflicts, past dates and out-of-hours appointments.
- Same pet cannot be double-booked through another groomer.
- Lifecycle transition rules and groomer assignment authorization.
- Server prices cannot be replaced by submitted prices; duplicate checkout key reuses receipt.
- Insufficient stock and underpayment leave no partially committed sale.
- Duplicate product lines are aggregated before stock checks.
- Whole-order discount/tax calculations and required non-cash reference.
- Grooming owner assignment and duplicate appointment billing prevention.
- Full refund restores stock exactly once.
- Negative stock adjustment rejected; valid delivery records audit and movement.
- Business configuration saved; executable upload rejected.
- Valid JPEG logo re-encoded to random PNG; old receipt business name/logo preserved after rebranding.
- Administrator cannot demote their own account.
- Staff provisioning/reset hashes passwords and does not render hashes.
- Own password change requires the current password.
- Stored script markup is escaped; missing fields produce validation messages.
- Report rendering and downloadable CSV; refund handling.
- Large submitted catalog with zero-quantity lines can still check out selected items.
- Business currency cannot change after first sale.
- Cashier dashboard does not expose appointment/pet/customer care records.

## Repeat verification

Default portable suite:

```powershell
php artisan test
```

MySQL: create an empty dedicated test database (for example `paw_polish_test`) with credentials matching `.env`, then:

```powershell
.\scripts\test-mysql.ps1 -Database paw_polish_test
```

Do not point tests at business records. Never rename a live database to end in `_test` just to satisfy the guard. The test suite intentionally resets tables and uses fake records.

## Business acceptance checklist

1. Fresh database: install, create owner, verify no default users, change branding and opening hours.
2. Create receptionist, groomer, cashier, inventory and manager accounts. Test each account's permitted and forbidden workflows in separate sessions.
3. Add customer/pet with medical and handling notes; view the profile/history.
4. Book a visit, attempt overlapping/out-of-hours slots, reschedule, cancel and no-show.
5. Check in, start grooming as assigned groomer, add notes, mark ready, bill, release.
6. Receive product stock, sell products with service and separately, verify stock, tendered amount, tax, discount and change.
7. Refresh/retry the same checkout response and confirm there is only one receipt.
8. Print on the intended printer and inspect line wrapping/margins; use Save as PDF if needed.
9. Record a full refund only after confirming goods can return to stock, reverse the external payment, and verify stock/report/history.
10. Export CSV, verify date range and tax assumptions with the business owner, then restore a backup into a separate recovery database.

## Verification limits

This is functional integration testing and a browser sanity check, not a penetration test, regulatory certification, load test, printer hardware certification, or proof against every concurrency pattern. Real concurrent-process stress testing has not been performed; transaction/lock behavior and database uniqueness constraints are implemented and sequential integrity cases are tested on MySQL. Accessibility has keyboard/focus/responsive provisions but has not had a formal WCAG audit. SMTP/SMS, online payment gateways, public booking, staff shift/leave scheduling, and MFA are not implemented or tested.

The browser-tested sample contains a small catalog; very large catalogs should use a paginated POS search extension. PHP input limits apply; JavaScript submits selected product lines only. Report totals are operational snapshots of current receipt status, not immutable accounting-period statements.

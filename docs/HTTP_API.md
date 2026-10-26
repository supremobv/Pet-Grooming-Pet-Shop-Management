# HTTP endpoint reference

This MVP is a server-rendered, session-authenticated Laravel application. The routes below are its internal HTTP interface, not a public REST/token API. GET pages return HTML; successful mutations normally redirect with a flash message. There are no API keys, bearer tokens, CORS allowances, public registration endpoints, or public data endpoints.

## Authentication and request conventions

- GET `/login` sets up a session and includes a hidden `_token`. POST credentials plus that CSRF token to `/login`, retaining cookies. Successful login regenerates the session identifier.
- Every mutation requires the authenticated session and a current CSRF `_token`, or the `X-CSRF-TOKEN` header. HTML forms use `@csrf`.
- PUT can be sent as HTTP PUT or POST with `_method=PUT`.
- To request Laravel JSON validation errors, send `Accept: application/json`. Validation failures return 422 with `{message, errors}`; unauthenticated JSON requests return 401. Permission failures are 403, missing records 404, expired/missing CSRF 419, rate limits 429, conflicting unique records 409 for JSON (HTML redirects with a friendly error).
- Success redirects remain redirects even with Accept JSON. Integrations needing stable JSON resources should add a versioned API with explicit token scopes and separate tests; do not rely on scraping rendered pages.
- Monetary inputs are major units (e.g. `250.00`), stored as integer cents. All submitted prices/totals are ignored: product prices, service booking prices, tax and stock are computed server-side.
- All business times use APP_TIMEZONE. IDs are positive database IDs. User-supplied HTML is escaped in views.

## Routes

| Method | Path | Permission | Input / purpose |
| --- | --- | --- | --- |
| GET | `/login` | Guest | Sign-in form |
| POST | `/login` | Guest + throttle | email, password |
| POST | `/logout` | Authenticated | End current session |
| GET | `/` | dashboard | Overview scoped by role |
| GET | `/account` | Authenticated | Account/password form |
| POST | `/account/password` | Authenticated + throttle | current_password, password, password_confirmation |
| GET | `/business-logo` | Authenticated | Current safe branding image |
| GET | `/customers`, `/pets`, `/products`, `/services`, `/suppliers` | Matching module | q (name search), archived=1 (include archived), page |
| GET | `/{module}/create` | Matching module | Creation form for one of the five modules above |
| POST | `/{module}` | Matching module | Validated catalog fields below |
| GET | `/{module}/{id}/edit` | Matching module | Edit form |
| PUT | `/{module}/{id}` | Matching module | Validated catalog fields below |
| POST | `/{module}/{id}/archive` | Matching module | Toggle active/archived; no physical deletion |
| GET | `/customers/{id}` | customers | Customer profile and permitted history |
| GET | `/pets/{id}` | pets | Pet care record and visit history |
| GET | `/appointments` | appointments or grooming | date (Y-m-d), status, page; groomers see assigned visits only |
| GET | `/appointments/create` | appointments | Booking form |
| POST | `/appointments` | appointments | pet_id, service_id, groomer_id, starts_at, notes |
| GET | `/appointments/{id}/edit` | appointments | Rescheduling form |
| PUT | `/appointments/{id}` | appointments | Same booking fields; only booked records accepted |
| POST | `/appointments/{id}/status` | appointments or assigned grooming | status, grooming_notes; legal transitions only |
| GET | `/checkout` | checkout | New sale form; optional appointment query selects visit |
| POST | `/checkout` | checkout + throttle | See checkout contract |
| GET | `/sales` | sales | q (exact receipt ID), page |
| GET | `/sales/{id}` | sales | Receipt |
| GET | `/sales/{id}/logo` | sales | Historical receipt logo from business snapshot |
| POST | `/sales/{id}/refund` | refunds | refund_reason (5–180 chars), full return only |
| GET | `/stock` | stock | Stock and paginated movement history |
| POST | `/stock` | stock | product_id, quantity (signed nonzero integer), reason (5–180 chars) |
| GET | `/reports` | reports | from/to (Y-m-d), export=csv |
| GET | `/settings` | settings | Branding/business form |
| POST | `/settings` | settings | See settings fields below; multipart for logo |
| GET | `/users` | users | Staff administration |
| POST | `/users` | users | name, email, role, active (0/1), password, password_confirmation |
| PUT | `/users/{id}` | users | Same, password optional; blank means preserve password |
| GET | `/audit` | audit | Paginated activity log |
| GET | `/up` | Public | Framework health response; contains no business records |

The literal module names are allowlisted in routes, not used as arbitrary user-controlled table names.

## Catalog inputs

| Module | Required | Optional |
| --- | --- | --- |
| customers | name, phone | email, address, notes |
| pets | name, customer_id, species | breed, birth_date, weight, vaccination_due, medical_notes, behavior_notes |
| services | name, duration, price | description |
| products | name, sku, category, price, cost, reorder_level | supplier_id |
| suppliers | name | email, phone, address |

Species: Dog, Cat, Rabbit, Bird, Other. Duration is 5–480 minutes. Prices/costs are 0–1,000,000 major units. Product SKU is unique including archived records. Stock cannot be set through catalog fields; use stock movements. Form metadata and precise field rules are in `app/Support/Catalog.php`.

## Booking input example

```json
{
  "pet_id": 1,
  "service_id": 2,
  "groomer_id": 3,
  "starts_at": "2026-11-02T10:00",
  "notes": "Use sensitive-skin shampoo."
}
```

Date format is exactly `Y-m-d\TH:i`. The application derives ends_at and snapshots price_cents from the service. Eligible groomer roles are groomer, manager, admin, with active=true. An active owner and pet are required. Overlap intervals are half-open: a new booking can begin when another ends. Cancelling/no-show releases its reserved interval. No staff leave/shift logic is implemented.

Status values: booked, checked_in, in_progress, ready, completed, cancelled, no_show. The status endpoint cannot set booked or arbitrary transitions. Groomers can only set in_progress or ready on their own assignment.

## Checkout contract

```json
{
  "checkout_key": "ecf17c71-851d-433a-bc90-d36cb1834b3e",
  "customer_id": 1,
  "appointment_id": null,
  "items": [{"product_id": 4, "quantity": 2}],
  "discount": "20.00",
  "tendered": "400.00",
  "payment_method": "cash",
  "payment_reference": null
}
```

Use a new UUID for each intended sale. Retrying the same key from its original cashier returns the same receipt. Duplicate keys are unique at database level; concurrent duplicate writes cannot create a second sale. A racing request that reaches the database uniqueness constraint receives a friendly conflict response and should check Sales before retrying. Another user cannot retrieve a receipt through replaying a checkout key.

At most 100 selected product lines; duplicate product IDs are combined before stock checks. Zero-quantity form lines are discarded before validation. Positive quantities must be integers ≤10,000. At least one product or billable appointment is required. Appointments must be ready/completed and never previously billed. Supplying customer_id with an appointment must match its owner.

Cash received must cover total. Card/e_wallet must exactly match total and require a reference; these are records of externally processed payments. Discount cannot exceed subtotal. Totals are rounded to cents; tax is applied once to the discounted order. All stock/sale/item/movement/audit writes share one database transaction.

## Settings fields

name, tagline, email, phone, address, currency, tax_rate, open_time (`H:i`), close_time (`H:i`), open_days (ISO weekday string array 1=Mon to 7=Sun), receipt_footer. currency is PHP/USD/SGD/EUR/GBP/AUD/CAD/MYR and cannot change after a sale. tax_rate accepts up to 2 decimal places from 0 to 100. At least one opening day and close_time > open_time are required.

Optional logo: PNG/JPEG/WebP ≤2 MB and max 2400×2400 px, decoded and re-encoded to PNG under a random filename. SVG uploads and executable types are rejected. remove_logo=1 restores the sample. Old safe logo images are retained for historic receipts.

## Rates and session behavior

- Login: 5 failed attempts per normalized email/IP in 60 seconds, plus route-level 20 requests/minute.
- Checkout: 30 requests/minute per authenticated identity.
- Password changes: 6 requests/minute.
- Default idle session lifetime: 60 minutes; encrypted server-side database session payload and HttpOnly/SameSite cookies. Secure flag is false for localhost HTTP and must be true for production HTTPS.

Exact permission definitions are in `app/Support/Access.php`; routes are the authoritative endpoint inventory (`php artisan route:list`).

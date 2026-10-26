# User manual

## First-time administrator checklist

1. Sign in using your administrator account. Click your name at the bottom of the sidebar and update your password.
2. Open **Business settings**. Set business name, contacts, address, logo, currency, tax, open hours/days, and receipt footer. Use a PNG/JPEG/WebP logo. Restore the sample logo with the checkbox. Currency becomes fixed after the first sale.
3. Open **Team & access**. Create individual accounts with the smallest suitable role. Share temporary passwords privately and have staff change them from **My account**. Disabling an account blocks it on its next request. Do not share administrator credentials.
4. Create your **Grooming services** with price and duration. Packages such as “Bath + trim + nails” should be one service with the total duration and price.
5. Create **Suppliers**, then **Products**. Enter SKU, category, supplier, cost, selling price, and reorder threshold. Stock starts at zero.
6. Under **Stock movements**, receive opening stock with a positive quantity and a clear reason. Example: `20`, “Opening stock count”.
7. Add **Customers** before adding their **Pet profiles**. Fill medical/allergy and handling notes before a visit.

Business settings govern new bookings and receipts. Changing hours does not rewrite existing appointments. New bookings/reschedules must fit the current hours. Existing booked prices remain until rescheduling; rescheduling applies the current catalog price/duration.

## Roles

| Role | Main responsibilities |
| --- | --- |
| Admin | All features, branding, staff management and access |
| Manager | Customers, pets, grooming, catalog/inventory, checkout/sales/refunds, reports, audit; no branding or account administration |
| Receptionist | Customer/pet records, appointment booking/check-in/status management |
| Groomer | Only assigned appointments, their care notes, start grooming and mark ready |
| Cashier | Checkout, sales and receipts; no refund privilege or customer/pet editing |
| Inventory | Products, suppliers, stock adjustments/history |

Permission checks run on the server. A hidden menu item cannot be bypassed by typing its URL. All staff can change their own password. Fixed role definitions are in `app/Support/Access.php`; there is no custom-role builder in this MVP.

## Daily grooming workflow

1. **Appointments → New appointment**. Select a pet, service, groomer, future date/time, and optional visit notes. The list includes the owner's name to distinguish pets with the same name.
2. The app checks service duration, active records, opening days/hours, and overlap for the groomer or pet. If a slot is rejected, choose another time or groomer. End-to-start bookings are allowed.
3. At arrival, open the appointment and review **Care notes & visit details**, including medical/allergies, handling, and vaccination due. Vaccination dates do not automatically prevent a visit; follow your business's care policies.
4. Receptionist/manager/admin selects **Check in**.
5. The assigned groomer selects **Start grooming**, adds treatment or handover notes, then **Mark ready for pickup**.
6. Receptionist/manager/admin selects **Complete & release** at pickup. Payment and physical release are separate: a completed visit can still need checkout. Always confirm payment in Sales/POS before releasing under your own policy.

Allowed lifecycle:

```text
booked → checked_in → in_progress → ready → completed
booked → cancelled / no_show
checked_in → cancelled
```

Only **booked** visits can be rescheduled. Cancelled, no-show, and completed records remain in history. To arrange a replacement, create a new appointment. A groomer cannot change another groomer's visit, cancel bookings, check customers in, or release pets.

The default schedule shows today and future visits. Choose a date to see historical appointments; filter by status to focus the list. Customer/pet profile pages include their full visit history.

## Customers and pets

Search by name. Click a customer/pet name for its profile and visit history; select **Edit** to change details. A pet has exactly one owner. **Archive** removes the record from active lists without deleting history. Use **Include archived** to find and restore it.

Archiving an owner/pet stops new bookings but does not cancel already scheduled visits. Review those visits when archiving. Recorded contact names can change as profiles are corrected. Protect medical and contact notes and record only what the business needs.

## Products and deliveries

Each SKU must be unique, even on archived products. Use readable categories such as Food, Grooming, Toys, Accessories, and Cat essentials. Prices/costs are per unit; stock is whole units.

For a delivery, open **Stock movements**, select the product, enter a positive quantity and a supplier invoice reference in the reason. For damage/loss, enter a negative quantity and explain why. The app prevents stock going below zero. To correct an adjustment, make an opposite adjustment with a reference to the original; history is never overwritten.

Low-stock alerts appear when stock is at or below its threshold. Supplier records are a contact directory; purchase orders and accounts payable are outside this MVP.

## Point of sale

1. Open **Point of sale**. Search by product name, category, or exact/scanned SKU. Scanners acting as keyboards can enter SKU text in search; choose quantity afterward.
2. Enter product quantities. The current sale shows chosen lines and totals immediately.
3. Select a customer or leave it as walk-in. For a grooming payment, choose a **ready or completed, unbilled** appointment. The pet owner is used automatically; a conflicting customer selection is rejected.
4. Enter any whole-sale discount. Tax is added after the discount: `round((subtotal − discount) × tax rate)`. Discounts cannot exceed the subtotal. Staff with checkout permission can apply discounts; use the activity/receipt history for oversight.
5. Choose **Cash**, **Card**, or **E-wallet**. Card/wallet payments happen outside this system. Confirm the actual payment, then enter its reference. Do not enter card numbers, CVV, PIN, or wallet passwords here.
6. Enter **Amount received**. Cash can exceed the total; change appears automatically. Card/wallet must match the total exactly.
7. Select **Complete sale**. Prices and available stock are checked again. If stock changed while you were working, refresh and adjust the order. Duplicate submission of the same checkout key returns the original receipt.
8. Review and **Print receipt**. Browser print supports Save as PDF and a narrow receipt layout. Printer margins and paper width may require local adjustment.

Sales and stock updates commit together. No payment is recorded if a business validation fails. Grooming appointments can only be billed once, including after a full refund. Create a new appointment for a replacement service rather than rebilling the refunded visit.

## Refunds

Admin/manager: open the receipt, confirm goods are eligible to return to sellable stock, enter a reason, and select **Record full refund**. This refunds the entire recorded sale and returns all its product quantities to stock once. The app does **not** transfer money: return cash or reverse the terminal/wallet payment separately. Do not use this flow for partial returns, damaged goods, or service-only partial credits; those require an extension. Full refunds cannot be undone in the app.

## Reports and activity

**Reports** uses an inclusive date range based on the original sale date and the business timezone. Paid totals exclude fully refunded receipts. “Refunded sales” means sales originally made in the range that are now refunded, rather than refunds processed within the range. This means a later refund updates earlier-period operational totals.

Top-item revenue is before order-level discount/tax. Export CSV for spreadsheet analysis. These are operational reports, not accounting statements or tax filings. Receipts are not certified tax-authority invoices.

**Activity log** records sign-in/out and business changes, including staff, settings, stock, sales, refunds, and exports. It does not record passwords or full customer/medical note content. Administrators with direct database access can alter underlying data, so external backups and database access controls remain necessary.

## Errors and recovery

- A validation banner explains rejected fields. Correct the inputs and retry. Password fields are never repopulated.
- “Session expired”: sign in and retry. Before resubmitting a payment, check recent receipts.
- “Access restricted”: ask the administrator to review your role.
- A generic failure: note the time/action for the administrator. Check sales and stock history before repeating checkout.
- Lost password: ask an administrator to reset it under **Team & access**. If the only admin is locked out, use `php artisan app:admin` from the trusted server terminal.

Sign out when leaving a shared workstation. Sessions expire after 60 idle minutes by default. Back up the business database, logos, and protected configuration regularly.

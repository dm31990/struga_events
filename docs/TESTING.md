# Testing — Struga Events (Section 7)

## 7.1 Overview

Four levels: functional, UI, database, security. Each user story maps to at least one test case.

## 7.2 Functional testing

| ID | Feature | Test description | Expected result | Status |
|----|---------|------------------|-----------------|--------|
| T01 | User registration | Valid registration form | Account created, can sign in | Pass |
| T02 | User login | Correct credentials | Session created, redirect by role | Pass |
| T03 | User login | Wrong password | Error message, no session | Pass |
| T04 | Browse catalog | Open home / catalog | Items with name, price, category | Pass |
| T05 | Filter by category | Select category | Filtered results | Pass |
| T06 | Date availability | Select date range + check | Available qty shown | Pass |
| T07 | Submit reservation | Cart + submit | `pending` reservation + line items | Pass |
| T08 | Conflict detection | Overbooked dates | Request rejected | Pass |
| T09 | Missing fields | Empty dates | Validation error | Pass |
| T10 | View reservations | Customer dashboard | List with status | Pass |
| T11 | Cancel reservation | Cancel pending | `cancelled`, stock released | Pass |
| T12 | Approve reservation | Employee approves | `approved`, invoice, notification | Pass |
| T13 | Reject reservation | Employee rejects | `cancelled`, customer notified | Pass |
| T14 | Generate invoice | On approval | Invoice + line items, correct totals | Pass |
| T15 | Download invoice | Open `invoice.html` + print | Printable billing details | Partial |
| T16 | Update inventory | Change quantity/price | Saved, visible in catalog | Pass |
| T17 | Upload item photo | Valid JPEG/PNG | Saved under `uploads/` | Pass |
| T18 | Upload invalid file | Non-image | Rejected with error | Pass |
| T19 | Admin calendar | FullCalendar view | Events on correct dates | Pass |
| T20 | Generate report | Admin report date range | Totals returned | Pass |

## 7.3 UI testing

- Catalog grid: 2–3 columns desktop, 1 column mobile (`styles.css` responsive rules).
- Date inputs: native `type="date"` on mobile and desktop.
- Cart: quantity and total update in `app.js`.
- Calendar: month/week/day via FullCalendar (`admin.html`).
- Invoice print: `invoice.html` layout tested with browser print preview.

## 7.4 Database testing

- Reservation insert: `reservations` + `reservation_items` with locked `unit_price`.
- Approval: status `approved`, row in `invoices` and `invoice_line_items`.
- Cancel: status `cancelled`; availability query excludes cancelled.
- Registration: `password_hash` is bcrypt, not plain text.

## 7.5 Security testing

- SQL injection: PDO prepared statements on all endpoints.
- XSS: `escHtml()` in frontend; htmlspecialchars in PHP where needed.
- Auth: protected PHP endpoints return 401/403 without session.
- RBAC: `admin_users.php` and reports require `admin`; inventory allows `employee`.

## How to run manual tests

1. Import `sql/schema.sql`.
2. Use demo accounts from `README.md`.
3. Walk through T01–T20 in order; record results in your report appendix.

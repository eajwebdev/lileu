# Lil'Eu — dessert brand, reseller platform & POS

A Laravel 12 + React (Inertia) application built to the brand spec in
[`master-prompt.md`](master-prompt.md): one warm chocolate / cream / blush identity carried across the
public website, the reseller portal, PayMongo QR Ph payments, printed receipts, the cashier POS and
the admin back office.

---

## Running it

You already have XAMPP (PHP 8.2, MySQL) and Node 22. From the project root:

```bash
composer install
npm install

php artisan migrate:fresh --seed   # builds the `lileu` database with demo data
npm run build                      # or: npm run dev  (hot reload)
php artisan serve
```

Then open <http://127.0.0.1:8000>.

The `.env` is already pointed at the local MySQL instance (`lileu`, user `root`). If your MySQL
credentials differ, edit `DB_USERNAME` / `DB_PASSWORD`.

### Demo accounts

| Role     | Email                | Password   | Lands on          |
| -------- | -------------------- | ---------- | ----------------- |
| Owner    | `admin@lileu.test`   | `password` | `/admin`          |
| Cashier  | `cashier@lileu.test` | `password` | `/pos`            |
| Reseller | `juan@lileu.test`    | `password` | `/portal`         |
| Reseller | `maria@lileu.test`   | `password` | `/portal`         |
| Applicant| `ana@lileu.test`     | `password` | `/portal/status`  |

The seeded data deliberately includes all three money states: an order with a 50% downpayment paid
and a balance outstanding, an order settled in two separate payments, and a brand-new unpaid order.

The default business address shown across the site and receipts is **Dahile, Mabinay, Negros
Oriental**. It can still be changed later in Admin â†’ Brand & receipts.

---

## The brand system

The palette from the spec lives in one place — `tailwind.config.js` — and everything else reads from
it, including the receipts:

| Token                 | Hex       | Used for                                              |
| --------------------- | --------- | ----------------------------------------------------- |
| `chocolate` (700)     | `#3B2A22` | Navigation, headings, primary buttons, receipt header |
| `chocolate-800`       | `#2B1B16` | Dark total bar, hover states                          |
| `blush` (300)         | `#EFAFB8` | Secondary buttons, badges, selected states, accents   |
| `blush-200`           | `#F7CED3` | Soft section backgrounds                              |
| `cream` (200)         | `#F5E8D9` | Main surface                                          |
| `cream-100`           | `#FBF5EC` | Page background                                       |
| `vanilla`             | `#FFFDFC` | Cards, inputs, tables, POS cart                       |
| `cherry`              | `#C71827` | Sparing highlights and promo badges                   |
| `caramel`             | `#D7973E` | Food highlights, charts, top-seller markers           |
| `success`             | `#2F7D4A` | **Functional status only** — PAID / COMPLETED         |

Type: **Fraunces** for brand headings (`font-display`), **Plus Jakarta Sans** for all system UI.

### Logo

The master artwork is `public/logo.png` (1254×1254, transparent). Two web-sized derivatives are
generated from it and are what the app actually loads:

| File | Size | Used by |
| --- | --- | --- |
| `public/images/logo.png` | 560px, 470 KB | Landing hero |
| `public/images/logo-mark.png` | 160px, 50 KB | Nav, sidebar, footer, receipts, POS slip |
| `public/favicon.png` | 64px, 10 KB | Browser tab |

The mark is circular with a transparent ground, so `<Logo>` sits it on a cream disc — invisible on
cream pages, and what keeps the chocolate rim readable against the dark navigation and sidebar.

Print and PDF output embeds the logo as a base64 data URI (dompdf cannot fetch remote assets) via
`Settings::logoDataUri()`, which resolves whatever path is configured — so changing the logo in
Admin → Brand & receipts updates the receipts too. `receipt_show_logo` toggles between
*logo + business name* and *business name only*, per the spec.

**If you replace the artwork**, drop a new `public/logo.png` and regenerate the derivatives (any
image tool will do — 560px, 160px and 64px squares, transparency preserved). Note that palette
quantisation destroys the alpha channel on this illustration, so keep them true-colour.

---

## How the money works

This is the part the spec cares most about, so it is worth stating plainly.

**An order number and a receipt number are different things.**

- Orders: `RE-2026-000012` — resets yearly.
- Receipts: `LE-20260902-001301` — resets daily.
- One order can carry several receipts, because the 50% and the remaining 50% are paid separately.

The year is always read from the clock; nothing is hard-coded. Both prefixes are editable in
Admin → Brand & receipts, and numbers are drawn under a row lock (`number_sequences`) so two
concurrent checkouts can never collide.

**A payment *is* the receipt.** `payments` rows carry the receipt number, and
`OrderService::syncTotals()` derives `amount_paid`, `balance` and `payment_status` from the paid
payments on record. A receipt view, print or PDF only *renders* that state — regenerating one never
posts a second transaction. `PaymentService::markPaid()` is idempotent, so a redelivered PayMongo
webhook cannot double-count either.

**The stamp never lies about what is owed:**

| Order state                             | Stamp                     | Tone         |
| --------------------------------------- | ------------------------- | ------------ |
| Nothing paid                            | `PENDING PAYMENT`         | Amber        |
| Paid, but less than the downpayment     | `PARTIALLY PAID`          | Amber        |
| Downpayment met, balance outstanding    | `50% DOWNPAYMENT PAID`    | Muted green  |
| Balance cleared                         | `FULLY PAID`              | Green        |
| Cancelled / voided / refunded           | `CANCELLED` / `VOID` / `REFUNDED` | Muted red |

`tests/Feature/ResellerPaymentFlowTest.php` locks all of this down. Run `php artisan test`.

### Student and vendor consignments

Admin â†’ Consignments handles short selling runs where products are handed to a student or vendor
without payment up front. A batch may be collected once or several times (for example, each evening
or the next morning). At every collection, each unit can be recorded as:

- sold â€” the consignment price becomes payable;
- returned in good condition â€” stock goes back onto the shelf immediately;
- expired, damaged, or missing/other â€” stock is written off at cost, with notes for the scenario; or
- still out â€” the batch stays open for the next collection.

The final collection cannot close while units are unaccounted for. The settlement screen can mark
all remaining units as returned in one click, while stock checks prevent issuing more than is
actually available.

---

## Receipts

One payload (`App\Services\ReceiptService`) feeds three renderers so the browser receipt and the
downloaded PDF cannot drift apart:

- **Web** — `resources/js/Components/Lileu/Receipt.jsx`
- **Print + PDF** — `resources/views/receipts/reseller.blade.php` (table-based; dompdf has no flexbox)
- **Consolidated summary** — `resources/views/receipts/summary.blade.php`, every payment on one sheet
- **80mm thermal** — `resources/views/receipts/pos.blade.php`, for the counter

Composition, per the spec: chocolate header → cream body → order info → item table → dark chocolate
total bar → payment stamp → footer.

Routes:

```
/receipts/{receipt}             view
/receipts/{receipt}/print       print sheet  (?paper=thermal for 80mm)
/receipts/{receipt}/pdf         download
/orders/{order}/summary         consolidated payment summary
/orders/{order}/summary/pdf     …as PDF
```

Emoji are stripped from PDF output — dompdf's core fonts have no coverage for them, and a footer
reading `Thank you for growing with Lil'Eu. 💗` would otherwise render a tofu box.

---

## PayMongo QR Ph

With `PAYMONGO_SECRET_KEY` blank the app runs in **demo mode**: it mints a local QR payload so the
whole reseller → pay → receipt journey is clickable, and the payment page offers a "simulate
successful payment" button. That button is refused the moment live keys are configured.

To go live, set in `.env`:

```
PAYMONGO_SECRET_KEY=sk_live_…
PAYMONGO_PUBLIC_KEY=pk_live_…
PAYMONGO_WEBHOOK_SECRET=whsk_…
PAYMONGO_DEMO_MODE=false
```

Then register `POST /webhooks/paymongo` in the PayMongo dashboard for `payment.paid`. The webhook
URL is shown (with a copy button) in Admin → Brand & receipts. Signatures are HMAC-verified; if live
keys are set but no webhook secret is, every delivery is rejected rather than trusted.

Receipts show payment method, date, time, reference and amount. API keys, client secrets, QR payloads
and raw gateway metadata are hidden on the model and never reach a receipt or an Inertia payload —
there is a test for that.

---

## What's in the app

| Area | Route | Notes |
| --- | --- | --- |
| Public site | `/`, `/products`, `/become-a-reseller` | Landing, menu, application form |
| Reseller portal | `/portal` | Mobile-first, bottom tab bar; dashboard, catalog, ordering, orders, chat |
| Payment | `/pay/{receipt}` | Branded QR Ph page + success page |
| POS | `/pos` | Touch-sized cashier terminal, thermal slip, sales history |
| Admin | `/admin` | Dashboard, orders, payments, resellers, products, messages, ledger, reports, users, settings |

**Roles** — `admin`, `cashier`, `reseller`, gated by the `role:` middleware (admins inherit cashier
access). Pending and rejected applicants keep their login but are parked on `/portal/status` rather
than dropped into an empty portal. Resellers can only ever see their own orders and receipts.

**Reseller catalogs** — admin can curate exactly which products a reseller may order and at what
negotiated price (`product_reseller` pivot). A freshly approved partner with no curated list sees
everything flagged `available_to_resellers`, so they are never staring at an empty catalog.

**Admin KPIs** — Sales (chocolate), Purchases (caramel), Expenses (rose), Net profit (green),
Reseller commissions (blush): one restrained accent each, not a rainbow.

---

## Layout

```
app/
  Http/Controllers/{Site,Portal,Admin,Pos,Webhooks}/   role-scoped controllers
  Http/Middleware/EnsureUserHasRole.php                role:admin,cashier
  Http/Middleware/EnsureResellerIsApproved.php         parks pending applicants
  Services/
    NumberGenerator.php    gap-free RE- / LE- / POS- numbering
    OrderService.php       order placement + the money derivation
    PaymentService.php     opening, settling and voiding payments
    PayMongoService.php    QR Ph, with a demo fallback
    ReceiptService.php     the one receipt payload
  Support/Settings.php     cached, owner-editable branding
resources/
  css/app.css              brand layer: .btn-primary, .card, .table-lileu, …
  js/Components/Lileu/     ui.jsx, product.jsx, Receipt.jsx
  js/Layouts/              Site, Portal, Admin, Guest
  js/Pages/                one folder per area
  views/receipts/          reseller.blade.php, summary.blade.php, pos.blade.php
```

## Things worth knowing

- Settings are cached forever and flushed on write (`App\Support\Settings`). If you edit the
  `settings` table by hand, run `php artisan cache:clear`.
- The test suite runs against a separate `lileu_testing` MySQL database (see `phpunit.xml`). It is
  not sqlite because the app uses MySQL-only `FIELD()` ordering in a couple of admin listings.
- Product images are referenced by path (`/images/products/…`) rather than uploaded — drop files in
  `public/images/products/` and point the product at them. Products without an image get a
  dessert-toned placeholder plate instead of a grey box.

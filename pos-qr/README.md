# POS Suite — Vercel Deployment Guide

A PHP-based Point of Sale system with **automatic QR code generation** on every invoice.  
Customers scan the QR to open a public invoice page at `https://your-app.vercel.app/invoice/INV-YYYYMMDD-XXXXX`.

---

## What was added

| Feature | Details |
|---|---|
| **QR code on every invoice** | Generated via `api.qrserver.com` — no API key, no library |
| **Public invoice page** | `/invoice/{invoice_no}` — no login required |
| **DB-backed sessions** | Required for Vercel's stateless serverless functions |
| **Env-var config** | All secrets read from environment variables |
| **Vercel routing** | Clean URLs via `vercel.json` rewrites |

---

## Prerequisites

- [Vercel account](https://vercel.com) (free tier works)
- A cloud MySQL database — choose one:
  - **[PlanetScale](https://planetscale.com)** (serverless MySQL, free tier)
  - **[Railway](https://railway.app)** (MySQL, free trial)
  - **[Aiven](https://aiven.io)** (MySQL, free tier)
  - **[TiDB Cloud](https://tidbcloud.com)** (MySQL-compatible, free tier)

---

## Step 1 — Provision the database

1. Create a MySQL database on your chosen provider.
2. Copy the connection credentials (host, user, password, database name).
3. In your provider's SQL console, run **`database.sql`** to create all tables.  
   Then run **`migrate.sql`** and **`suppliers_migrate.sql`** for extra tables.

> **PlanetScale note:** PlanetScale disables foreign key constraints by default.  
> Remove the `FOREIGN KEY` lines from `database.sql` before importing, or enable  
> `@@foreign_key_checks` in your branch settings.

---

## Step 2 — Deploy to Vercel

### Option A — Vercel Dashboard (recommended for first deploy)

```bash
# 1. Push your code to GitHub
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/your-username/your-repo.git
git push -u origin main

# 2. Go to https://vercel.com/new → Import the repo
# 3. Leave Framework Preset as "Other"
# 4. Set environment variables (see Step 3)
# 5. Click Deploy
```

### Option B — Vercel CLI

```bash
npm i -g vercel
vercel login
vercel --prod
```

---

## Step 3 — Set environment variables

In the Vercel dashboard → **Project → Settings → Environment Variables**, add:

| Variable | Example value | Required |
|---|---|---|
| `DB_HOST` | `aws.connect.psdb.cloud` | ✅ |
| `DB_USER` | `your_db_user` | ✅ |
| `DB_PASS` | `your_db_password` | ✅ |
| `DB_NAME` | `pos_db` | ✅ |
| `APP_URL` | `https://your-app.vercel.app` | ✅ |
| `SHOP_NAME` | `AL Majeed Book Store` | optional |
| `CURRENCY` | `Rs-` | optional |
| `TAX_RATE` | `0.10` | optional |

> `APP_URL` is critical — it's embedded in every QR code.  
> Use your actual Vercel domain. After adding a custom domain, update this variable.

---

## Step 4 — Verify deployment

1. Visit `https://your-app.vercel.app/login.php`
2. Login with `admin` / `admin123`
3. Make a test sale in the POS
4. The invoice modal shows a QR code — scan it
5. It opens `https://your-app.vercel.app/invoice/INV-...` — no login needed

---

## How QR codes work

```
Customer scans QR
       ↓
https://your-app.vercel.app/invoice/INV-20240501-AB3F2
       ↓
vercel.json rewrites to:  /invoice.php?no=INV-20240501-AB3F2
       ↓
invoice.php fetches sale from DB (no auth)
       ↓
Renders public invoice page with all details + QR
```

The QR image is fetched from `api.qrserver.com` at render time — no storage needed.

---

## Local development

```bash
# Using XAMPP/MAMP — put the project in htdocs/pos/
# Then visit http://localhost/pos/login.php

# Create a .env file is NOT needed for XAMPP.
# Edit includes/config.php fallback values for local DB credentials.

# For local Vercel dev (requires Node.js):
npm i -g vercel
vercel dev
# Then visit http://localhost:3000
```

---

## File structure

```
├── vercel.json              ← Vercel config (PHP runtime + URL rewrites)
├── .env.example             ← Copy to .env for local reference
├── database.sql             ← Run once to create all tables (incl. sessions)
├── migrate.sql              ← Inventory log table
├── suppliers_migrate.sql    ← Suppliers + orders tables
├── index.php                ← Dashboard (requires login)
├── login.php                ← Public login page
├── logout.php               ← Destroys session, redirects to login
├── invoice.php              ← PUBLIC invoice viewer (no login needed)
├── includes/
│   ├── config.php           ← DB + constants + QR helpers (reads env vars)
│   ├── session_handler.php  ← MySQL-backed session handler for Vercel
│   ├── auth.php             ← requireLogin() / isAdmin() helpers
│   ├── header.php           ← App shell + sidebar
│   └── footer.php           ← Closes HTML + loads JS
├── pages/
│   ├── pos.php              ← Point of Sale (QR embedded in invoice modal)
│   ├── checkout.php         ← AJAX checkout endpoint (returns QR URLs)
│   ├── sale_detail.php      ← Admin sale detail modal (shows QR)
│   ├── sales.php            ← Sales history (public link button per row)
│   └── ...                  ← Other admin pages (unchanged)
├── css/style.css
└── js/main.js
```

---

## Troubleshooting

**"Database Connection Failed" on Vercel**  
→ Check all 4 DB env vars are set. For PlanetScale, enable SSL:  
add `?ssl_mode=REQUIRED` to `DB_HOST` or configure in code.

**QR code not scanning / wrong URL**  
→ Make sure `APP_URL` is set to your exact Vercel domain (no trailing slash).  
→ After adding a custom domain, update `APP_URL` and redeploy.

**Session not persisting (logged out on every request)**  
→ The `sessions` table must exist. Re-run `database.sql`.  
→ Check that `DB_*` variables are correct — session writes silently fail if DB is unreachable.

**PHP runtime error on Vercel**  
→ Verify `vercel.json` specifies `"vercel-php@0.7.2"`. Check Vercel's PHP runtime  
   [changelog](https://github.com/vercel-community/php) if a newer version is available.

---

## Security notes

- The public invoice page (`/invoice.php`) exposes: item names, quantities, totals, customer first name, and payment method. It does **not** expose phone numbers, email addresses, or addresses.  
- Sessions are stored in MySQL with a 2-hour expiry and `HttpOnly` + `SameSite=Lax` cookies.  
- All user input is escaped via `$conn->real_escape_string()` or prepared statements.  
- Change the default admin password immediately after first login.
# pos-qr

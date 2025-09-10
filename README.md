Becca Stock Management (PHP + MySQL)

What you have now

- Minimal, working PHP app with:
  - Secure login/logout with roles (Admin/Manager/Staff)
  - First‑time setup page to create Admin
  - Dashboard: Today’s Sales, Low Stock alerts, Upcoming Payment reminders, Monthly Profit
  - Master data CRUD: Categories, Units, Locations, Products, Suppliers, Customers
- Stock tracking by location (initialized to 0 for the first location when a product is created)
  - CSV export on all reports; Profit & Loss uses weighted average cost; invoice/header branding

Quick start (XAMPP)

1) Create the database
   - Open phpMyAdmin → create a DB named `becca_stock` (utf8mb4)
   - Import `database/schema.sql`

2) Configure the app
   - Edit `config.php` and set your MySQL username/password if different from defaults.

3) Run the app
   - Navigate to `http://localhost/Becca%20Stock%20Management/public/`
   - On first run, click “Setup Admin” and choose admin email/password
   - Login and start using Master Data pages to add categories, units, locations, suppliers, customers, and products

Roles

- Admin: Full access including user management
- Manager: For future operations (sales, purchases, expenses, reports)
- Staff: For future limited operations (e.g., add sales only)

Next milestones (not yet implemented)

- Sales and Purchases entry screens (auto‑update stock quantities)
- Expense tracker with categories
- Daily Sales report, Purchase report, Profit & Loss
- Invoices (HTML/print) and payment recording
- Email/SMS reminders (optional)

Project structure

- `public/` — Web root (index.php, assets)
- `views/` — Pages + layout partials
- `includes/` — Database, auth, and CSRF helpers
- `database/schema.sql` — MySQL schema + seed rows
 - `database/migrations/` — Optional SQL migrations (e.g., add `sale_items.cost_at_sale`)

Upgrade note

- To store average cost at time of sale, run `database/migrations/001_add_cost_at_sale.sql` in phpMyAdmin on your `becca_stock` database. The app also works without it, computing costs on the fly.
- To enable Settings (company info, currency), also run `database/migrations/002_settings.sql` and optionally `database/migrations/003_currency.sql`. Then go to Admin → Settings.

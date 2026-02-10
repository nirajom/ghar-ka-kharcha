Bilkul Om 👍 niche ek solid **README.md** de raha hoon — direct copy/paste karke repo me daal do. (Tumhare current setup: Apache + PHP + SQLite + FPDF + uploads + Admin/User)

````md
# House Expense & Electricity Tracker (PHP + SQLite)

A simple self-hosted web app for tracking shared house expenses among flatmates and calculating monthly electricity bills (Direct Meter + Inverter Meter).  
Runs on your laptop/server using **Apache + PHP + SQLite**, with optional **PDF downloads** and **bill photo uploads**.

---

## ✨ Features

### 🧾 Expenses
- Add house expenses with:
  - Amount, date, notes
  - Who paid (payer)
  - Split among selected members (3/4/5/6 etc.)
  - Upload bill/transaction screenshot
- Auto split calculation (selected members only)
- Admin-only:
  - Edit / Update / Delete expenses
- Recent expenses view

### 💰 Contributions
- Track monthly contributions paid by each member
- Admin-only edit/update/delete
- Notes + optional photo upload

### 📊 Reports (Monthly / Full)
- Report by:
  - Specific member
  - **Ghar** (overall house report)
  - Filter types:
    - All expenses
    - By month (YYYY-MM)
    - By date range (YYYY-MM-DD to YYYY-MM-DD)
- PDF download for reports (with uploaded images if enabled)

### ⚡ Electricity Bill Calculator
- Two meters:
  - **Direct Meter**
  - **Inverter Meter**
- Uses Previous and Current readings to calculate units
- Default rate: `1 unit = ₹10` (editable)
- Upload photos for both:
  - Previous reading
  - Current reading
- PDF download for electricity bill report

#### ✅ Special Rule: Inverter Meter 50/50
Inverter meter bill is split:
- **My 50%** (counted in total payable)
- **Owner 50%** (shown for reference, not added)

---

## 🧱 Tech Stack
- **PHP** (single-file project: `index.php`)
- **SQLite** (local database file)
- **Apache2** (web server)
- **FPDF** (PDF generation)
- Local file uploads for bill images

---

## 📁 Project Structure

```text
house-expense/
├── index.php
├── expenses.db            (SQLite database)
├── uploads/               (uploaded bills/images)
└── README.md
````

> NOTE: Your existing database file name may differ (example: `expenses.db`).

---

## ✅ Requirements

* Linux (Debian/Parrot/Ubuntu recommended)
* Apache2
* PHP 8+
* PHP extensions:

  * pdo_sqlite
  * mbstring
* FPDF for PDF generation

---

## ⚙️ Installation (Local Laptop / Server)

### 1) Install dependencies

```bash
sudo apt update
sudo apt install apache2 php php-sqlite3 php-mbstring php-fpdf
```

### 2) Copy project to Apache root

```bash
sudo mkdir -p /var/www/html/house-expense
sudo cp -r ./* /var/www/html/house-expense/
```

### 3) Create uploads directory

```bash
sudo mkdir -p /var/www/html/house-expense/uploads
```

### 4) Fix permissions (IMPORTANT for Edit/Delete/Update)

SQLite needs write permission for Apache user (`www-data`).

```bash
sudo chown -R www-data:www-data /var/www/html/house-expense
sudo chmod -R 775 /var/www/html/house-expense

# If your db file is named expenses.db
sudo chmod 664 /var/www/html/house-expense/expenses.db
```

### 5) Restart Apache

```bash
sudo systemctl restart apache2
```

### 6) Open in browser

```text
http://localhost/house-expense/
```

---

## 🌐 Access from Same Network (LAN)

Find your laptop IP:

```bash
ip a | grep inet
```

Example:

```text
http://192.168.1.9/house-expense/
```

> Make sure firewall allows port 80 (or your Apache port).

---

## 🌍 Access Over the Internet (Optional)

If you want free public access:

* Use **Cloudflare Tunnel** (recommended) OR
* Use **Ngrok** OR
* Use port-forwarding + Dynamic DNS (advanced)

> Keep admin password strong if exposing publicly.

---

## 🔐 Admin vs User

* **User:** No login required (view/add if enabled by your config)
* **Admin:** Password protected panel with permissions:

  * Edit / Update / Delete records
  * Modify electricity readings, expenses, contributions

> Admin password is stored in code/config (change it before publishing).

---

## 🛡️ Security Notes (Important)

Before publishing the repo:

* ✅ Do NOT upload real private data
* ✅ Do NOT commit your real `expenses.db`
* ✅ Do NOT commit uploaded bills in `uploads/`
* ✅ Use `.gitignore` to avoid leaking data

Recommended `.gitignore`:

```gitignore
uploads/
*.db
*.sqlite
*.sqlite3
```

---

## 🧪 Troubleshooting

### HTTP ERROR 500 during edit/delete/update

Check Apache error log:

```bash
sudo tail -n 40 /var/log/apache2/error.log
```

Common fix: database became read-only

```bash
sudo chown -R www-data:www-data /var/www/html/house-expense
sudo chmod -R 775 /var/www/html/house-expense
sudo chmod 664 /var/www/html/house-expense/expenses.db
sudo systemctl restart apache2
```

---

## 📌 Credits

Built for a shared flat expense & electricity tracking use-case.
PDF export powered by **FPDF**.

---

## 📜 License

MIT (or choose your preferred license)

```

---

Agar tum chaho to main **.gitignore** aur **LICENSE (MIT)** file bhi ready de dunga, aur README me **screenshots section** + **demo GIF** structure bhi add kar dunga.
```

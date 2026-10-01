# BookBridge Backend Setup Guide (XAMPP Localhost)

Welcome to the BookBridge (UIU Used Textbook Marketplace) backend development environment! This guide walks you through setting up your local environment using XAMPP on Windows.

---

## 1. Prerequisites
- **XAMPP** (with Apache and MySQL / MariaDB) installed.
- **PHP 8.0+** (included in modern XAMPP).
- Web browser (Chrome, Edge, Firefox).

---

## 2. Project Directory
Ensure the repository is placed in your XAMPP web root:
```
C:\xampp\htdocs\UIU-Used-Textbook-Marketplace
```
The application will be accessible at:
```
http://localhost/UIU-Used-Textbook-Marketplace/
```

---

## 3. Database Setup (MySQL/MariaDB)

### Option A: Using phpMyAdmin (Recommended for Beginners)
1. Start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Open your browser and navigate to: `http://localhost/phpmyadmin/`.
3. If setting up a fresh install:
   - Click on the **Import** tab at the top.
   - Click **Choose File** and select:
     ```
     C:\xampp\htdocs\UIU-Used-Textbook-Marketplace\database\bookbridge.sql
     ```
   - Click **Go** at the bottom.
   - The `bookbridge_db` database will be created with all 7 tables and initial demo seed data.
4. If migrating an existing database:
   - Ensure `bookbridge_db` is selected, then import:
     ```
     C:\xampp\htdocs\UIU-Used-Textbook-Marketplace\database\migrations\001_align_to_target_schema.sql
     ```

### Option B: Using MySQL Command Line
Run the following in PowerShell / Command Prompt:
```powershell
C:\xampp\mysql\bin\mysql.exe -u root < "C:\xampp\htdocs\UIU-Used-Textbook-Marketplace\database\bookbridge.sql"
```

---

## 4. Configuration

1. In the `config/` folder, check if `config.php` exists. If not, copy `config.example.php`:
   - Copy `config/config.example.php` -> `config/config.php`
2. Open `config/config.php` and verify your local settings:
   ```php
   'db' => [
       'host'     => '127.0.0.1',
       'port'     => 3306,
       'dbname'   => 'bookbridge_db',
       'username' => 'root',
       'password' => '', // Default XAMPP password is empty
       'charset'  => 'utf8mb4',
   ],
   ```
3. `config/config.php` is ignored by Git, so your local passwords won't accidentally be committed.

---

## 5. Verify Setup (Health Endpoint)

Open your browser or run a test in Postman/browser to visit:
```
http://localhost/UIU-Used-Textbook-Marketplace/api/health.php
```

You should receive an HTTP 200 JSON response:
```json
{
  "status": "ok",
  "timestamp": "2026-10-01T14:00:00+06:00",
  "environment": "development",
  "php_version": "8.2.x",
  "services": {
    "database": "connected"
  }
}
```

If the database shows `"disconnected"`:
- Ensure MySQL is running in the XAMPP Control Panel (green light on port 3306).
- Check that the `bookbridge` database was imported in phpMyAdmin.

---

## 6. Seed Accounts for Testing

The seed file `database/bookbridge.sql` includes ready-to-use demo accounts:

| Role   | Full Name     | UIU Email            | Password      | Student ID  |
| :----- | :------------ | :------------------- | :------------ | :---------- |
| Admin  | Admin User    | `admin@uiu.ac.bd`    | `password123` | `011200001` |
| Seller | Rafiul Islam  | `seller@uiu.ac.bd`   | `password123` | `011211054` |
| Buyer  | Zahir Raihan  | `buyer@uiu.ac.bd`    | `password123` | `011211088` |
| Seller | Nusrat Jahan  | `nusrat@uiu.ac.bd`   | `password123` | `011212030` |
| Buyer  | Tanvir Ahmed  | `tanvir@uiu.ac.bd`   | `password123` | `011213012` |

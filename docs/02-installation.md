# Installation

## 1. Install Dependencies

```bash
composer install
```

If Composer is not available globally, install Composer first from `https://getcomposer.org/`.

## 2. Configure Environment

```bash
cp env .env
```

Minimum `.env` values:

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'

database.default.hostname = localhost
database.default.database = pena_erp
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.port = 3306
```

Use `.env` for credentials and provider keys. Never hardcode secrets in source code.

## 3. Create Database

Create an empty MySQL or MariaDB database:

```sql
CREATE DATABASE pena_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 4. Run Migrations

```bash
php spark migrate --all
```

The `--all` flag is important because Shield migrations live in the vendor namespace.

## 5. Run Seeder

```bash
php spark db:seed PenaErpSeeder
```

Default login:

- Email: `admin@pena-erp.local`
- Password: `Admin123!`

## 6. Run Local Server

```bash
php spark serve
```

Open `http://localhost:8080`.

## Deployment Notes

- Point the web server document root to `public/`
- Keep `writable/` writable by PHP
- Keep uploaded ERP documents under `writable/secure_uploads`
- Disable development mode in production
- Configure HTTPS, secure cookies, backup jobs, and log rotation
- Configure OCR/AI provider keys through `.env`

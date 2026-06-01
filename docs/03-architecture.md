# Architecture

## Stack

- PHP 8.2+
- CodeIgniter 4
- CodeIgniter Shield
- MySQL or MariaDB
- Skote-compatible admin layout structure
- Service layer for tenant, menu, OCR, and AI extraction logic

## Application Layers

- Controllers handle request and response only
- Models handle table access
- Services hold business logic and integrations
- Migrations define schema
- Seeders define baseline data
- Views are organized into layouts, partials, and module views

## Important Paths

- `app/Config/AuthGroups.php`: role and permission matrix
- `app/Database/Migrations`: schema
- `app/Database/Seeds`: seed data
- `app/Services/TenantContext.php`: active company and site
- `app/Services/Ai`: vendor-neutral OCR and AI contracts
- `app/Views/layouts`: main and auth layouts
- `app/Views/partials`: header, sidebar, topbar, footer
- `public/assets/pena`: temporary app styling

## Multi Company

Tenant isolation uses `company_id` and `site_id`.

Rules:

- Transaction tables require `company_id`
- Site-specific records include `site_id`
- User access is stored in `user_company_access` and `user_site_access`
- `TenantContext` manages active company and site in session
- Future repository queries must filter by allowed company and site

## Skote Integration Strategy

The repository did not contain a Skote zip during audit. The app includes the required CodeIgniter view structure:

- `app/Views/layouts/main.php`
- `app/Views/layouts/auth.php`
- `app/Views/partials/header.php`
- `app/Views/partials/sidebar.php`
- `app/Views/partials/topbar.php`
- `app/Views/partials/footer.php`

When the licensed Skote package is available, copy compiled assets into `public/assets/skote` and update `partials/header.php` to reference Skote CSS/JS files.

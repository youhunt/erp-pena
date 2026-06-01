# Testing Checklist

## Installation

- `composer install` completes
- `.env` database is configured
- `php spark migrate --all` completes
- `php spark db:seed PenaErpSeeder` completes
- `php spark serve` starts

## Authentication

- Login page opens
- Admin can login with seeded credentials
- Logout works
- Protected routes redirect guests to login

## Tenant

- Seeded admin has default company access
- Seeded admin has default site access
- `TenantContext` sets active company and site

## Setup Pages

- Dashboard opens
- Companies page lists seeded company
- Sites page lists seeded site
- Sidebar only shows permitted menu items

## AI Documents

- Upload page accepts PDF/image
- Upload rejects unsupported MIME types
- Uploaded file is saved outside `public/`
- Duplicate upload is marked as duplicate
- Document list shows uploaded records

## Database

- All ERP foundation tables exist
- Important unique indexes exist
- Foreign keys are valid
- Soft delete fields exist on master tables

## Security

- `.env` is not committed
- Uploads are not publicly accessible
- Provider secrets are not hardcoded
- CSRF is enabled for forms

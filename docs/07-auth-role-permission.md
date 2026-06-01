# Auth, Role, Permission

## Authentication

Authentication uses CodeIgniter Shield.

Routes are registered through:

```php
service('auth')->routes($routes);
```

Protected ERP routes use the Shield `session` filter.

## Roles

Configured roles:

- `superadmin`
- `company_admin`
- `finance`
- `sales`
- `purchase`
- `inventory`
- `production`
- `viewer`

## Permission Examples

- `sales.customer.view`
- `sales.customer.manage`
- `sales.order.create`
- `sales.order.approve`
- `purchase.po.view`
- `purchase.po.create`
- `purchase.po.approve`
- `inventory.stock.view`
- `inventory.movement.post`
- `finance.gl.post`
- `ai.document.upload`
- `ai.document.review`
- `ai.document.convert`

## Company and Site Access

Shield handles identity, groups, and permissions. PENA ERP adds tenant access tables:

- `user_company_access`
- `user_site_access`

`TenantContext` loads and switches active company/site in session.

## Dynamic Menu

`menu_items.permission` controls sidebar visibility. `MenuService` filters menu rows using Shield permissions.

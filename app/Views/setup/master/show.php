<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$fieldLabel = static function (string $field, array $config): string {
    if (isset($config['fields'][$field]['label'])) {
        return (string) $config['fields'][$field]['label'];
    }

    return ucwords(str_replace('_', ' ', $field));
};

$relationTables = [
    'department_id' => 'departments',
    'warehouse_id' => 'warehouses',
    'location_id' => 'locations',
    'from_uom_id' => 'uoms',
    'to_uom_id' => 'uoms',
    'item_id' => 'items',
    'vat_rate_id' => 'vat_rates',
    'country_id' => 'countries',
    'province_id' => 'provinces',
    'city_id' => 'cities',
    'postal_code_id' => 'postal_codes',
];

$relationCache = [];
$relationLabel = static function (string $field, mixed $value) use (&$relationCache, $relationTables): ?string {
    if ($value === null || $value === '' || ! isset($relationTables[$field])) {
        return null;
    }

    $table = $relationTables[$field];
    $id = (int) $value;
    $cacheKey = $table . ':' . $id;

    if (array_key_exists($cacheKey, $relationCache)) {
        return $relationCache[$cacheKey];
    }

    $record = db_connect()->table($table)->where('id', $id)->get()->getRowArray();
    if ($record === null) {
        return $relationCache[$cacheKey] = (string) $value;
    }

    $code = trim((string) ($record['item_code'] ?? $record['customer'] ?? $record['supplier'] ?? $record['terms_code'] ?? $record['promo_code'] ?? $record['code'] ?? $record['id'] ?? $value));
    $name = trim((string) ($record['item_name'] ?? $record['customern'] ?? $record['supplierna'] ?? $record['terms_name'] ?? $record['promo_description'] ?? $record['name'] ?? ''));

    return $relationCache[$cacheKey] = $name !== '' ? $code . ' - ' . $name : $code;
};

$formatValue = static function (string $field, mixed $value) use ($relationLabel): string {
    $relation = $relationLabel($field, $value);
    if ($relation !== null) {
        return $relation;
    }

    if (in_array($field, ['is_default'], true)) {
        return (int) $value === 1 ? 'Yes' : 'No';
    }

    if (in_array($field, ['is_active', 'active'], true)) {
        return (int) $value === 1 ? 'Active' : 'Inactive';
    }

    if ($value === null || $value === '') {
        return '-';
    }

    if (is_numeric($value) && str_contains((string) $value, '.')) {
        return rtrim(rtrim(number_format((float) $value, 8, '.', ''), '0'), '.');
    }

    return (string) $value;
};

$isCustomer = $resource === 'customers';
$isSupplier = $resource === 'suppliers';
$displayConfig = $config['display'] ?? [];
$codeField = $isCustomer ? 'customer' : ($isSupplier ? 'supplier' : (string) ($displayConfig['code'] ?? 'code'));
$nameField = $isCustomer ? 'customern' : ($isSupplier ? 'supplierna' : (string) ($displayConfig['name'] ?? 'name'));
$descriptionField = (string) ($displayConfig['description'] ?? '');
$referenceField = $isCustomer ? 'customerr' : ($isSupplier ? 'supplierref' : null);

$addressGroups = $isCustomer ? [
    'Office' => ['officeaddre', 'officecity', 'officeprovir', 'officecount', 'officeposta', 'officeconta', 'officephon', 'officehp'],
    'Billing' => ['billingaddre', 'billingcity', 'billingprovi', 'billingcoun', 'billingposta', 'billingconta', 'billingphon', 'billinghp'],
    'Mailing' => ['mailaddres', 'mailcity', 'mailprovin', 'mailcountr', 'mailpostal', 'mailcontac', 'mailphone', 'mailhp'],
    'Shipping' => ['shiptoaddr', 'shiptocity', 'shiptoprovi', 'shiptocour', 'shiptopost', 'shiptocont', 'shiptophon', 'shiptohp'],
] : ($isSupplier ? [
    'Office' => ['officeaddre', 'officecity', 'officeprovir', 'officecoun', 'officeposta', 'officeconta', 'officephon', 'officehp'],
    'Billing' => ['billingadre', 'billingcity', 'billingprovi', 'billingcoun', 'billingposta', 'billingconta', 'billingphon', 'billinghp'],
    'Mailing' => ['mailaddres', 'mailcity', 'mailprovin', 'mailcountr', 'mailpostal', 'mailcontac', 'mailphone', 'mailhp'],
    'Shipping' => ['shiptoaddr', 'shiptocity', 'shiptoprovi', 'shiptocoun', 'shiptopost', 'shiptocont', 'shiptophon', 'shiptohp'],
] : []);

$visibleFields = array_filter(
    array_keys($config['fields'] ?? []),
    static fn (string $field): bool => ! str_ends_with($field, '_address_template')
);
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div>
                        <h4 class="card-title mb-1"><?= esc($formatValue($nameField, $row[$nameField] ?? null)) ?></h4>
                        <div class="text-muted">
                            <?= esc($config['title']) ?> / <?= esc($formatValue($codeField, $row[$codeField] ?? null)) ?>
                            <?php if (! $isCustomer && ! $isSupplier && $descriptionField !== ''): ?>
                                / <?= esc($formatValue($descriptionField, $row[$descriptionField] ?? null)) ?>
                            <?php endif ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-light" href="<?= site_url("setup/{$resource}") ?>">
                            <i class="bx bx-arrow-back me-1"></i> Back
                        </a>
                        <?php if ($canManage): ?>
                            <a class="btn btn-primary" href="<?= site_url("setup/{$resource}/{$row['id']}/edit") ?>">
                                <i class="bx bx-edit me-1"></i> Edit
                            </a>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($isCustomer || $isSupplier): ?>
    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="font-size-15 mb-3">Partner Info</h5>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><th>Code</th><td><?= esc($formatValue($codeField, $row[$codeField] ?? null)) ?></td></tr>
                                <tr><th>Name</th><td><?= esc($formatValue($nameField, $row[$nameField] ?? null)) ?></td></tr>
                                <?php if ($referenceField !== null): ?>
                                    <tr><th>Reference</th><td><?= esc($formatValue($referenceField, $row[$referenceField] ?? null)) ?></td></tr>
                                <?php endif ?>
                                <tr><th>Main Contact</th><td><?= esc($formatValue('contactnar', $row['contactnar'] ?? null)) ?></td></tr>
                                <tr><th>Email</th><td><?= esc($formatValue('email', $row['email'] ?? null)) ?></td></tr>
                                <tr><th>Status</th><td><?= ((int) ($row['is_active'] ?? $row['active'] ?? 1) === 1) ? 'Active' : 'Inactive' ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="font-size-15 mb-3">Tax & Terms</h5>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><th>Terms</th><td><?= esc($formatValue('terms', $row['terms'] ?? $row['terms_code'] ?? null)) ?></td></tr>
                                <tr><th>VAT</th><td><?= esc($formatValue('vat', $row['vat'] ?? null)) ?></td></tr>
                                <tr><th>Tax Code</th><td><?= esc($formatValue('taxcode', $row['taxcode'] ?? null)) ?></td></tr>
                                <tr><th>Tax Number</th><td><?= esc($formatValue('taxnumber', $row['taxnumber'] ?? $row['tax_number'] ?? null)) ?></td></tr>
                                <tr><th>Limit Amount</th><td><?= esc($formatValue('limitamound', $row['limitamound'] ?? null)) ?></td></tr>
                                <tr><th>Limit Days</th><td><?= esc($formatValue('limitdays', $row['limitdays'] ?? null)) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="font-size-15 mb-3">Bank</h5>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><th>Bank 1</th><td><?= esc($formatValue('bank1', $row['bank1'] ?? null)) ?></td></tr>
                                <tr><th>Account 1</th><td><?= esc($formatValue('bankaccou', $row['bankaccou'] ?? null)) ?></td></tr>
                                <tr><th>Bank 2</th><td><?= esc($formatValue('bank2', $row['bank2'] ?? null)) ?></td></tr>
                                <tr><th>Account 2</th><td><?= esc($formatValue('bankaccou2', $row['bankaccou2'] ?? null)) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <?php foreach ($addressGroups as $groupTitle => $fields): ?>
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="font-size-15 mb-3"><?= esc($groupTitle) ?> Address</h5>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($fields as $field): ?>
                                        <tr>
                                            <th style="width: 180px;"><?= esc($fieldLabel($field, $config)) ?></th>
                                            <td><?= nl2br(esc($formatValue($field, $row[$field] ?? null))) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php else: ?>
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="font-size-15 mb-3">Record Detail</h5>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <?php foreach ($visibleFields as $field): ?>
                                    <?php if (($config['fields'][$field]['persist'] ?? true) === false) {
                                        continue;
                                    } ?>
                                    <tr>
                                        <th style="width: 220px;"><?= esc($fieldLabel($field, $config)) ?></th>
                                        <td><?= nl2br(esc($formatValue($field, $row[$field] ?? null))) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>

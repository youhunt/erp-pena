<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$db = \Config\Database::connect();
$tenant = new \App\Services\TenantContext(session());
$companyId = $tenant->activeCompanyId();
$siteId = $tenant->activeSiteId();
$activeSiteCode = '';

$itemOptions = [];
$siteOptions = [];
$departmentOptions = [];
$warehouseOptions = [];

if ($siteId !== null && $db->tableExists('sites')) {
    $activeSite = $db->table('sites')->where('id', $siteId)->get(1)->getRowArray();
    if ($activeSite !== null) {
        $activeSiteCode = (string) ($activeSite['code'] ?? $activeSite['site_code'] ?? '');
    }
}

if ($db->tableExists('items')) {
    $builder = $db->table('items');
    if ($companyId !== null && $db->fieldExists('company_id', 'items')) {
        $builder->where('company_id', $companyId);
    }
    if ($siteId !== null && $db->fieldExists('site_id', 'items')) {
        $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
    }
    if ($db->fieldExists('deleted_at', 'items')) {
        $builder->where('deleted_at', null);
    }
    $itemOptions = $builder->orderBy('item_code', 'ASC')->get(1000)->getResultArray();
}

foreach (['sites' => 'siteOptions', 'departments' => 'departmentOptions', 'warehouses' => 'warehouseOptions'] as $table => $target) {
    if ($db->tableExists($table)) {
        $builder = $db->table($table);
        if ($companyId !== null && $db->fieldExists('company_id', $table)) {
            $builder->where('company_id', $companyId);
        }
        if ($siteId !== null && $table !== 'sites' && $db->fieldExists('site_id', $table)) {
            $builder->groupStart()->where('site_id', $siteId)->orWhere('site_id', null)->orWhere('site_id', 0)->groupEnd();
        }
        if ($db->fieldExists('deleted_at', $table)) {
            $builder->where('deleted_at', null);
        }
        ${$target} = $builder->orderBy('id', 'ASC')->get(500)->getResultArray();
    }
}
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">Item Cost</h4>
                <p class="text-muted mb-0">Master cost per item, site, department, dan warehouse.</p>
            </div>
            <a href="<?= site_url('modules/calculate-cost') ?>" class="btn btn-success">Calculate Cost</a>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

        <?php if (! $hasTable): ?>
            <div class="alert alert-warning">Tabel Item Cost belum tersedia. Jalankan <code>database/hosting/2026-06-24_install_costing_module.sql</code>.</div>
        <?php else: ?>
            <form method="get" action="<?= site_url('modules/item-cost') ?>" class="row g-3 border rounded bg-light p-3 mb-4">
                <input type="hidden" name="action" value="save">
                <div class="col-md-3">
                    <label class="form-label">Item Code</label>
                    <select name="item_code" id="item_code" class="form-select select2" required onchange="window.fillItemCostFields && window.fillItemCostFields()">
                        <option value="">-- Select Item --</option>
                        <?php foreach ($itemOptions as $item): ?>
                            <?php
                                $code = (string) ($item['item_code'] ?? $item['code'] ?? '');
                                $name = (string) ($item['item_name'] ?? $item['name'] ?? '');
                                $description = (string) ($item['description'] ?? $item['remarks'] ?? $name);
                            ?>
                            <option value="<?= esc($code, 'attr') ?>" data-name="<?= esc($name, 'attr') ?>" data-description="<?= esc($description, 'attr') ?>"><?= esc(trim($code . ' - ' . $name)) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Item Name</label><input type="text" name="item_name" id="item_name" class="form-control" readonly></div>
                <div class="col-md-2">
                    <label class="form-label">Site</label>
                    <select name="site_code" id="site_code" class="form-select select2">
                        <option value="">-- Site --</option>
                        <?php foreach ($siteOptions as $site): ?>
                            <?php
                                $code = (string) ($site['code'] ?? $site['site_code'] ?? '');
                                $selected = ((int) ($site['id'] ?? 0) === (int) $siteId || ($activeSiteCode !== '' && $code === $activeSiteCode)) ? 'selected' : '';
                            ?>
                            <option value="<?= esc($code, 'attr') ?>" <?= $selected ?>><?= esc(trim($code . ' - ' . (string) ($site['name'] ?? $site['description'] ?? ''))) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Department</label>
                    <select name="department_code" class="form-select select2">
                        <option value="">-- Department --</option>
                        <?php foreach ($departmentOptions as $department): ?><?php $code = (string) ($department['code'] ?? $department['department_code'] ?? ''); ?>
                            <option value="<?= esc($code, 'attr') ?>"><?= esc(trim($code . ' - ' . (string) ($department['name'] ?? $department['description'] ?? ''))) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Warehouse</label>
                    <select name="warehouse_code" class="form-select select2">
                        <option value="">-- Warehouse --</option>
                        <?php foreach ($warehouseOptions as $warehouse): ?><?php $code = (string) ($warehouse['code'] ?? $warehouse['warehouse_code'] ?? ''); ?>
                            <option value="<?= esc($code, 'attr') ?>"><?= esc(trim($code . ' - ' . (string) ($warehouse['name'] ?? $warehouse['description'] ?? ''))) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-8"><label class="form-label">Description</label><input type="text" name="description" id="description" class="form-control" placeholder="Cost KARET Finish Goods NOVA"></div>
                <div class="col-md-2"><label class="form-label">This Item Cost</label><input type="number" step="0.000001" name="this_item_cost" class="form-control" value="0"></div>
                <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100">Save</button></div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Item</th>
                        <th>Site</th>
                        <th>Department</th>
                        <th>Warehouse</th>
                        <th class="text-end">This Item Cost</th>
                        <th class="text-end">BOM Cost</th>
                        <th class="text-end">Total Cost</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?><tr><td colspan="9" class="text-center text-muted py-4">No item cost yet.</td></tr><?php endif ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?= esc($row['item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($row['item_name'] ?? '') ?></small></td>
                            <td><?= esc($row['site_code'] ?? '') ?></td>
                            <td><?= esc($row['department_code'] ?? '') ?></td>
                            <td><?= esc($row['warehouse_code'] ?? '') ?></td>
                            <td class="text-end"><?= number_format((float) ($row['this_item_cost'] ?? 0), 6) ?></td>
                            <td class="text-end"><?= number_format((float) ($row['bom_cost'] ?? 0), 6) ?></td>
                            <td class="text-end fw-semibold"><?= number_format((float) ($row['total_cost'] ?? 0), 6) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= esc($row['status'] ?? 'draft') ?></span></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-success" href="<?= site_url('modules/calculate-cost?item_code=' . rawurlencode((string) ($row['item_code'] ?? ''))) ?>">Calculate</a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
</div>
<script>
window.fillItemCostFields = function () {
    var itemSelect = document.getElementById('item_code');
    var itemName = document.getElementById('item_name');
    var description = document.getElementById('description');
    if (!itemSelect) return;

    var selected = itemSelect.options[itemSelect.selectedIndex];
    var code = selected ? (selected.value || '') : '';
    var name = selected ? (selected.getAttribute('data-name') || '') : '';
    var desc = selected ? (selected.getAttribute('data-description') || '') : '';

    if (itemName) itemName.value = name;
    if (description) description.value = code ? ('Cost ' + (desc || name || code)) : '';
};

document.addEventListener('DOMContentLoaded', function () {
    var itemSelect = document.getElementById('item_code');
    if (itemSelect) {
        itemSelect.addEventListener('change', window.fillItemCostFields);
        itemSelect.addEventListener('input', window.fillItemCostFields);
    }
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.select2').select2({ width: '100%' });
        jQuery('#item_code').on('select2:select change', window.fillItemCostFields);
        jQuery('#site_code').trigger('change.select2');
    }
    window.setTimeout(window.fillItemCostFields, 100);
});
</script>
<?= $this->endSection() ?>

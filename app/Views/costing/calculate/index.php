<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$db = \Config\Database::connect();
$tenant = new \App\Services\TenantContext(session());
$companyId = $tenant->activeCompanyId();
$siteId = $tenant->activeSiteId();
$itemOptions = [];
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
?>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="card-title mb-1">Calculate Cost</h4>
                <p class="text-muted mb-0">Hitung BOM Cost dari material paling bawah ke atas.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= site_url('modules/cost-type') ?>" class="btn btn-light">Cost Type</a>
                <a href="<?= site_url('modules/item-cost') ?>" class="btn btn-outline-primary">Item Cost</a>
            </div>
        </div>

        <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>
        <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>

        <?php if (! $hasTable): ?>
            <div class="alert alert-warning">Tabel Costing belum tersedia. Jalankan <code>database/hosting/2026-06-24_install_costing_module.sql</code>.</div>
        <?php endif ?>

        <form method="get" action="<?= site_url('modules/calculate-cost') ?>" class="row g-3 border rounded bg-light p-3 mb-4">
            <div class="col-md-5">
                <label class="form-label">Item Code</label>
                <select name="item_code" class="form-select select2" required>
                    <option value="">-- Select Item --</option>
                    <?php foreach ($itemOptions as $item): ?>
                        <?php $code = (string) ($item['item_code'] ?? $item['code'] ?? ''); $name = (string) ($item['item_name'] ?? $item['name'] ?? ''); ?>
                        <option value="<?= esc($code, 'attr') ?>" <?= $itemCode === $code ? 'selected' : '' ?>><?= esc(trim($code . ' - ' . $name)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100">Calculate</button></div>
            <?php if ($itemCode !== ''): ?>
                <div class="col-md-3 d-flex align-items-end"><a class="btn btn-success w-100" href="<?= site_url('modules/calculate-cost?action=save&item_code=' . rawurlencode($itemCode)) ?>">Save to Item Cost</a></div>
            <?php endif ?>
        </form>

        <?php if ($itemCode !== ''): ?>
            <div class="row mb-4">
                <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted">This Item Cost</div><h4><?= number_format((float) ($calculation['this_item_cost'] ?? 0), 6) ?></h4></div></div>
                <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted">BOM Cost</div><h4><?= number_format((float) ($calculation['bom_cost'] ?? 0), 6) ?></h4></div></div>
                <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted">Total Cost</div><h4 class="text-success"><?= number_format((float) ($calculation['total_cost'] ?? 0), 6) ?></h4></div></div>
            </div>
        <?php endif ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                <tr>
                    <th>Level</th>
                    <th>BOM No.</th>
                    <th>Item Child</th>
                    <th class="text-end">Qty/Batch</th>
                    <th class="text-end">Qty Used</th>
                    <th>UoM</th>
                    <th class="text-end">% Ratio</th>
                    <th class="text-end">Factor</th>
                    <th class="text-end">This Item Cost</th>
                    <th class="text-end">BOM Cost</th>
                    <th class="text-end">Total Cost</th>
                    <th>Notes</th>
                </tr>
                </thead>
                <tbody>
                <?php $rows = $calculation['rows'] ?? []; ?>
                <?php if ($itemCode === ''): ?><tr><td colspan="12" class="text-center text-muted py-4">Pilih Item Code untuk calculate cost.</td></tr><?php endif ?>
                <?php if ($itemCode !== '' && $rows === []): ?><tr><td colspan="12" class="text-center text-muted py-4">No BOM child found. Cost diambil dari This Item Cost.</td></tr><?php endif ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= number_format((float) ($row['depth'] ?? 0), 0) ?></td>
                        <td><?= esc($row['bom_no'] ?? '') ?></td>
                        <td style="padding-left: <?= ((int) ($row['depth'] ?? 0)) * 18 ?>px"><strong><?= esc($row['item_code'] ?? '') ?></strong><br><small class="text-muted"><?= esc($row['item_name'] ?? '') ?></small></td>
                        <td class="text-end"><?= number_format((float) ($row['qty_batch'] ?? 0), 6) ?></td>
                        <td class="text-end"><?= number_format((float) ($row['qty_used'] ?? 0), 6) ?></td>
                        <td><?= esc($row['uom_code'] ?? '') ?></td>
                        <td class="text-end"><?= number_format((float) ($row['ratio_percent'] ?? 0), 6) ?></td>
                        <td class="text-end"><?= number_format((float) ($row['factor'] ?? 0), 6) ?></td>
                        <td class="text-end"><?= number_format((float) ($row['this_item_cost'] ?? 0), 6) ?></td>
                        <td class="text-end"><?= number_format((float) ($row['bom_cost'] ?? 0), 6) ?></td>
                        <td class="text-end fw-semibold"><?= number_format((float) ($row['total_cost'] ?? 0), 6) ?></td>
                        <td><?= esc($row['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.select2').select2({ width: '100%' });
    }
});
</script>
<?= $this->endSection() ?>

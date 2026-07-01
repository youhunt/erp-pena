<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
helper('master_display');
$firstLine = $lines[0] ?? [];
$lineWhs = trim((string) ($firstLine['whs'] ?? ''));
$lineLoc = trim((string) ($firstLine['loc'] ?? ''));
$pick = static function (array $row, array $keys, mixed $default = ''): mixed {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && trim((string) $row[$key]) !== '') {
            return $row[$key];
        }
    }
    return $default;
};
$currentDept = old('dept', $allocation['dept'] ?? '');
$currentWhs = old('whs', $allocation['whs'] ?? '');
?>
<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Allocation Order</h4>
                <p class="text-muted mb-3"><?= esc($allocation['allocnumb'] ?? '-') ?></p>
                <table class="table table-sm mb-0">
                    <tr><th>Status</th><td><span class="badge bg-secondary"><?= esc($allocation['status'] ?? '-') ?></span></td></tr>
                    <tr><th>Date</th><td><?= esc($allocation['allocdate'] ?? '-') ?></td></tr>
                    <tr><th>Customer</th><td><?= esc(($allocation['customer'] ?? '-') . ' ' . ($allocation['customern'] ?? '')) ?></td></tr>
                    <tr><th>Site</th><td><?= esc(erp_site_label($allocation)) ?></td></tr>
                    <tr><th>Line Warehouse</th><td><?= esc($lineWhs !== '' ? erp_warehouse_label($firstLine, 'whs') : '-') ?></td></tr>
                    <tr><th>Line Location</th><td><?= esc($lineLoc !== '' ? erp_location_label($firstLine, 'loc') : '-') ?></td></tr>
                </table>
                <div class="alert alert-info mt-3 mb-0 small">
                    Edit ini hanya untuk header/administrasi. Qty allocation, stock reserved, batch, warehouse/location line tidak berubah.
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <form method="post" action="<?= site_url('sales/allocations/' . (int) $allocation['id']) ?>">
            <?= csrf_field() ?>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-4">
                        <div>
                            <h4 class="card-title mb-1">Edit Allocation Header</h4>
                            <p class="text-muted mb-0">Dept, Warehouse header/filter, Ship Date, Ship To, dan Remarks bisa dirapikan tanpa mengubah reservasi stock.</p>
                        </div>
                        <a href="<?= site_url('sales/allocations/' . (int) $allocation['id']) ?>" class="btn btn-light">Back</a>
                    </div>

                    <?php if (session('error')): ?><div class="alert alert-danger"><?= esc(session('error')) ?></div><?php endif ?>
                    <?php if (session('message')): ?><div class="alert alert-success"><?= esc(session('message')) ?></div><?php endif ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select name="dept" class="form-select select2-basic">
                                <option value="">- Optional -</option>
                                <?php foreach ($departments as $department): ?>
                                    <?php
                                    $code = (string) $pick($department, ['code', 'dept_code', 'department_code']);
                                    $name = (string) $pick($department, ['name', 'dept_name', 'department_name', 'description']);
                                    ?>
                                    <option value="<?= esc($code, 'attr') ?>" <?= (string) $currentDept === $code ? 'selected' : '' ?>><?= esc(trim($code . ' - ' . $name, ' -')) ?></option>
                                <?php endforeach ?>
                                <?php if ((string) $currentDept !== '' && ! in_array((string) $currentDept, array_map(static fn ($row) => (string) ($row['code'] ?? $row['dept_code'] ?? $row['department_code'] ?? ''), $departments), true)): ?>
                                    <option value="<?= esc((string) $currentDept, 'attr') ?>" selected><?= esc((string) $currentDept) ?> (existing)</option>
                                <?php endif ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Warehouse Header / Filter</label>
                            <select name="whs" class="form-select select2-basic">
                                <option value="">- Optional / follow line warehouse -</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <?php
                                    $code = (string) $pick($warehouse, ['code', 'warehouse_code', 'whs']);
                                    $name = (string) $pick($warehouse, ['name', 'warehouse_name', 'description']);
                                    ?>
                                    <option value="<?= esc($code, 'attr') ?>" <?= (string) $currentWhs === $code ? 'selected' : '' ?>><?= esc(trim($code . ' - ' . $name, ' -')) ?></option>
                                <?php endforeach ?>
                                <?php if ((string) $currentWhs !== '' && ! in_array((string) $currentWhs, array_map(static fn ($row) => (string) ($row['code'] ?? $row['warehouse_code'] ?? $row['whs'] ?? ''), $warehouses), true)): ?>
                                    <option value="<?= esc((string) $currentWhs, 'attr') ?>" selected><?= esc((string) $currentWhs) ?> (existing)</option>
                                <?php endif ?>
                            </select>
                            <small class="text-muted">Kalau dikosongkan, detail akan menampilkan warehouse dari line allocation pertama.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ship Date</label>
                            <input type="date" name="shipdate" class="form-control" value="<?= esc(old('shipdate', $allocation['shipdate'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ship To</label>
                            <input type="text" name="shipto" class="form-control" maxlength="12" value="<?= esc(old('shipto', $allocation['shipto'] ?? '')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Allocation No</label>
                            <input type="text" class="form-control" value="<?= esc($allocation['allocnumb'] ?? '-') ?>" readonly>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" maxlength="500" rows="3"><?= esc(old('remarks', $allocation['remarks'] ?? '')) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i> Save Header</button>
                        <a href="<?= site_url('sales/allocations/' . (int) $allocation['id']) ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

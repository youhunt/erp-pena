<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Active Company</p>
                <h5 class="mb-0"><?= $company ? esc(($company['code'] ?? '') . ' - ' . ($company['name'] ?? '')) : 'Not selected' ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-1">Active Site</p>
                <h5 class="mb-0"><?= $site ? esc(($site['code'] ?? '') . ' - ' . ($site['name'] ?? '')) : 'Not selected' ?></h5>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h4 class="card-title mb-1">ERP Auto Setup</h4>
                <p class="text-muted mb-0">Jalankan setup fondasi ERP dari browser tanpa CLI dan tanpa SQL manual.</p>
            </div>
            <form method="post" action="<?= site_url('system/auto-setup/run') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Jalankan ERP Auto Setup untuk active company/site?')">
                    <i class="bx bx-play-circle me-1"></i> Run Auto Setup
                </button>
            </form>
        </div>

        <?php if (session('message')): ?>
            <div class="alert alert-success"><?= esc(session('message')) ?></div>
        <?php endif ?>
        <?php if (session('error')): ?>
            <div class="alert alert-danger"><?= esc(session('error')) ?></div>
        <?php endif ?>

        <div class="alert alert-info">
            Auto Setup akan membuat data default: COA, GL Book, GL Posting Profile, UOM, Currency, Transaction Code, Cash/Bank, Department, Warehouse, dan Location.
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Area</th>
                        <th class="text-end">Jumlah Data</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary as $label => $count): ?>
                        <tr>
                            <td class="fw-semibold"><?= esc($label) ?></td>
                            <td class="text-end"><?= esc((string) $count) ?></td>
                            <td>
                                <?php if ((int) $count > 0): ?>
                                    <span class="badge bg-success-subtle text-success">READY</span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning">EMPTY</span>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    <?php if ($summary === []): ?>
                        <tr><td colspan="3" class="text-muted">Pilih active company dulu sebelum menjalankan Auto Setup.</td></tr>
                    <?php endif ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex gap-2 mt-3">
            <a href="<?= site_url('system/core-health') ?>" class="btn btn-light">Core Health</a>
            <a href="<?= site_url('setup/companies') ?>" class="btn btn-outline-secondary">Companies</a>
            <a href="<?= site_url('setup/sites') ?>" class="btn btn-outline-secondary">Sites</a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

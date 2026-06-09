<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="card-title mb-1">Legacy COA Source</h4>
                        <p class="text-muted mb-0">Rows from source table <code>coa</code>.</p>
                    </div>
                    <a href="<?= site_url('gl/utilities') ?>" class="btn btn-outline-primary btn-sm">Sync Utility</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-nowrap table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Book</th><th>Company</th><th>Site</th><th>Code</th><th>Remarks</th></tr></thead>
                        <tbody>
                        <?php foreach ($coaRows as $row): ?>
                            <tr>
                                <td><?= esc($row['booktype'] ?? '') ?></td>
                                <td><?= esc($row['company'] ?? '') ?></td>
                                <td><?= esc($row['site'] ?? '') ?></td>
                                <td class="fw-semibold"><code><?= esc($row['code'] ?? '') ?></code></td>
                                <td><?= esc($row['remarks'] ?? '') ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($coaRows === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No legacy COA rows.</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">COA Line</h4>
                <p class="text-muted mb-3">Rows from source table <code>coaline</code>.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>Column</th><th>Description</th></tr></thead>
                        <tbody>
                        <?php foreach ($coaLines as $row): ?>
                            <tr><td><code><?= esc($row['column'] ?? '') ?></code></td><td><?= esc($row['description'] ?? '') ?></td></tr>
                        <?php endforeach ?>
                        <?php if ($coaLines === []): ?><tr><td colspan="2" class="text-center text-muted py-4">No COA line rows.</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

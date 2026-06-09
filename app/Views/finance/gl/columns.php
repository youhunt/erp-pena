<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">GL Column</h4>
                <p class="text-muted mb-3">Legacy GL column setup from <code>glcolumn</code>.</p>
                <div class="table-responsive">
                    <table class="table table-nowrap table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Book Type</th><th>Company</th><th>Site</th><th>Type</th><th>Remarks</th></tr></thead>
                        <tbody>
                        <?php foreach ($columns as $row): ?>
                            <tr>
                                <td><code><?= esc($row['booktype'] ?? '') ?></code></td>
                                <td><?= esc($row['company'] ?? '') ?></td>
                                <td><?= esc($row['site'] ?? '') ?></td>
                                <td><?= esc($row['type'] ?? '') ?></td>
                                <td><?= esc($row['remarks'] ?? '') ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($columns === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No GL column rows.</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">GL Column Line</h4>
                <p class="text-muted mb-3">Column code descriptions from <code>glcolumnline</code>.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>Code</th><th>Description</th></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $row): ?>
                            <tr><td><code><?= esc($row['code'] ?? '') ?></code></td><td><?= esc($row['description'] ?? '') ?></td></tr>
                        <?php endforeach ?>
                        <?php if ($lines === []): ?><tr><td colspan="2" class="text-center text-muted py-4">No GL column line rows.</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

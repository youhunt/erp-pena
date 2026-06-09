<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Recurring Master</h4>
                <p class="text-muted mb-3">Rows from <code>recurring</code>.</p>
                <div class="table-responsive">
                    <table class="table table-nowrap table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Prefix</th><th>Rec No</th><th>Trans Date</th><th>Site</th><th>Post Date</th><th>Remarks</th></tr></thead>
                        <tbody>
                        <?php foreach ($recurringRows as $row): ?>
                            <tr>
                                <td><?= esc($row['prefix'] ?? '') ?></td>
                                <td class="fw-semibold"><code><?= esc($row['recno'] ?? '') ?></code></td>
                                <td><?= esc($row['transdate'] ?? '') ?></td>
                                <td><?= esc($row['site'] ?? '') ?></td>
                                <td><?= esc($row['postdate'] ?? '') ?></td>
                                <td><?= esc($row['remarks'] ?? '') ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($recurringRows === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No recurring master rows.</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Recurring Lines</h4>
                <p class="text-muted mb-3">Rows from <code>recurring_line</code>.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>Trans Code</th><th>Column</th><th>Currency</th><th>Amount</th><th>Rate</th><th>Description</th></tr></thead>
                        <tbody>
                        <?php foreach ($recurringLines as $row): ?>
                            <tr>
                                <td><code><?= esc($row['transcode'] ?? '') ?></code></td>
                                <td><?= esc($row['column'] ?? '') ?></td>
                                <td><?= esc($row['currency'] ?? '') ?></td>
                                <td class="text-end"><?= number_format((float) ($row['transamount'] ?? 0), 2) ?></td>
                                <td class="text-end"><?= number_format((float) ($row['rate'] ?? 0), 6) ?></td>
                                <td><?= esc($row['description'] ?? '') ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($recurringLines === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No recurring line rows.</td></tr><?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

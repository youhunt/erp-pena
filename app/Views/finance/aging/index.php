<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1"><?= esc($title) ?></h4>
                <p class="text-muted mb-0">Outstanding invoice aging by active company/site.</p>
            </div>
            <form method="get" action="<?= current_url() ?>" class="d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label class="form-label">As Of</label>
                    <input type="date" name="as_of" value="<?= esc($asOf) ?>" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary"><i class="bx bx-refresh me-1"></i> Refresh</button>
            </form>
        </div>

        <div class="row">
            <?php foreach ([
                'current' => 'Current',
                'days_1_30' => '1-30',
                'days_31_60' => '31-60',
                'days_61_90' => '61-90',
                'days_over_90' => '> 90',
            ] as $field => $label): ?>
                <div class="col-xl col-md-4 col-sm-6">
                    <div class="card border shadow-none mb-3">
                        <div class="card-body py-3">
                            <p class="text-muted mb-1"><?= esc($label) ?></p>
                            <h5 class="mb-0">Rp <?= esc(number_format((float) ($totals[$field] ?? 0), 0, ',', '.')) ?></h5>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-3"><?= esc($config['partnerLabel'] ?? 'Partner') ?> Summary</h5>
        <div class="table-responsive">
            <table class="table table-sm table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= esc($config['partnerLabel'] ?? 'Partner') ?></th>
                        <th class="text-end">Current</th>
                        <th class="text-end">1-30</th>
                        <th class="text-end">31-60</th>
                        <th class="text-end">61-90</th>
                        <th class="text-end">&gt; 90</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($summary as $row): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= esc($row['partner_name'] ?? '-') ?></div>
                            <small class="text-muted"><?= esc($row['partner_code'] ?? '-') ?></small>
                        </td>
                        <td class="text-end"><?= esc(number_format((float) ($row['current'] ?? 0), 0, ',', '.')) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['days_1_30'] ?? 0), 0, ',', '.')) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['days_31_60'] ?? 0), 0, ',', '.')) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['days_61_90'] ?? 0), 0, ',', '.')) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['days_over_90'] ?? 0), 0, ',', '.')) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($row['total'] ?? 0), 0, ',', '.')) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($summary === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No outstanding invoice found.</td></tr>
                <?php endif ?>
                </tbody>
                <?php if ($summary !== []): ?>
                    <tfoot class="table-light">
                        <tr>
                            <th>Total</th>
                            <th class="text-end"><?= esc(number_format((float) ($totals['current'] ?? 0), 0, ',', '.')) ?></th>
                            <th class="text-end"><?= esc(number_format((float) ($totals['days_1_30'] ?? 0), 0, ',', '.')) ?></th>
                            <th class="text-end"><?= esc(number_format((float) ($totals['days_31_60'] ?? 0), 0, ',', '.')) ?></th>
                            <th class="text-end"><?= esc(number_format((float) ($totals['days_61_90'] ?? 0), 0, ',', '.')) ?></th>
                            <th class="text-end"><?= esc(number_format((float) ($totals['days_over_90'] ?? 0), 0, ',', '.')) ?></th>
                            <th class="text-end"><?= esc(number_format((float) ($totals['total'] ?? 0), 0, ',', '.')) ?></th>
                        </tr>
                    </tfoot>
                <?php endif ?>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title mb-3">Outstanding Invoice Detail</h5>
        <div class="table-responsive">
            <table class="table table-sm table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice No</th>
                        <th>Date</th>
                        <th>Due Date</th>
                        <th><?= esc($config['partnerLabel'] ?? 'Partner') ?></th>
                        <th>Bucket</th>
                        <th class="text-end">Age Days</th>
                        <th class="text-end">Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="<?= esc($row['document_url'] ?? '#') ?>" class="fw-semibold"><?= esc($row['invoice_no'] ?? '-') ?></a></td>
                        <td><?= esc($row['invoice_date'] ?? '-') ?></td>
                        <td><?= esc($row['due_date'] ?? '-') ?></td>
                        <td>
                            <div><?= esc($row[$config['partnerNameField']] ?? '-') ?></div>
                            <small class="text-muted"><?= esc($row[$config['partnerCodeField']] ?? '-') ?></small>
                        </td>
                        <td><span class="badge bg-secondary"><?= esc($row['bucket'] ?? '-') ?></span></td>
                        <td class="text-end"><?= esc(number_format((float) ($row['age_days'] ?? 0), 0, ',', '.')) ?></td>
                        <td class="text-end fw-semibold"><?= esc(number_format((float) ($row['outstanding_amount'] ?? 0), 0, ',', '.')) ?></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No outstanding invoice found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

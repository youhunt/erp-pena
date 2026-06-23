<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                    <div>
                        <h4 class="card-title mb-1">Document Numbering</h4>
                        <p class="text-muted mb-0">Setup nomor dokumen sekarang memakai <strong>Transaction Codes</strong> sebagai sumber utama.</p>
                    </div>
                    <a href="<?= site_url('setup/transaction-codes') ?>" class="btn btn-light">Open Transaction Codes</a>
                </div>

                <?php if (session('message')): ?>
                    <div class="alert alert-success"><?= esc(session('message')) ?></div>
                <?php endif ?>
                <?php if (session('error')): ?>
                    <div class="alert alert-danger"><?= esc(session('error')) ?></div>
                <?php endif ?>

                <div class="alert alert-info">
                    <div class="fw-semibold mb-1">Token format yang bisa dipakai</div>
                    <code>{PREFIX}</code>, <code>{CODE}</code>, <code>{YYYY}</code>, <code>{YY}</code>, <code>{MM}</code>, <code>{DD}</code>, <code>{SEQ}</code>, <code>{PERIOD}</code>
                    <div class="mt-2 small">
                        Contoh PO001: <code>{PREFIX}{SEQ}</code>, reset <code>never</code>, padding <code>3</code>.<br>
                        Contoh PO/202606/0001: <code>{PREFIX}/{YYYY}{MM}/{SEQ}</code>, reset <code>monthly</code>, padding <code>4</code>.
                    </div>
                </div>

                <form method="post" action="<?= site_url('setup/document-numbering') ?>">
                    <?= csrf_field() ?>
                    <div class="table-responsive">
                        <table class="table table-nowrap align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:80px;">Code</th>
                                    <th style="min-width:160px;">Name</th>
                                    <th style="min-width:120px;">Prefix</th>
                                    <th style="min-width:230px;">Format</th>
                                    <th style="min-width:130px;">Reset</th>
                                    <th style="min-width:100px;">Padding</th>
                                    <th style="min-width:180px;">Next Preview</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach (($codes ?? []) as $index => $row): ?>
                                <?php $code = strtoupper(trim((string) ($row['code'] ?? $row['transaction_code'] ?? ''))); ?>
                                <tr>
                                    <td>
                                        <input type="hidden" name="rows[<?= $index ?>][id]" value="<?= (int) ($row['id'] ?? 0) ?>">
                                        <input type="text" class="form-control form-control-sm fw-semibold" name="rows[<?= $index ?>][code]" value="<?= esc($code, 'attr') ?>" required>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" name="rows[<?= $index ?>][name]" value="<?= esc((string) ($row['name'] ?? ''), 'attr') ?>"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="rows[<?= $index ?>][prefix]" value="<?= esc((string) ($row['prefix'] ?? $code), 'attr') ?>"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="rows[<?= $index ?>][format]" value="<?= esc((string) ($row['format'] ?? '{PREFIX}/{YYYY}{MM}/{SEQ}'), 'attr') ?>"></td>
                                    <td>
                                        <?php $reset = (string) ($row['reset_period'] ?? 'monthly'); ?>
                                        <select class="form-select form-select-sm" name="rows[<?= $index ?>][reset_period]">
                                            <?php foreach (['daily' => 'Daily', 'monthly' => 'Monthly', 'yearly' => 'Yearly', 'never' => 'Never'] as $value => $label): ?>
                                                <option value="<?= esc($value) ?>" <?= $reset === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                                            <?php endforeach ?>
                                        </select>
                                    </td>
                                    <td><input type="number" min="1" max="12" class="form-control form-control-sm" name="rows[<?= $index ?>][padding]" value="<?= esc((string) ($row['padding'] ?? 5), 'attr') ?>"></td>
                                    <td><span class="badge bg-light text-dark font-size-12"><?= esc($previews[$code] ?? '-') ?></span></td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="rows[<?= $index ?>][is_active]" value="1" <?= (int) ($row['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                </tr>
                                <?php if (! empty($sequences[$code])): ?>
                                    <tr>
                                        <td></td>
                                        <td colspan="7">
                                            <div class="small text-muted mb-1">Sequence aktif untuk <?= esc($code) ?>:</div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($sequences[$code] as $seq): ?>
                                                    <div class="border rounded px-2 py-1 small bg-light">
                                                        <span class="me-2"><?= esc(($seq['period_key'] ?? '-') . ' / last: ' . ($seq['last_number'] ?? 0) . ' / ' . ($seq['last_document_no'] ?? '-')) ?></span>
                                                        <form method="post" action="<?= site_url('setup/document-numbering/reset-sequence') ?>" class="d-inline" onsubmit="return confirm('Reset sequence <?= esc($code, 'js') ?> period <?= esc((string) ($seq['period_key'] ?? ''), 'js') ?>? Nomor berikutnya akan mulai ulang untuk period ini.');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="transaction_code" value="<?= esc($code, 'attr') ?>">
                                                            <input type="hidden" name="period_key" value="<?= esc((string) ($seq['period_key'] ?? ''), 'attr') ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0">Reset</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif ?>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-primary" type="submit"><i class="bx bx-save me-1"></i> Save Numbering Setup</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

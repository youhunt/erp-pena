<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">GL Entries</h4>
                <p class="text-muted mb-0">Posted manual journals and future ERP posting journal entries.</p>
            </div>
            <a href="<?= site_url('gl/entries/new') ?>" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> New GL Entry
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Journal No</th>
                        <th>Period</th>
                        <th>Description</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?= esc($entry['journal_date'] ?? '-') ?></td>
                        <td class="fw-semibold"><?= esc($entry['journal_no'] ?? '-') ?></td>
                        <td><?= esc($entry['period'] ?? '-') ?></td>
                        <td><?= esc($entry['description'] ?? '-') ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($entry['total_debit'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= esc(number_format((float) ($entry['total_credit'] ?? 0), 2)) ?></td>
                        <td><span class="badge bg-success"><?= esc($entry['status'] ?? 'posted') ?></span></td>
                        <td class="text-end"><a href="<?= site_url('gl/entries/' . $entry['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bx bx-show"></i></a></td>
                    </tr>
                <?php endforeach ?>
                <?php if ($entries === []): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No GL entry found.</td></tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

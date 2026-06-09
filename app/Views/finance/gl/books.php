<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h4 class="card-title mb-1">Modern GL Books</h4>
                        <p class="text-muted mb-0">Ledger books used by ERP posting.</p>
                    </div>
                    <a href="<?= site_url('gl/utilities') ?>" class="btn btn-outline-primary btn-sm">GL Utilities</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-nowrap table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Book Code</th><th>Name</th><th>Currency</th><th>Default</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td class="fw-semibold"><code><?= esc($book['book_code']) ?></code></td>
                                <td><?= esc($book['book_name']) ?></td>
                                <td><?= esc($book['currency_code'] ?? 'IDR') ?></td>
                                <td><span class="badge bg-<?= (int) ($book['is_default'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int) ($book['is_default'] ?? 0) === 1 ? 'Yes' : 'No' ?></span></td>
                                <td><span class="badge bg-<?= (int) ($book['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int) ($book['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($books === []): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No modern GL book found. Run GL Utilities &gt; Initialize Defaults.</td></tr>
                        <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Legacy GL Book Source</h4>
                <p class="text-muted mb-3">Rows from <code>glbook</code> source table.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>Book Type</th><th>Currency</th><th>Year</th><th>Company</th><th>Site</th></tr></thead>
                        <tbody>
                        <?php foreach ($legacyBooks as $row): ?>
                            <tr>
                                <td><code><?= esc($row['booktype'] ?? '') ?></code></td>
                                <td><?= esc($row['currency'] ?? '') ?></td>
                                <td><?= esc($row['year'] ?? '') ?></td>
                                <td><?= esc($row['company'] ?? '') ?></td>
                                <td><?= esc($row['site'] ?? '') ?></td>
                            </tr>
                        <?php endforeach ?>
                        <?php if ($legacyBooks === []): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No legacy glbook rows.</td></tr>
                        <?php endif ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

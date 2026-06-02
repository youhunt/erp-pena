<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h4 class="card-title mb-1">Audit Logs</h4>
                <p class="text-muted mb-0">Recent system activity across master data and ERP workflows.</p>
            </div>
        </div>

        <form method="get" class="row g-2 align-items-end mb-3">
            <div class="col-lg-4 col-md-6">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" value="<?= esc($filters['q'] ?? '') ?>" placeholder="Search action, record, description...">
            </div>
            <div class="col-lg-3 col-md-3">
                <label class="form-label">Module</label>
                <select name="module" class="form-select">
                    <option value="">All Modules</option>
                    <?php foreach ($modules as $row): ?>
                        <option value="<?= esc($row['module']) ?>" <?= ($filters['module'] ?? '') === $row['module'] ? 'selected' : '' ?>><?= esc($row['module']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-3">
                <label class="form-label">Action</label>
                <select name="action" class="form-select">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $row): ?>
                        <option value="<?= esc($row['action']) ?>" <?= ($filters['action'] ?? '') === $row['action'] ? 'selected' : '' ?>><?= esc($row['action']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bx bx-search me-1"></i> Filter
                </button>
                <a class="btn btn-light" href="<?= site_url('audit-logs') ?>">Reset</a>
            </div>
        </form>

        <div class="row g-2 align-items-end mb-3">
            <div class="col-lg-4 col-md-6">
                <label class="form-label" for="auditClientSearch">Quick Search</label>
                <input type="text" class="form-control" id="auditClientSearch" placeholder="Filter current results...">
            </div>
            <div class="col-lg-2 col-md-3">
                <label class="form-label" for="auditPerPage">Rows</label>
                <select class="form-select" id="auditPerPage">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0" id="auditLogTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>Record</th>
                        <th>Description</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr data-log-row="1">
                        <td><?= esc($log['created_at'] ?? '-') ?></td>
                        <td><span class="badge bg-light text-dark"><?= esc($log['module'] ?? '-') ?></span></td>
                        <td><span class="badge bg-info"><?= esc($log['action'] ?? '-') ?></span></td>
                        <td><?= esc($log['table_name'] ?? '-') ?></td>
                        <td>
                            <div class="fw-semibold"><?= esc($log['record_code'] ?? $log['record_id'] ?? '-') ?></div>
                            <small class="text-muted">ID: <?= esc($log['record_id'] ?? '-') ?></small>
                        </td>
                        <td><?= esc($log['description'] ?? '-') ?></td>
                        <td><?= esc($log['user_id'] ?? '-') ?></td>
                    </tr>
                <?php endforeach ?>

                <tr id="auditEmptyRow" class="d-none">
                    <td colspan="7" class="text-center text-muted py-4">No matching audit logs found.</td>
                </tr>

                <?php if ($logs === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No audit logs yet.</td>
                    </tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <div class="text-muted small" id="auditPaginationInfo"></div>
            <div class="btn-group" role="group" aria-label="Pagination">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="auditPrevPage">Previous</button>
                <button class="btn btn-outline-secondary btn-sm disabled" type="button" id="auditPageInfo">Page 1</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" id="auditNextPage">Next</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('auditLogTable');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr[data-log-row]'));
    const emptyRow = document.getElementById('auditEmptyRow');
    const search = document.getElementById('auditClientSearch');
    const perPage = document.getElementById('auditPerPage');
    const prev = document.getElementById('auditPrevPage');
    const next = document.getElementById('auditNextPage');
    const pageInfo = document.getElementById('auditPageInfo');
    const paginationInfo = document.getElementById('auditPaginationInfo');
    let page = 1;

    function matchedRows() {
        const keyword = (search.value || '').toLowerCase().trim();
        return rows.filter(row => keyword === '' || row.innerText.toLowerCase().includes(keyword));
    }

    function render() {
        const matches = matchedRows();
        const limit = parseInt(perPage.value, 10) || 25;
        const totalPages = Math.max(1, Math.ceil(matches.length / limit));
        page = Math.min(page, totalPages);
        const start = (page - 1) * limit;
        const end = start + limit;

        rows.forEach(row => row.classList.add('d-none'));
        matches.slice(start, end).forEach(row => row.classList.remove('d-none'));

        if (emptyRow) {
            emptyRow.classList.toggle('d-none', matches.length > 0);
        }

        prev.disabled = page <= 1;
        next.disabled = page >= totalPages;
        pageInfo.textContent = 'Page ' + page + ' / ' + totalPages;
        paginationInfo.textContent = matches.length === 0
            ? 'Showing 0 of ' + rows.length + ' logs'
            : 'Showing ' + (start + 1) + '-' + Math.min(end, matches.length) + ' of ' + matches.length + ' logs';
    }

    search.addEventListener('input', function () { page = 1; render(); });
    perPage.addEventListener('change', function () { page = 1; render(); });
    prev.addEventListener('click', function () { if (page > 1) { page--; render(); } });
    next.addEventListener('click', function () { page++; render(); });

    render();
});
</script>
<?= $this->endSection() ?>

<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">User Management</h4>
                <p class="text-muted mb-0">Manage ERP users, roles, company access, and site access.</p>
            </div>
            <a href="<?= site_url('admin/users/new') ?>" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> New User
            </a>
        </div>

        <div class="row g-2 align-items-end mb-3">
            <div class="col-lg-5 col-md-6">
                <label class="form-label" for="userSearch">Search</label>
                <input type="text" class="form-control" id="userSearch" placeholder="Search username, email, or role...">
            </div>
            <div class="col-lg-3 col-md-3">
                <label class="form-label" for="userStatusFilter">Status</label>
                <select class="form-select" id="userStatusFilter">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3">
                <label class="form-label" for="userPerPage">Rows</label>
                <select class="form-select" id="userPerPage">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="col-lg-2 text-lg-end">
                <button class="btn btn-light w-100" type="button" id="userResetFilter">
                    <i class="bx bx-reset me-1"></i> Reset
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-nowrap table-hover align-middle mb-0" id="userDataTable">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $row): ?>
                    <?php $status = (int) $row['active'] === 1 ? 'active' : 'inactive'; ?>
                    <tr data-status="<?= esc($status) ?>">
                        <td class="fw-semibold"><?= esc($row['username']) ?></td>
                        <td><?= esc($row['email']) ?></td>
                        <td><?= esc($row['groups'] ?: '-') ?></td>
                        <td>
                            <span class="badge bg-<?= $status === 'active' ? 'success' : 'secondary' ?>">
                                <?= $status === 'active' ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="<?= site_url('admin/users/' . $row['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bx bx-edit"></i>
                            </a>
                            <form action="<?= site_url('admin/users/' . $row['id'] . '/toggle') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Change this user status?')">
                                    <i class="bx bx-power-off"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>

                <tr id="userEmptyRow" class="d-none">
                    <td colspan="5" class="text-center text-muted py-4">No matching users found.</td>
                </tr>

                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                <?php endif ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <div class="text-muted small" id="userPaginationInfo"></div>
            <div class="btn-group" role="group" aria-label="Pagination">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="userPrevPage">Previous</button>
                <button class="btn btn-outline-secondary btn-sm disabled" type="button" id="userPageInfo">Page 1</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" id="userNextPage">Next</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('userDataTable');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tbody tr[data-status]'));
    const emptyRow = document.getElementById('userEmptyRow');
    const search = document.getElementById('userSearch');
    const status = document.getElementById('userStatusFilter');
    const perPage = document.getElementById('userPerPage');
    const reset = document.getElementById('userResetFilter');
    const prev = document.getElementById('userPrevPage');
    const next = document.getElementById('userNextPage');
    const pageInfo = document.getElementById('userPageInfo');
    const paginationInfo = document.getElementById('userPaginationInfo');
    let page = 1;

    function matchedRows() {
        const keyword = (search.value || '').toLowerCase().trim();
        const statusValue = status.value;

        return rows.filter(function (row) {
            const textMatch = keyword === '' || row.innerText.toLowerCase().includes(keyword);
            const statusMatch = statusValue === 'all' || row.dataset.status === statusValue;
            return textMatch && statusMatch;
        });
    }

    function render() {
        const matches = matchedRows();
        const limit = parseInt(perPage.value, 10) || 10;
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
            ? 'Showing 0 of ' + rows.length + ' users'
            : 'Showing ' + (start + 1) + '-' + Math.min(end, matches.length) + ' of ' + matches.length + ' users';
    }

    [search, status, perPage].forEach(function (input) {
        input.addEventListener('input', function () { page = 1; render(); });
        input.addEventListener('change', function () { page = 1; render(); });
    });

    reset.addEventListener('click', function () {
        search.value = '';
        status.value = 'all';
        perPage.value = '10';
        page = 1;
        render();
    });

    prev.addEventListener('click', function () { if (page > 1) { page--; render(); } });
    next.addEventListener('click', function () { page++; render(); });

    render();
});
</script>
<?= $this->endSection() ?>

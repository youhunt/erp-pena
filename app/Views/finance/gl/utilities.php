<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="card"><div class="card-body"><p class="text-muted mb-1">Modern COA</p><h3><?= esc((string) $modernCoaCount) ?></h3><span class="badge bg-success">chart_accounts</span></div></div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card"><div class="card-body"><p class="text-muted mb-1">Modern GL Books</p><h3><?= esc((string) $modernBookCount) ?></h3><span class="badge bg-success">gl_books</span></div></div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card"><div class="card-body"><p class="text-muted mb-1">Legacy COA Source</p><h3><?= esc((string) $legacyCoaCount) ?></h3><span class="badge bg-info">coa</span></div></div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card"><div class="card-body"><p class="text-muted mb-1">Legacy GL Book Source</p><h3><?= esc((string) $legacyBookCount) ?></h3><span class="badge bg-info">glbook</span></div></div>
    </div>
</div>

<div class="row">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Initialize GL Defaults</h4>
                <p class="text-muted">Create default GL Book, Chart of Account, and Posting Profiles for active/demo company.</p>
                <form method="post" action="<?= site_url('gl/utilities/init-defaults') ?>" onsubmit="return confirm('Initialize Finance GL default data?')">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary w-100" type="submit"><i class="bx bx-data me-1"></i> Initialize Defaults</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Sync Legacy COA</h4>
                <p class="text-muted">Map source table <code>coa</code> into modern <code>chart_accounts</code>.</p>
                <form method="post" action="<?= site_url('gl/utilities/sync-legacy-coa') ?>" onsubmit="return confirm('Sync legacy coa to chart_accounts?')">
                    <?= csrf_field() ?>
                    <button class="btn btn-success w-100" type="submit" <?= $hasCoa ? '' : 'disabled' ?>><i class="bx bx-transfer me-1"></i> Sync COA</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-1">Sync Legacy GL Book</h4>
                <p class="text-muted">Map source table <code>glbook</code> into modern <code>gl_books</code>.</p>
                <form method="post" action="<?= site_url('gl/utilities/sync-legacy-books') ?>" onsubmit="return confirm('Sync legacy glbook to gl_books?')">
                    <?= csrf_field() ?>
                    <button class="btn btn-success w-100" type="submit" <?= $hasGlBook ? '' : 'disabled' ?>><i class="bx bx-transfer me-1"></i> Sync GL Book</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h4 class="card-title mb-3">Recommended GL Setup Flow</h4>
        <div class="table-responsive">
            <table class="table table-nowrap align-middle mb-0">
                <thead class="table-light"><tr><th>Step</th><th>Action</th><th>Menu</th></tr></thead>
                <tbody>
                    <tr><td>1</td><td>Run database migration.</td><td><code>php spark migrate</code></td></tr>
                    <tr><td>2</td><td>Initialize default COA, GL Book, and Posting Profile.</td><td>GL &gt; GL Utilities</td></tr>
                    <tr><td>3</td><td>Import legacy/source GL files through Excel Import Export when available.</td><td>System &gt; Excel Import Export</td></tr>
                    <tr><td>4</td><td>Sync legacy COA/GL Book into modern ERP tables.</td><td>GL &gt; GL Utilities</td></tr>
                    <tr><td>5</td><td>Review posting profile and manual journal posting.</td><td>GL &gt; Posting Profile / GL Entry</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

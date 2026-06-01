<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start">
                    <div class="avatar-sm me-3">
                        <span class="avatar-title rounded-circle bg-primary-subtle text-primary font-size-24">
                            <i class="bx bx-grid-alt"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="card-title mb-2"><?= esc($title) ?></h4>
                        <p class="text-muted mb-0">
                            Menu ini sudah aktif. Form, tabel, approval, dan proses bisnis detail akan dilanjutkan bertahap sesuai prioritas modul.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Status</h4>
                <span class="badge bg-warning-subtle text-warning font-size-12">Module Placeholder</span>
                <p class="text-muted mt-3 mb-0">
                    Route: <span class="fw-semibold"><?= esc($slug) ?></span>
                </p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

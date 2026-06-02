<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">OCR Diagnostics</h4>
                <p class="text-muted mb-0">Check whether local OCR dependencies are callable from PHP/XAMPP.</p>
            </div>
            <a href="<?= site_url('ai-documents') ?>" class="btn btn-light">
                <i class="bx bx-arrow-back me-1"></i> Back to Documents
            </a>
        </div>

        <div class="row">
            <?php foreach ($checks as $key => $check): ?>
                <div class="col-xl-4 col-md-6">
                    <div class="card border <?= ! empty($check['ok']) ? 'border-success' : 'border-danger' ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <h5 class="mb-0"><?= esc($check['label'] ?? $key) ?></h5>
                                <span class="badge bg-<?= ! empty($check['ok']) ? 'success' : 'danger' ?>">
                                    <?= ! empty($check['ok']) ? 'OK' : 'FAILED' ?>
                                </span>
                            </div>
                            <p class="text-muted mb-2"><?= esc($check['message'] ?? '-') ?></p>
                            <?php if (! empty($check['command'])): ?>
                                <div class="small text-muted mb-2">Command: <code><?= esc($check['command']) ?></code></div>
                            <?php endif ?>
                            <?php if (! empty($check['output'])): ?>
                                <pre class="bg-light border rounded p-2 mb-0" style="white-space: pre-wrap; max-height: 160px; overflow:auto;"><code><?= esc(implode("\n", $check['output'])) ?></code></pre>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>

        <div class="alert alert-info mb-0">
            <strong>Windows/XAMPP note:</strong> if Tesseract or Poppler works in CMD but fails here, restart Apache/XAMPP after updating PATH, or set full executable paths in <code>app/Config/AiOcr.php</code>.
        </div>
    </div>
</div>
<?= $this->endSection() ?>

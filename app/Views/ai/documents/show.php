<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$status = $document['status'] ?? 'uploaded';
$badge = match ($status) {
    'duplicate' => 'warning',
    'extraction_completed', 'processed' => 'success',
    'failed' => 'danger',
    default => 'info',
};
$canProcess = in_array($status, ['uploaded', 'ocr_completed', 'failed'], true) && (($document['duplicate_of_id'] ?? null) === null);
?>
<div class="row">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                    <div>
                        <h4 class="card-title mb-1">Document Detail</h4>
                        <p class="text-muted mb-0">Uploaded ERP document metadata.</p>
                    </div>
                    <span class="badge bg-<?= esc($badge) ?>"><?= esc($status) ?></span>
                </div>

                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th>ID</th><td><?= esc($document['id'] ?? '-') ?></td></tr>
                        <tr><th>Original File</th><td><?= esc($document['original_name'] ?? '-') ?></td></tr>
                        <tr><th>Document Type</th><td><?= esc($document['document_type'] ?? '-') ?></td></tr>
                        <tr><th>MIME Type</th><td><?= esc($document['mime_type'] ?? '-') ?></td></tr>
                        <tr><th>File Size</th><td><?= esc(number_format(((int) ($document['file_size'] ?? 0)) / 1024, 2)) ?> KB</td></tr>
                        <tr><th>Uploaded By</th><td><?= esc($document['uploaded_by'] ?? '-') ?></td></tr>
                        <tr><th>Uploaded At</th><td><?= esc($document['created_at'] ?? '-') ?></td></tr>
                        <tr><th>Company</th><td><?= esc($document['company_id'] ?? '-') ?></td></tr>
                        <tr><th>Site</th><td><?= esc($document['site_id'] ?? '-') ?></td></tr>
                        <tr><th>Duplicate Of</th><td><?= esc($document['duplicate_of_id'] ?? '-') ?></td></tr>
                    </tbody>
                </table>

                <div class="mt-3 d-flex flex-wrap gap-2">
                    <a href="<?= site_url('ai-documents') ?>" class="btn btn-light">
                        <i class="bx bx-arrow-back me-1"></i> Back
                    </a>

                    <?php if ($canProcess): ?>
                        <form method="post" action="<?= site_url('ai-documents/' . $document['id'] . '/process') ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Process this document with OCR/AI now?')">
                                <i class="bx bx-cog me-1"></i> Process OCR/AI
                            </button>
                        </form>
                    <?php endif ?>

                    <?php if (($document['duplicate_of_id'] ?? null) !== null): ?>
                        <a href="<?= site_url('ai-documents/' . $document['duplicate_of_id']) ?>" class="btn btn-outline-warning">
                            View Original
                        </a>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Processing Status</h4>
                <?php if ($status === 'duplicate'): ?>
                    <div class="alert alert-warning mb-0">
                        This document is a duplicate. Open the original document for processing/review.
                    </div>
                <?php elseif ($status === 'extraction_completed'): ?>
                    <div class="alert alert-success mb-0">
                        OCR and AI extraction completed. Human review screen will be added in the next phase.
                    </div>
                <?php elseif ($status === 'failed'): ?>
                    <div class="alert alert-danger mb-0">
                        Processing failed. You can retry using the Process OCR/AI button.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        This document is stored safely and ready for manual OCR/AI processing.
                    </div>
                <?php endif ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">SHA256 Hash</h4>
                <pre class="bg-light border rounded p-3 mb-0" style="white-space: pre-wrap;"><code><?= esc($document['sha256_hash'] ?? '-') ?></code></pre>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Storage Note</h4>
                <p class="text-muted mb-0">
                    The file is stored under the server writeable secure upload directory and is not exposed directly as a public asset.
                </p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

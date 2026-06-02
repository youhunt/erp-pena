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
$fields = ! empty($extraction['extracted_fields']) ? json_decode($extraction['extracted_fields'], true) : null;
$lineItems = ! empty($extraction['line_items']) ? json_decode($extraction['line_items'], true) : null;
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
                    <a href="<?= site_url('ai-documents') ?>" class="btn btn-light"><i class="bx bx-arrow-back me-1"></i> Back</a>

                    <?php if ($canProcess): ?>
                        <form method="post" action="<?= site_url('ai-documents/' . $document['id'] . '/process') ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary" onclick="return confirm('Process this document with OCR/AI now?')">
                                <i class="bx bx-cog me-1"></i> Process OCR/AI
                            </button>
                        </form>
                    <?php endif ?>

                    <?php if (($document['duplicate_of_id'] ?? null) !== null): ?>
                        <a href="<?= site_url('ai-documents/' . $document['duplicate_of_id']) ?>" class="btn btn-outline-warning">View Original</a>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Processing Logs</h4>
                <?php if (! empty($processingLogs)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Time</th><th>Step</th><th>Status</th><th>Message</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($processingLogs as $log): ?>
                                <tr>
                                    <td class="text-muted small"><?= esc($log['created_at'] ?? '-') ?></td>
                                    <td><?= esc($log['step'] ?? '-') ?></td>
                                    <td><span class="badge bg-secondary"><?= esc($log['status'] ?? '-') ?></span></td>
                                    <td><?= esc($log['message'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No processing logs yet.</p>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">Processing Status</h4>
                <?php if ($status === 'duplicate'): ?>
                    <div class="alert alert-warning mb-0">This document is a duplicate. Open the original document for processing/review.</div>
                <?php elseif ($status === 'extraction_completed'): ?>
                    <div class="alert alert-success mb-0">OCR and AI extraction completed. Human review screen will be added in the next phase.</div>
                <?php elseif ($status === 'failed'): ?>
                    <div class="alert alert-danger mb-0">Processing failed. You can retry using the Process OCR/AI button.</div>
                <?php else: ?>
                    <div class="alert alert-info mb-0">This document is stored safely and ready for manual OCR/AI processing.</div>
                <?php endif ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">OCR Result</h4>
                <?php if (! empty($ocrResult)): ?>
                    <div class="mb-2 text-muted small">
                        Provider: <strong><?= esc($ocrResult['provider'] ?? '-') ?></strong> | Confidence: <strong><?= esc($ocrResult['confidence_score'] ?? '0') ?></strong>
                    </div>
                    <pre class="bg-light border rounded p-3 mb-0" style="white-space: pre-wrap; max-height: 280px; overflow:auto;"><code><?= esc($ocrResult['ocr_text'] ?? '') ?></code></pre>
                <?php else: ?>
                    <p class="text-muted mb-0">No OCR result yet.</p>
                <?php endif ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">AI Extraction Result</h4>
                <?php if (! empty($extraction)): ?>
                    <div class="mb-2 text-muted small">
                        Provider: <strong><?= esc($extraction['provider'] ?? '-') ?></strong> |
                        Type: <strong><?= esc($extraction['document_type'] ?? '-') ?></strong> |
                        Confidence: <strong><?= esc($extraction['confidence_score'] ?? '0') ?></strong> |
                        Review: <strong><?= esc($extraction['review_status'] ?? '-') ?></strong>
                    </div>
                    <h6>Fields</h6>
                    <pre class="bg-light border rounded p-3" style="white-space: pre-wrap; max-height: 220px; overflow:auto;"><code><?= esc(json_encode($fields, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                    <h6>Line Items</h6>
                    <pre class="bg-light border rounded p-3 mb-0" style="white-space: pre-wrap; max-height: 220px; overflow:auto;"><code><?= esc(json_encode($lineItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                <?php else: ?>
                    <p class="text-muted mb-0">No AI extraction result yet.</p>
                <?php endif ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-3">SHA256 Hash</h4>
                <pre class="bg-light border rounded p-3 mb-0" style="white-space: pre-wrap;"><code><?= esc($document['sha256_hash'] ?? '-') ?></code></pre>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

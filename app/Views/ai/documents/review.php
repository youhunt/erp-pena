<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<form method="post" action="<?= site_url('ai-documents/' . $document['id'] . '/review') ?>">
    <?= csrf_field() ?>

    <div class="row">
        <div class="col-xl-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Document</h4>
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th>ID</th><td><?= esc($document['id'] ?? '-') ?></td></tr>
                            <tr><th>File</th><td><?= esc($document['original_name'] ?? '-') ?></td></tr>
                            <tr><th>Status</th><td><?= esc($document['status'] ?? '-') ?></td></tr>
                            <tr><th>Type</th><td><?= esc($extraction['document_type'] ?? '-') ?></td></tr>
                            <tr><th>Confidence</th><td><?= esc($extraction['confidence_score'] ?? '0') ?></td></tr>
                            <tr><th>Review</th><td><?= esc($extraction['review_status'] ?? '-') ?></td></tr>
                        </tbody>
                    </table>

                    <div class="mt-3">
                        <label class="form-label">Review Status</label>
                        <select class="form-select" name="review_status">
                            <option value="pending_review" <?= ($extraction['review_status'] ?? '') === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="reviewed" <?= ($extraction['review_status'] ?? '') === 'reviewed' ? 'selected' : '' ?>>Reviewed / Ready</option>
                        </select>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">
                            <i class="bx bx-save me-1"></i> Save Review
                        </button>
                        <a href="<?= site_url('ai-documents/' . $document['id']) ?>" class="btn btn-light">Cancel</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Extracted Fields JSON</h4>
                    <textarea name="extracted_fields" class="form-control font-monospace" rows="14" spellcheck="false"><?= esc(old('extracted_fields', $fieldsJson)) ?></textarea>
                    <div class="form-text">Edit header fields such as document number, date, customer/supplier, tax, total, and notes.</div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Line Items JSON</h4>
                    <textarea name="line_items" class="form-control font-monospace" rows="14" spellcheck="false"><?= esc(old('line_items', $lineItemsJson)) ?></textarea>
                    <div class="form-text">Edit item rows such as item code/name, qty, UoM, price, discount, tax, and subtotal.</div>
                </div>
            </div>
        </div>
    </div>
</form>
<?= $this->endSection() ?>

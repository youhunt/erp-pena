<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="card-title mb-1">OCR Sample Documents</h4>
                <p class="text-muted mb-0">Open these samples, print to PDF, or screenshot them for OCR upload testing.</p>
            </div>
            <a href="<?= site_url('ai-documents/upload') ?>" class="btn btn-primary">
                <i class="bx bx-upload me-1"></i> Upload Document
            </a>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card border">
                    <div class="card-body">
                        <h5>Purchase Order Sample</h5>
                        <p class="text-muted">Use this sample to test OCR extraction into Purchase Order fields.</p>
                        <a href="<?= site_url('ai-ocr/samples/po') ?>" target="_blank" class="btn btn-outline-primary">
                            Open PO Sample
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border">
                    <div class="card-body">
                        <h5>Sales Order Sample</h5>
                        <p class="text-muted">Use this sample to test OCR extraction into Sales Order fields.</p>
                        <a href="<?= site_url('ai-ocr/samples/so') ?>" target="_blank" class="btn btn-outline-primary">
                            Open SO Sample
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info mb-0">
            Tip: open a sample, press <strong>Ctrl+P</strong>, choose <strong>Save as PDF</strong>, then upload the PDF or take a screenshot and upload the image.
        </div>
    </div>
</div>
<?= $this->endSection() ?>

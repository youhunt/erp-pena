<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">Upload ERP Document</h4>

                <form action="<?= site_url('ai-documents/upload') ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="document">ERP document</label>
                        <input class="form-control" id="document" name="document" type="file" accept="application/pdf,image/*" required>
                    </div>

                    <button class="btn btn-primary waves-effect waves-light" type="submit">
                        <i class="bx bx-upload me-1"></i> Upload
                    </button>
                    <a class="btn btn-light ms-2" href="<?= site_url('ai-documents') ?>">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

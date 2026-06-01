<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="panel">
    <form action="<?= site_url('ai-documents/upload') ?>" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <p>
            <label for="document">ERP document</label><br>
            <input id="document" name="document" type="file" accept="application/pdf,image/*" required>
        </p>

        <button class="button" type="submit">Upload</button>
    </form>
</div>
<?= $this->endSection() ?>

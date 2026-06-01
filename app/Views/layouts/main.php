<?= $this->include('partials/header') ?>

<div id="layout-wrapper">
    <?= $this->include('partials/topbar') ?>
    <?= $this->include('partials/sidebar') ?>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                            <h4 class="mb-sm-0 font-size-18"><?= esc($title ?? 'Dashboard') ?></h4>
                            <div class="page-title-right">
                                <ol class="breadcrumb m-0">
                                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">PENA ERP</a></li>
                                    <li class="breadcrumb-item active"><?= esc($title ?? 'Dashboard') ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

            <?php if (session('message')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= esc(session('message')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif ?>

            <?php if (session('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= esc(session('error')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif ?>

            <?= $this->renderSection('content') ?>
            </div>
        </div>

        <?= $this->include('partials/footer') ?>
    </div>
</div>

</body>
</html>

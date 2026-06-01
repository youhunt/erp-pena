<?= $this->include('partials/header') ?>

<div class="app-shell">
    <?= $this->include('partials/sidebar') ?>

    <main class="main-area">
        <?= $this->include('partials/topbar') ?>

        <section class="content">
            <?php if (session('message')): ?>
                <div class="alert alert-success"><?= esc(session('message')) ?></div>
            <?php endif ?>

            <?php if (session('error')): ?>
                <div class="alert alert-error"><?= esc(session('error')) ?></div>
            <?php endif ?>

            <?= $this->renderSection('content') ?>
        </section>

        <?= $this->include('partials/footer') ?>
    </main>
</div>

</body>
</html>

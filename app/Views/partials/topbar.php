<header class="topbar">
    <div>
        <strong><?= esc($title ?? 'Dashboard') ?></strong>
    </div>
    <div>
        <?= esc(auth()->user()?->username ?? 'User') ?>
        <a href="<?= site_url('logout') ?>">Logout</a>
    </div>
</header>

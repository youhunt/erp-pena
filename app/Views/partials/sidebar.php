<?php
use App\Services\MenuService;

$items = (new MenuService())->visibleMenuItems();
$current = trim(uri_string(), '/');
?>

<aside class="side-nav">
    <div class="brand">PENA ERP</div>

    <nav>
        <?php foreach ($items as $item): ?>
            <?php $route = trim((string) ($item['route'] ?? '#'), '/'); ?>
            <a class="menu-link <?= $current === $route ? 'is-active' : '' ?>" href="<?= $route === '#' ? '#' : site_url($route) ?>">
                <span><?= esc($item['icon'] ?: 'bx-circle') ?></span>
                <span><?= esc($item['label']) ?></span>
            </a>
        <?php endforeach ?>
    </nav>
</aside>

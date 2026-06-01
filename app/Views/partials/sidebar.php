<?php
use App\Services\MenuService;

$items = (new MenuService())->visibleMenuItems();
$current = trim(uri_string(), '/');
?>

<div class="vertical-menu">
    <div data-simplebar class="h-100">
        <div id="sidebar-menu">
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title">Menu</li>

                <?php foreach ($items as $item): ?>
                    <?php
                    $route = trim((string) ($item['route'] ?? '#'), '/');
                    $isActive = $route !== '#' && ($current === $route || str_starts_with($current, $route . '/'));
                    ?>
                    <li class="<?= $isActive ? 'mm-active' : '' ?>">
                        <a class="waves-effect <?= $isActive ? 'active' : '' ?>" href="<?= $route === '#' ? 'javascript:void(0);' : site_url($route) ?>">
                            <i class="bx <?= esc($item['icon'] ?: 'bx-circle') ?>"></i>
                            <span><?= esc($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
</div>

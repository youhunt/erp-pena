<?php
use App\Services\MenuService;

$items = (new MenuService())->visibleMenuTree();
$current = trim(uri_string(), '/');

$isActiveRoute = static function (?string $route) use ($current): bool {
    $route = trim((string) $route, '/');

    return $route !== '' && $route !== '#' && ($current === $route || str_starts_with($current, $route . '/'));
};

$hasActiveChild = static function (array $item) use (&$hasActiveChild, $isActiveRoute): bool {
    if ($isActiveRoute($item['route'] ?? null)) {
        return true;
    }

    foreach ($item['children'] ?? [] as $child) {
        if ($hasActiveChild($child)) {
            return true;
        }
    }

    return false;
};

$renderItems = static function (array $nodes, int $level = 0) use (&$renderItems, $hasActiveChild, $isActiveRoute): void {
    foreach ($nodes as $item) {
        $children = $item['children'] ?? [];
        $hasChildren = $children !== [];
        $route = trim((string) ($item['route'] ?? ''), '/');
        $active = $hasActiveChild($item);
        $isSection = $level > 0 && $hasChildren && ($route === '' || $route === '#') && empty($item['icon']);
        $href = $route === '' || $route === '#' ? 'javascript:void(0);' : site_url($route);
        $linkClass = $hasChildren && ! $isSection ? 'has-arrow waves-effect' : 'waves-effect';
        $linkClass .= $isActiveRoute($route) ? ' active' : '';
        ?>
        <li class="<?= trim(($active ? 'mm-active ' : '') . ($isSection ? 'pena-menu-section' : '')) ?>">
            <a href="<?= $isSection ? 'javascript:void(0);' : $href ?>" class="<?= esc($linkClass) ?>">
                <?php if ($level === 0): ?>
                    <i class="bx <?= esc($item['icon'] ?: 'bx-circle') ?>"></i>
                <?php endif ?>
                <span><?= esc($item['label']) ?></span>
            </a>

            <?php if ($hasChildren): ?>
                <ul class="sub-menu <?= ($active || $isSection) ? 'mm-show' : '' ?>" aria-expanded="<?= ($active || $isSection) ? 'true' : 'false' ?>">
                    <?php $renderItems($children, $level + 1); ?>
                </ul>
            <?php endif ?>
        </li>
        <?php
    }
};
?>

<div class="vertical-menu">
    <div data-simplebar class="h-100">
        <div id="sidebar-menu">
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title">ERP Menu</li>
                <?php $renderItems($items); ?>
            </ul>
        </div>
    </div>
</div>

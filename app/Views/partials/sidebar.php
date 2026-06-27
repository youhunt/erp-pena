<?php
use App\Services\MenuService;

$items = (new MenuService())->visibleMenuTree();
$current = trim(uri_string(), '/');

$isActiveRoute = static function (?string $route) use ($current): bool {
    $route = trim((string) $route, '/');

    if ($route === '' || $route === '#') {
        return false;
    }

    // Exact match only. Prefix matching made sibling menus light up together,
    // for example production/work-orders also activating allocation/in/out menus.
    return $current === $route;
};

$defaultIcon = static function (array $item): string {
    $label = strtolower(trim((string) ($item['label'] ?? '')));
    $route = strtolower(trim((string) ($item['route'] ?? '')));
    $key = $label . ' ' . $route;

    return match (true) {
        str_contains($key, 'dashboard') => 'bx-home-circle',
        str_contains($key, 'setup') || str_contains($key, 'master') => 'bx-slider-alt',
        str_contains($key, 'purchase') => 'bx-cart',
        str_contains($key, 'sales') => 'bx-store',
        str_contains($key, 'inventory') => 'bx-package',
        str_contains($key, 'production') => 'bx-cog',
        str_contains($key, 'manufact') => 'bx-cog',
        str_contains($key, 'work order') => 'bx-task',
        str_contains($key, 'finance') => 'bx-wallet',
        str_contains($key, 'cash') || str_contains($key, 'bank') => 'bx-credit-card',
        str_contains($key, 'gl') || str_contains($key, 'ledger') => 'bx-book-content',
        str_contains($key, 'ap') || str_contains($key, 'payable') => 'bx-receipt',
        str_contains($key, 'ar') || str_contains($key, 'receivable') => 'bx-money',
        str_contains($key, 'report') => 'bx-bar-chart-alt-2',
        str_contains($key, 'system') || str_contains($key, 'admin') => 'bx-shield-quarter',
        default => 'bx-circle',
    };
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

$renderItems = static function (array $nodes, int $level = 0) use (&$renderItems, $hasActiveChild, $isActiveRoute, $defaultIcon): void {
    foreach ($nodes as $item) {
        $children = $item['children'] ?? [];
        $hasChildren = $children !== [];
        $route = trim((string) ($item['route'] ?? ''), '/');
        $active = $hasActiveChild($item);
        $expanded = $active || ($level > 0 && $hasChildren && ($route === '' || $route === '#') && empty($item['icon']));
        $isSection = $level > 0 && $hasChildren && ($route === '' || $route === '#') && empty($item['icon']);
        $href = $route === '' || $route === '#' ? 'javascript:void(0);' : site_url($route);
        $linkClass = $hasChildren && ! $isSection ? 'has-arrow waves-effect' : 'waves-effect';
        $linkClass .= $isActiveRoute($route) ? ' active' : '';
        $icon = trim((string) ($item['icon'] ?? '')) !== '' ? (string) $item['icon'] : $defaultIcon($item);
        ?>
        <li class="<?= trim(($active ? 'mm-active ' : '') . ($isSection ? 'pena-menu-section' : '')) ?>">
            <a href="<?= $isSection ? 'javascript:void(0);' : $href ?>" class="<?= esc($linkClass) ?>">
                <?php if ($level === 0): ?>
                    <i class="bx <?= esc($icon) ?>"></i>
                <?php endif ?>
                <span><?= esc($item['label']) ?></span>
            </a>

            <?php if ($hasChildren): ?>
                <ul class="sub-menu <?= $expanded ? 'mm-show' : '' ?>" aria-expanded="<?= $expanded ? 'true' : 'false' ?>">
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

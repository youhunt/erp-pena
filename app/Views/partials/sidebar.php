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
        $href = $route === '' || $route === '#' ? 'javascript:void(0);' : site_url($route);
        $linkClass = $hasChildren ? 'has-arrow waves-effect' : 'waves-effect';
        $linkClass .= $isActiveRoute($route) ? ' active' : '';
        ?>
        <li class="<?= $active ? 'mm-active' : '' ?>">
            <a href="<?= $href ?>" class="<?= esc($linkClass) ?>">
                <?php if ($level === 0): ?>
                    <i class="bx <?= esc($item['icon'] ?: 'bx-circle') ?>"></i>
                <?php elseif (! $hasChildren): ?>
                    <i class="bx bx-radio-circle font-size-10 me-1"></i>
                <?php endif ?>
                <span><?= esc($item['label']) ?></span>
            </a>

            <?php if ($hasChildren): ?>
                <ul class="sub-menu <?= $active ? 'mm-show' : '' ?>" aria-expanded="<?= $active ? 'true' : 'false' ?>">
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

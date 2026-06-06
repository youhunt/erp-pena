<?php

namespace Config;

use App\Filters\TenantBootstrapFilter;
use App\Filters\PermissionGuardFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'tenant'        => TenantBootstrapFilter::class,
        'permission'    => PermissionGuardFilter::class,
    ];

    public array $required = [
        'before' => [
            'forcehttps',
            'pagecache',
        ],
        'after' => [
            'pagecache',
            'performance',
            'toolbar',
        ],
    ];

    public array $globals = [
        'before' => [],
        'after' => [],
    ];

    public array $methods = [];

    public array $filters = [
        'tenant' => [
            'before' => [
                'dashboard',
                'tenant/*',
                'modules/*',
                'setup/*',
                'admin/*',
                'sales/*',
                'purchase/*',
                'inventory/*',
                'production/*',
                'ap/*',
                'ar/*',
                'audit-logs',
                'audit-logs/*',
                'ai-documents',
                'ai-documents/*',
                'ai-ocr/*',
            ],
        ],
        'permission' => [
            'before' => [
                'dashboard',
                'modules/*',
                'setup/*',
                'admin/*',
                'sales/*',
                'purchase/*',
                'inventory/*',
                'production/*',
                'ap/*',
                'ar/*',
                'audit-logs',
                'audit-logs/*',
                'ai-documents',
                'ai-documents/*',
                'ai-ocr/*',
            ],
        ],
    ];
}

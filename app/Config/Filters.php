<?php

namespace Config;

use App\Filters\TenantBootstrapFilter;
use App\Filters\PermissionGuardFilter;
use App\Filters\SetupMasterTenantGuardFilter;
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
        'setupTenant'   => SetupMasterTenantGuardFilter::class,
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
                'system/*',
                'setup/*',
                'admin/*',
                'sales/*',
                'purchase/*',
                'inventory/*',
                'production/*',
                'ap/*',
                'ar/*',
                'gl/*',
                'cash-bank/*',
                'period-close',
                'period-close/*',
                'audit-logs',
                'audit-logs/*',
                'ai-documents',
                'ai-documents/*',
                'ai-ocr/*',
            ],
        ],
        'setupTenant' => [
            'before' => [
                'setup/*',
            ],
            'after' => [
                'setup/*',
            ],
        ],
        'permission' => [
            'before' => [
                'dashboard',
                'modules/*',
                'system/*',
                'setup/*',
                'admin/*',
                'sales/*',
                'purchase/*',
                'inventory/*',
                'production/*',
                'ap/*',
                'ar/*',
                'gl/*',
                'cash-bank/*',
                'period-close',
                'period-close/*',
                'audit-logs',
                'audit-logs/*',
                'ai-documents',
                'ai-documents/*',
                'ai-ocr/*',
            ],
        ],
    ];
}

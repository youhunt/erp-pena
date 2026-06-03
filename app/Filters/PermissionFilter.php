<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Enforces CodeIgniter Shield permissions at route level.
 *
 * Usage in Routes.php:
 *
 *     ['filter' => 'permission:dashboard.view']
 *     ['filter' => 'permission:users.view,users.manage']
 *
 * Multiple permissions are treated as OR access. A user only needs one of the
 * provided permissions. Shield wildcard permissions, for example `users.*` or
 * `*`, are handled by Shield through `$user->can()`.
 */
final class PermissionFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): RedirectResponse|ResponseInterface|null
    {
        $auth = auth();

        if (! $auth->loggedIn()) {
            return redirect()->to(url_to('login'));
        }

        $permissions = $this->normalizePermissions($arguments);

        if ($permissions === []) {
            return null;
        }

        $user = $auth->user();

        foreach ($permissions as $permission) {
            if ($user !== null && $user->can($permission)) {
                return null;
            }
        }

        if ($request->isAJAX()) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 403,
                    'error'   => 'Forbidden',
                    'message' => 'You do not have permission to access this resource.',
                ]);
        }

        return service('response')
            ->setStatusCode(403)
            ->setBody('403 Forbidden: You do not have permission to access this resource.');
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
        // No after-response action required.
    }

    /**
     * @param list<string>|null $arguments
     * @return list<string>
     */
    private function normalizePermissions(?array $arguments): array
    {
        if ($arguments === null || $arguments === []) {
            return [];
        }

        $permissions = [];

        foreach ($arguments as $argument) {
            foreach (explode(',', (string) $argument) as $permission) {
                $permission = trim($permission);

                if ($permission !== '') {
                    $permissions[] = $permission;
                }
            }
        }

        return array_values(array_unique($permissions));
    }
}

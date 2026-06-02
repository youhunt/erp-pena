<?php

namespace App\Services;

use Config\Database;
use Throwable;

class AuditLogService
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(string $module, string $action, array $context = []): void
    {
        try {
            $tenant = new TenantContext(session());
            $request = service('request');

            Database::connect()->table('audit_logs')->insert([
                'company_id' => $context['company_id'] ?? $tenant->activeCompanyId(),
                'site_id' => $context['site_id'] ?? $tenant->activeSiteId(),
                'user_id' => $context['user_id'] ?? (function_exists('auth') ? auth()->id() : null),
                'module' => $module,
                'action' => $action,
                'table_name' => $context['table_name'] ?? null,
                'record_id' => isset($context['record_id']) ? (string) $context['record_id'] : null,
                'record_code' => isset($context['record_code']) ? (string) $context['record_code'] : null,
                'description' => $context['description'] ?? null,
                'old_values' => $this->json($context['old_values'] ?? null),
                'new_values' => $this->json($context['new_values'] ?? null),
                'ip_address' => method_exists($request, 'getIPAddress') ? $request->getIPAddress() : null,
                'user_agent' => substr((string) ($request->getUserAgent()?->getAgentString() ?? ''), 0, 500),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Audit logging must never break the business transaction.
        }
    }

    private function json(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }
}

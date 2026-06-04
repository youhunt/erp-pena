<?php

namespace App\Services\Workflow;

use App\Services\Support\TenantScope;
use Config\Database;
use RuntimeException;

final class ApprovalService
{
    public function request(string $documentType, int $documentId, ?string $documentNo = null, ?string $notes = null, ?int $userId = null): int
    {
        if ($documentType === '' || $documentId < 1) {
            throw new RuntimeException('Approval request requires document type and ID.');
        }

        $scope = new TenantScope();
        $db = Database::connect();

        $db->table('approval_requests')->insert([
            'company_id' => $scope->requireCompany(),
            'site_id' => $scope->optionalSite(),
            'document_type' => $documentType,
            'document_id' => $documentId,
            'document_no' => $documentNo,
            'requested_by' => $userId,
            'current_step' => 1,
            'status' => 'pending',
            'notes' => $notes,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    public function approve(int $approvalRequestId, ?string $notes = null, ?int $userId = null): void
    {
        $this->act($approvalRequestId, 'approved', $notes, $userId);
    }

    public function reject(int $approvalRequestId, ?string $notes = null, ?int $userId = null): void
    {
        $this->act($approvalRequestId, 'rejected', $notes, $userId);
    }

    private function act(int $approvalRequestId, string $action, ?string $notes, ?int $userId): void
    {
        if ($approvalRequestId < 1) {
            throw new RuntimeException('Approval request ID is required.');
        }

        $scope = new TenantScope();
        $companyId = $scope->requireCompany();
        $db = Database::connect();

        $request = $db->table('approval_requests')
            ->where('id', $approvalRequestId)
            ->where('company_id', $companyId)
            ->get()
            ->getRowArray();

        if ($request === null) {
            throw new RuntimeException('Approval request not found in active company.');
        }

        if (($request['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Only pending approval requests can be processed.');
        }

        $db->transStart();

        $db->table('approval_actions')->insert([
            'approval_request_id' => $approvalRequestId,
            'step_no' => (int) ($request['current_step'] ?? 1),
            'action' => $action,
            'acted_by' => $userId,
            'acted_at' => date('Y-m-d H:i:s'),
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $db->table('approval_requests')->where('id', $approvalRequestId)->update([
            'status' => $action,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new RuntimeException('Failed to process approval request.');
        }
    }
}

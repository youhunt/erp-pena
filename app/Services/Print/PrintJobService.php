<?php

namespace App\Services\Print;

use App\Services\Support\TenantScope;
use Config\Database;
use RuntimeException;

final class PrintJobService
{
    public function queue(string $documentType, int $documentId, ?string $documentNo = null, string $templateCode = 'default', ?int $userId = null): int
    {
        if ($documentType === '' || $documentId < 1) {
            throw new RuntimeException('Print job requires document type and ID.');
        }

        $scope = new TenantScope();
        $db = Database::connect();

        $db->table('print_jobs')->insert([
            'company_id' => $scope->requireCompany(),
            'site_id' => $scope->optionalSite(),
            'document_type' => $documentType,
            'document_id' => $documentId,
            'document_no' => $documentNo,
            'template_code' => $templateCode,
            'printed_by' => $userId,
            'printed_at' => null,
            'output_path' => null,
            'status' => 'queued',
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $db->insertID();
    }

    public function markPrinted(int $printJobId, ?string $outputPath = null, ?int $userId = null): void
    {
        if ($printJobId < 1) {
            throw new RuntimeException('Print job ID is required.');
        }

        Database::connect()->table('print_jobs')->where('id', $printJobId)->update([
            'printed_by' => $userId,
            'printed_at' => date('Y-m-d H:i:s'),
            'output_path' => $outputPath,
            'status' => 'printed',
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

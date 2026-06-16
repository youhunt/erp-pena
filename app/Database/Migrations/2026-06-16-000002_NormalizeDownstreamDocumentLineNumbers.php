<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeDownstreamDocumentLineNumbers extends Migration
{
    public function up(): void
    {
        $this->renumber('sales_delivery_lines', 'sales_delivery_id');
        $this->renumber('purchase_receipt_lines', 'purchase_receipt_id');
        $this->renumber('sales_invoice_lines', 'sales_invoice_id');
        $this->renumber('purchase_invoice_lines', 'purchase_invoice_id');
    }

    public function down(): void
    {
        // Display-only normalization cannot be safely reversed.
    }

    private function renumber(string $table, string $parentKey): void
    {
        if (! $this->db->tableExists($table) || ! $this->db->fieldExists('line_no', $table) || ! $this->db->fieldExists($parentKey, $table)) {
            return;
        }

        $rows = $this->db->table($table)
            ->select('id, ' . $parentKey . ', line_no')
            ->orderBy($parentKey, 'ASC')
            ->orderBy('line_no', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $currentParent = null;
        $lineNo = 0;

        foreach ($rows as $row) {
            if ((string) $currentParent !== (string) $row[$parentKey]) {
                $currentParent = $row[$parentKey];
                $lineNo = 1;
            } else {
                $lineNo++;
            }

            $this->db->table($table)
                ->where('id', (int) $row['id'])
                ->update(['line_no' => $lineNo]);
        }
    }
}

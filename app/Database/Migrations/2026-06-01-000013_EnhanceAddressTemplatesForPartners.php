<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceAddressTemplatesForPartners extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('addresses')) {
            $fields = [];

            if (! $this->db->fieldExists('contact_name', 'addresses')) {
                $fields['contact_name'] = ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'address_line2'];
            }

            if (! $this->db->fieldExists('mobile', 'addresses')) {
                $fields['mobile'] = ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'after' => 'phone'];
            }

            if ($fields !== []) {
                $this->forge->addColumn('addresses', $fields);
            }
        }

        $this->resizePartnerAddressFields('customers', [
            'officeaddre', 'officecity', 'officeprovir', 'officecount', 'officeposta', 'officeconta', 'officephon', 'officehp',
            'billingaddre', 'billingcity', 'billingprovi', 'billingcoun', 'billingposta', 'billingconta', 'billingphon', 'billinghp',
            'mailaddres', 'mailcity', 'mailprovin', 'mailcountr', 'mailpostal', 'mailcontac', 'mailphone', 'mailhp',
            'shiptoaddr', 'shiptocity', 'shiptoprovi', 'shiptocour', 'shiptopost', 'shiptocont', 'shiptophon', 'shiptohp',
        ]);

        $this->resizePartnerAddressFields('suppliers', [
            'officeaddre', 'officecity', 'officeprovir', 'officecoun', 'officeposta', 'officeconta', 'officephon', 'officehp',
            'mailaddres', 'mailcity', 'mailprovin', 'mailcountr', 'mailpostal', 'mailcontac', 'mailphone', 'mailhp',
            'billingadre', 'billingcity', 'billingprovi', 'billingcoun', 'billingposta', 'billingconta', 'billingphon', 'billinghp',
            'shiptoaddr', 'shiptocity', 'shiptoprovi', 'shiptocoun', 'shiptopost', 'shiptocont', 'shiptophon', 'shiptohp',
        ]);
    }

    public function down(): void
    {
        // No-op: keep safer address lengths and optional contact fields.
    }

    /**
     * @param list<string> $fields
     */
    private function resizePartnerAddressFields(string $table, array $fields): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $changes = [];
        foreach ($fields as $field) {
            if (! $this->db->fieldExists($field, $table)) {
                continue;
            }

            $changes[$field] = [
                'name' => $field,
                'type' => 'VARCHAR',
                'constraint' => $this->lengthFor($field),
                'null' => true,
            ];
        }

        if ($changes !== []) {
            $this->forge->modifyColumn($table, $changes);
        }
    }

    private function lengthFor(string $field): int
    {
        if (str_contains($field, 'phon') || str_contains($field, 'hp')) {
            return 50;
        }

        if (str_contains($field, 'posta') || str_contains($field, 'post')) {
            return 30;
        }

        if (str_contains($field, 'conta') || str_contains($field, 'contac') || str_contains($field, 'cont')) {
            return 150;
        }

        return 255;
    }
}

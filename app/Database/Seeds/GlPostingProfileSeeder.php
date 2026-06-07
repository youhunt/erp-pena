<?php

namespace App\Database\Seeds;

use App\Services\Finance\PostingProfileService;
use CodeIgniter\Database\Seeder;

class GlPostingProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->db->tableExists('gl_posting_profiles')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $companies = $this->db->table('companies')->where('is_active', 1)->get()->getResultArray();
        foreach ($companies as $company) {
            foreach (PostingProfileService::defaults() as $module => $profiles) {
                foreach ($profiles as $key => $accountNo) {
                    $this->upsert([
                        'company_id' => (int) $company['id'],
                        'module_code' => $module,
                        'posting_key' => $key,
                        'account_no' => $accountNo,
                        'description' => PostingProfileService::label($module, $key),
                        'is_active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    private function upsert(array $data): void
    {
        $where = [
            'company_id' => $data['company_id'],
            'module_code' => $data['module_code'],
            'posting_key' => $data['posting_key'],
        ];
        $row = $this->db->table('gl_posting_profiles')->where($where)->get()->getRowArray();
        if ($row !== null) {
            unset($data['created_at']);
            $this->db->table('gl_posting_profiles')->where('id', $row['id'])->update($data);
            return;
        }

        $this->db->table('gl_posting_profiles')->insert($data);
    }
}

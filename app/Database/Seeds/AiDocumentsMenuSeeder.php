<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AiDocumentsMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');
        $aiId = $this->menuItem(null, 'AI / OCR', '#', 'bx-brain', null, 80, $now);
        $this->menuItem($aiId, 'Documents', 'ai-documents', null, 'ai.document.upload', 10, $now);
        $this->menuItem($aiId, 'OCR Review Queue', 'ai-documents', null, 'ai.document.review', 20, $now);
    }

    private function menuItem(?int $parentId, string $label, string $route, ?string $icon, ?string $permission, int $sort, string $now): int
    {
        $row = $this->db->table('menu_items')
            ->where('parent_id', $parentId)
            ->where('label', $label)
            ->get()
            ->getRowArray();

        $data = [
            'parent_id' => $parentId,
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'permission' => $permission,
            'sort_order' => $sort,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        if ($row !== null) {
            $this->db->table('menu_items')->where('id', $row['id'])->update($data);

            return (int) $row['id'];
        }

        $this->db->table('menu_items')->insert($data + ['created_at' => $now]);

        return (int) $this->db->insertID();
    }
}

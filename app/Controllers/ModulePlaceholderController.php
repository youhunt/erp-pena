<?php

namespace App\Controllers;

class ModulePlaceholderController extends BaseController
{
    public function show(string $slug): string
    {
        $title = $this->titleFromSlug($slug);

        return view('modules/placeholder', [
            'title' => $title,
            'slug' => $slug,
        ]);
    }

    private function titleFromSlug(string $slug): string
    {
        $title = str_replace('-', ' ', trim($slug));

        return ucwords($title);
    }
}

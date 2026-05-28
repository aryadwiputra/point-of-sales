<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Models\Category;
use Illuminate\Support\Facades\Storage;

class DeleteCategoryService
{
    public function execute(Category $category): void
    {
        $image = $category->getRawOriginal('image');

        if ($image) {
            Storage::disk('local')->delete('public/category/'.basename($image));
        }

        $category->delete();
    }
}

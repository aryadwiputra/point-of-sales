<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Models\Category;

class CreateCategoryService
{
    public function execute(array $data): Category
    {
        $imageName = null;

        if (isset($data['image'])) {
            $image = $data['image'];
            $image->storeAs('public/category', $image->hashName());
            $imageName = $image->hashName();
        }

        return Category::create([
            'image' => $imageName,
            'name' => $data['name'],
            'description' => $data['description'],
        ]);
    }
}

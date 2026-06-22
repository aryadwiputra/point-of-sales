<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Models\Category;
use Illuminate\Support\Facades\Storage;

class UpdateCategoryService
{
    public function execute(Category $category, array $data): Category
    {
        $payload = [
            'name' => $data['name'],
            'description' => $data['description'],
        ];

        if (isset($data['image'])) {
            $this->deleteImage($category);

            $image = $data['image'];
            $image->storeAs('public/category', $image->hashName());
            $payload['image'] = $image->hashName();
        }

        $category->update($payload);

        return $category->fresh();
    }

    private function deleteImage(Category $category): void
    {
        $image = $category->getRawOriginal('image');

        if ($image) {
            Storage::disk('local')->delete('public/category/'.basename($image));
        }
    }
}

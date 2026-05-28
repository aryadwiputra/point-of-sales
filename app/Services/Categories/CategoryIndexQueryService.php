<?php

declare(strict_types=1);

namespace App\Services\Categories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryIndexQueryService
{
    public function execute(?string $search = null): LengthAwarePaginator
    {
        return Category::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->latest()
            ->paginate(2)
            ->withQueryString();
    }
}

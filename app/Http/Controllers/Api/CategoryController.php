<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Traits\ApiResponder;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    use ApiResponder;

    /**
     * GET /api/v1/categories
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->withCount('products')
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate($this->perPage());

        return $this->paginated($categories, CategoryResource::collection($categories));
    }

    /**
     * POST /api/v1/categories
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'image' => $validated['image'] ?? '',
        ]);

        return $this->created(new CategoryResource($category), 'Kategori berhasil dibuat');
    }

    /**
     * GET /api/v1/categories/{category}
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        return $this->ok(new CategoryResource($category->loadCount('products')));
    }

    /**
     * PUT/PATCH /api/v1/categories/{category}
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
        ]);

        $category->update($validated);

        return $this->ok(new CategoryResource($category), 'Kategori berhasil diperbarui');
    }

    /**
     * DELETE /api/v1/categories/{category}
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        $category->delete();

        return $this->noContent();
    }
}

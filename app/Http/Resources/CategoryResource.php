<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image
                ? (str_starts_with($this->image, 'http') ? $this->image : url('storage/'.ltrim($this->image, '/')))
                : null,
            'products_count' => $this->whenCounted('products'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserIndexQueryService
{
    public function execute(?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->select('id', 'name', 'avatar', 'email')
            ->latest()
            ->paginate(7)
            ->withQueryString();
    }
}

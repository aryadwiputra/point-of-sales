<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\Users\CreateUserService;
use App\Services\Users\DeleteUsersService;
use App\Services\Users\UpdateUserService;
use App\Services\Users\UserIndexQueryService;
use App\Services\Users\UserPayloadService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(IndexUserRequest $request, UserIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/Users/Index', [
            'users' => $service->execute($request->search()),
        ]);
    }

    public function create(UserPayloadService $service): Response
    {
        return Inertia::render('Dashboard/Users/Create', $service->createPayload());
    }

    public function store(StoreUserRequest $request, CreateUserService $service): RedirectResponse
    {
        $service->execute($request->validated(), $request->file('avatar'));

        return to_route('users.index');
    }

    public function edit(User $user, UserPayloadService $service): Response
    {
        return Inertia::render('Dashboard/Users/Edit', $service->editPayload($user));
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        UpdateUserService $service
    ): RedirectResponse {
        $service->execute($user, $request->validated(), $request->file('avatar'));

        return to_route('users.index');
    }

    public function destroy(int|string $id, DeleteUsersService $service): RedirectResponse
    {
        $service->execute($id);

        return back();
    }
}

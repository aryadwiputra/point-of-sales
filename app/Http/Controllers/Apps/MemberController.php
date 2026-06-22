<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\IndexMemberRequest;
use App\Http\Requests\Member\StoreMemberRequest;
use App\Http\Requests\Member\UpdateMemberRequest;
use App\Models\Customer;
use App\Services\Members\CreateMemberService;
use App\Services\Members\MemberIndexQueryService;
use App\Services\Members\MemberPayloadService;
use App\Services\Members\MemberShowQueryService;
use App\Services\Members\UpdateMemberService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(IndexMemberRequest $request, MemberIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/Members/Index', $service->execute($request->filters()));
    }

    public function create(MemberPayloadService $service): Response
    {
        return Inertia::render('Dashboard/Members/Create', $service->createPayload());
    }

    public function store(StoreMemberRequest $request, CreateMemberService $service): RedirectResponse
    {
        $service->execute($request->validated());

        return to_route('members.index');
    }

    public function show(Customer $member, MemberShowQueryService $service): Response
    {
        return Inertia::render('Dashboard/Members/Show', $service->execute($member));
    }

    public function edit(Customer $member, MemberPayloadService $service): Response
    {
        return Inertia::render('Dashboard/Members/Edit', $service->editPayload($member));
    }

    public function update(
        UpdateMemberRequest $request,
        Customer $member,
        UpdateMemberService $service
    ): RedirectResponse {
        $service->execute($member, $request->validated());

        return to_route('members.show', $member);
    }
}

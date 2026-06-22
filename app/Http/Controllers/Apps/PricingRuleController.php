<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\PricingRule\IndexPricingRuleRequest;
use App\Http\Requests\PricingRule\SavePricingRuleRequest;
use App\Models\PricingRule;
use App\Services\PricingRules\CreatePricingRuleService;
use App\Services\PricingRules\DeletePricingRuleService;
use App\Services\PricingRules\PreviewPricingRuleService;
use App\Services\PricingRules\PricingRuleIndexQueryService;
use App\Services\PricingRules\PricingRulePayloadService;
use App\Services\PricingRules\UpdatePricingRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PricingRuleController extends Controller
{
    public function index(IndexPricingRuleRequest $request, PricingRuleIndexQueryService $service): Response
    {
        return Inertia::render('Dashboard/PricingRules/Index', $service->execute($request->filters()));
    }

    public function create(PricingRulePayloadService $service): Response
    {
        return Inertia::render('Dashboard/PricingRules/Create', $service->formPayload());
    }

    public function store(SavePricingRuleRequest $request, CreatePricingRuleService $service): RedirectResponse
    {
        $service->execute($request->normalizedPayload(), $request->user()?->id);

        return redirect()
            ->route('pricing-rules.index')
            ->with('success', 'Rule promo berhasil dibuat.');
    }

    public function edit(PricingRule $pricingRule, PricingRulePayloadService $service): Response
    {
        return Inertia::render('Dashboard/PricingRules/Edit', [
            ...$service->formPayload(),
            'rule' => $service->editRulePayload($pricingRule),
        ]);
    }

    public function update(
        SavePricingRuleRequest $request,
        PricingRule $pricingRule,
        UpdatePricingRuleService $service
    ): RedirectResponse {
        $service->execute($pricingRule, $request->normalizedPayload());

        return redirect()
            ->route('pricing-rules.index')
            ->with('success', 'Rule promo berhasil diperbarui.');
    }

    public function destroy(PricingRule $pricingRule, DeletePricingRuleService $service): RedirectResponse
    {
        $service->execute($pricingRule);

        return back()->with('success', 'Rule promo berhasil dihapus.');
    }

    public function preview(SavePricingRuleRequest $request, PreviewPricingRuleService $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->execute(
                $request->normalizedPayload(),
                $request->filled('preview_customer_id') ? $request->integer('preview_customer_id') : null
            ),
        ]);
    }
}

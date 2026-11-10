<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\CrmCampaignController as AppsCrmCampaignController;
use App\Http\Traits\ApiResponder;
use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\Receivable;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmCampaignController extends AppsCrmCampaignController
{
    use ApiResponder;

    /**
     * GET /api/v1/crm-campaigns
     */
    public function listCampaigns(Request $request): JsonResponse
    {
        $outlet = $this->outletAccessService->activeOutlet($request);
        $campaigns = CustomerCampaign::query()
            ->where(function ($query) use ($outlet) {
                $query->whereNull('outlet_id');
                if ($outlet) {
                    $query->orWhere('outlet_id', $outlet->id);
                }
            })
            ->with(['creator:id,name'])
            ->withCount('logs')
            ->when($request->input('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($this->perPage())
            ->withQueryString();

        return $this->paginated($campaigns);
    }

    /**
     * GET /api/v1/crm-campaigns/create
     */
    public function createData(): JsonResponse
    {
        return $this->ok([
            'audience_options' => $this->crmAutomationService->audienceOptions(),
        ]);
    }

    /**
     * POST /api/v1/crm-campaigns
     */
    public function storeCampaign(Request $request): JsonResponse
    {
        $validated = $this->validateCampaign($request);
        $validated['outlet_id'] = $this->outletAccessService->activeOutlet($request)?->id;
        $campaign = $this->crmAutomationService->createCampaign($validated, $request->user()->id);

        if (! $request->boolean('save_as_draft')) {
            $campaign = $this->crmAutomationService->processCampaign($campaign);
        }

        return $this->created($campaign);
    }

    /**
     * GET /api/v1/crm-campaigns/{crmCampaign}
     */
    public function showCampaign(CustomerCampaign $crmCampaign): JsonResponse
    {
        abort_unless($this->canUseCampaign($crmCampaign), 404);

        $crmCampaign->load([
            'creator:id,name',
            'logs.customer:id,name,no_telp',
            'logs.transaction:id,invoice',
            'logs.receivable:id,invoice,due_date',
        ]);

        return $this->ok($crmCampaign);
    }

    /**
     * PUT /api/v1/crm-campaigns/{crmCampaign}
     */
    public function updateCampaign(Request $request, CustomerCampaign $crmCampaign): JsonResponse
    {
        abort_unless($this->canUseCampaign($crmCampaign), 404);

        $validated = $this->validateCampaign($request);
        $crmCampaign = $this->crmAutomationService->updateCampaign($crmCampaign, $validated);

        return $this->ok($crmCampaign);
    }

    /**
     * DELETE /api/v1/crm-campaigns/{crmCampaign}
     */
    public function destroyCampaign(CustomerCampaign $crmCampaign): JsonResponse
    {
        abort_unless($this->canUseCampaign($crmCampaign), 404);

        $crmCampaign->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/crm-campaigns/{crmCampaign}/process
     */
    public function processCampaign(CustomerCampaign $crmCampaign): JsonResponse
    {
        abort_unless($this->canUseCampaign($crmCampaign), 404);

        return $this->ok($this->crmAutomationService->processCampaign($crmCampaign));
    }

    /**
     * POST /api/v1/crm-campaigns/{crmCampaign}/cancel
     */
    public function cancelCampaign(CustomerCampaign $crmCampaign): JsonResponse
    {
        abort_unless($this->canUseCampaign($crmCampaign), 404);

        return $this->ok($this->crmAutomationService->cancelCampaign($crmCampaign));
    }

    /**
     * POST /api/v1/crm-campaign-logs/{log}/mark-sent
     */
    public function markLogSent(CustomerCampaignLog $log): JsonResponse
    {
        abort_unless($this->canUseCampaign($log->campaign), 404);

        $this->crmAutomationService->markLog($log, CustomerCampaignLog::STATUS_SENT);

        return $this->ok(['marked' => CustomerCampaignLog::STATUS_SENT]);
    }

    /**
     * POST /api/v1/crm-campaign-logs/{log}/skip
     */
    public function markLogSkipped(CustomerCampaignLog $log): JsonResponse
    {
        abort_unless($this->canUseCampaign($log->campaign), 404);

        $this->crmAutomationService->markLog($log, CustomerCampaignLog::STATUS_SKIPPED);

        return $this->ok(['marked' => CustomerCampaignLog::STATUS_SKIPPED]);
    }

    /**
     * POST /api/v1/crm-campaigns/share-transaction/{transaction}
     */
    public function shareTransaction(Transaction $transaction, Request $request): JsonResponse
    {
        abort_unless(
            $this->outletAccessService->canUseWarehouse($request->user(), $transaction->warehouse),
            404
        );

        $campaign = $this->crmAutomationService->createInvoiceShareCampaignForTransaction($transaction, $request->user()->id);

        return $this->created($campaign);
    }

    /**
     * POST /api/v1/crm-campaigns/share-receivable/{receivable}
     */
    public function shareReceivable(Receivable $receivable, Request $request): JsonResponse
    {
        abort_unless(
            $this->outletAccessService->canUseWarehouse($request->user(), $receivable->transaction?->warehouse),
            404
        );

        $campaign = $this->crmAutomationService->createInvoiceShareCampaignForReceivable($receivable, $request->user()->id);

        return $this->created($campaign);
    }
}

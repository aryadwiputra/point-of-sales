<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrmCampaign\IndexCrmCampaignRequest;
use App\Http\Requests\CrmCampaign\SaveCrmCampaignRequest;
use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Services\CrmCampaigns\CrmCampaignFormQueryService;
use App\Services\CrmCampaigns\CrmCampaignIndexQueryService;
use App\Services\CrmCampaigns\CrmCampaignLifecycleService;
use App\Services\CrmCampaigns\CrmCampaignShowQueryService;
use App\Services\CrmCampaigns\CrmInvoiceShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmCampaignController extends Controller
{
    public function index(
        IndexCrmCampaignRequest $request,
        CrmCampaignIndexQueryService $service
    ): Response {
        return Inertia::render('Dashboard/CrmCampaigns/Index', $service->execute($request->filters()));
    }

    public function create(CrmCampaignFormQueryService $service): Response
    {
        return Inertia::render('Dashboard/CrmCampaigns/Create', $service->execute());
    }

    public function store(
        SaveCrmCampaignRequest $request,
        CrmCampaignLifecycleService $service
    ): RedirectResponse {
        $campaign = $service->create(
            $request->campaignData(),
            $request->user()->id,
            $request->saveAsDraft()
        );

        return redirect()
            ->route('crm-campaigns.show', $campaign)
            ->with('success', 'Campaign CRM berhasil dibuat.');
    }

    public function show(
        CustomerCampaign $crmCampaign,
        CrmCampaignShowQueryService $service
    ): Response {
        return Inertia::render('Dashboard/CrmCampaigns/Show', $service->execute($crmCampaign));
    }

    public function edit(
        CustomerCampaign $crmCampaign,
        CrmCampaignFormQueryService $service
    ): Response {
        return Inertia::render('Dashboard/CrmCampaigns/Edit', $service->execute($crmCampaign));
    }

    public function update(
        SaveCrmCampaignRequest $request,
        CustomerCampaign $crmCampaign,
        CrmCampaignLifecycleService $service
    ): RedirectResponse {
        $campaign = $service->update($crmCampaign, $request->campaignData());

        return redirect()
            ->route('crm-campaigns.show', $campaign)
            ->with('success', 'Campaign CRM berhasil diperbarui.');
    }

    public function destroy(
        CustomerCampaign $crmCampaign,
        CrmCampaignLifecycleService $service
    ): RedirectResponse {
        $service->delete($crmCampaign);

        return redirect()
            ->route('crm-campaigns.index')
            ->with('success', 'Campaign CRM berhasil dihapus.');
    }

    public function process(
        CustomerCampaign $crmCampaign,
        CrmCampaignLifecycleService $service
    ): RedirectResponse {
        $campaign = $service->process($crmCampaign);

        return redirect()
            ->route('crm-campaigns.show', $campaign)
            ->with('success', 'Campaign berhasil diproses ke audience.');
    }

    public function cancel(
        CustomerCampaign $crmCampaign,
        CrmCampaignLifecycleService $service
    ): RedirectResponse {
        $campaign = $service->cancel($crmCampaign);

        return redirect()
            ->route('crm-campaigns.show', $campaign)
            ->with('success', 'Campaign dibatalkan.');
    }

    public function markLogSent(
        CustomerCampaignLog $log,
        CrmCampaignLifecycleService $service
    ): RedirectResponse {
        $service->markLog($log, CustomerCampaignLog::STATUS_SENT);

        return back()->with('success', 'Log campaign ditandai sebagai terkirim.');
    }

    public function markLogSkipped(
        CustomerCampaignLog $log,
        CrmCampaignLifecycleService $service
    ): RedirectResponse {
        $service->markLog($log, CustomerCampaignLog::STATUS_SKIPPED);

        return back()->with('success', 'Log campaign dilewati.');
    }

    public function shareTransaction(
        Transaction $transaction,
        Request $request,
        CrmInvoiceShareService $service
    ): RedirectResponse {
        $campaign = $service->forTransaction($transaction, $request->user()->id);

        return redirect()
            ->route('crm-campaigns.show', $campaign)
            ->with('success', 'Campaign share invoice transaksi berhasil dibuat.');
    }

    public function shareReceivable(
        Receivable $receivable,
        Request $request,
        CrmInvoiceShareService $service
    ): RedirectResponse {
        $campaign = $service->forReceivable($receivable, $request->user()->id);

        return redirect()
            ->route('crm-campaigns.show', $campaign)
            ->with('success', 'Campaign share piutang berhasil dibuat.');
    }
}

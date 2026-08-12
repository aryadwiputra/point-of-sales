<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Services\CrmAutomationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CrmCampaignController extends Controller
{
    public function __construct(
        private readonly CrmAutomationService $crmAutomationService
    ) {}

    public function index(Request $request)
    {
        $filters = [
            'type' => $request->input('type'),
            'status' => $request->input('status'),
        ];

        $campaigns = CustomerCampaign::query()
            ->with(['creator:id,name'])
            ->withCount('logs')
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(10)->withQueryString()
            ->withQueryString();

        return Inertia::render('Dashboard/CrmCampaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Dashboard/CrmCampaigns/Create', [
            'campaign' => null,
            'audienceOptions' => $this->crmAutomationService->audienceOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCampaign($request);
        $campaign = $this->crmAutomationService->createCampaign($validated, $request->user()->id);

        if (! $request->boolean('save_as_draft')) {
            $campaign = $this->crmAutomationService->processCampaign($campaign);
        }

        return redirect()
            ->route('crm-campaigns.show', $campaign)
            ->with('success', 'Campaign CRM berhasil dibuat.');
    }

    public function show(CustomerCampaign $crmCampaign)
    {
        $crmCampaign->load([
            'creator:id,name',
            'logs.customer:id,name,no_telp',
            'logs.transaction:id,invoice',
            'logs.receivable:id,invoice,due_date',
        ]);

        return Inertia::render('Dashboard/CrmCampaigns/Show', [
            'campaign' => $crmCampaign,
        ]);
    }

    public function edit(CustomerCampaign $crmCampaign)
    {
        return Inertia::render('Dashboard/CrmCampaigns/Edit', [
            'campaign' => $crmCampaign,
            'audienceOptions' => $this->crmAutomationService->audienceOptions(),
        ]);
    }

    public function update(Request $request, CustomerCampaign $crmCampaign)
    {
        $validated = $this->validateCampaign($request);
        $crmCampaign = $this->crmAutomationService->updateCampaign($crmCampaign, $validated);

        return redirect()
            ->route('crm-campaigns.show', $crmCampaign)
            ->with('success', 'Campaign CRM berhasil diperbarui.');
    }

    public function destroy(CustomerCampaign $crmCampaign)
    {
        $crmCampaign->delete();

        return redirect()
            ->route('crm-campaigns.index')
            ->with('success', 'Campaign CRM berhasil dihapus.');
    }

    public function process(CustomerCampaign $crmCampaign)
    {
        $crmCampaign = $this->crmAutomationService->processCampaign($crmCampaign);

        return redirect()
            ->route('crm-campaigns.show', $crmCampaign)
            ->with('success', 'Campaign berhasil diproses ke audience.');
    }

    public function cancel(CustomerCampaign $crmCampaign)
    {
        $crmCampaign = $this->crmAutomationService->cancelCampaign($crmCampaign);

        return redirect()
            ->route('crm-campaigns.show', $crmCampaign)
            ->with('success', 'Campaign dibatalkan.');
    }

    public function markLogSent(CustomerCampaignLog $log)
    {
        $this->crmAutomationService->markLog($log, CustomerCampaignLog::STATUS_SENT);

        return back()->with('success', 'Log campaign ditandai sebagai terkirim.');
    }

    public function markLogSkipped(CustomerCampaignLog $log)
    {
        $this->crmAutomationService->markLog($log, CustomerCampaignLog::STATUS_SKIPPED);

        return back()->with('success', 'Log campaign dilewati.');
    }

    public function shareTransaction(Transaction $transaction, Request $request)
    {
        $campaign = $this->crmAutomationService->createInvoiceShareCampaignForTransaction($transaction, $request->user()->id);

        return redirect()
            ->route('crm-campaigns.show', $campaign)
            ->with('success', 'Campaign share invoice transaksi berhasil dibuat.');
    }

    public function shareReceivable(Receivable $receivable, Request $request)
    {
        $campaign = $this->crmAutomationService->createInvoiceShareCampaignForReceivable($receivable, $request->user()->id);

        return redirect()
            ->route('crm-campaigns.show', $campaign)
            ->with('success', 'Campaign share piutang berhasil dibuat.');
    }

    private function validateCampaign(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([
                CustomerCampaign::TYPE_PROMO_BROADCAST,
                CustomerCampaign::TYPE_INVOICE_SHARE,
                CustomerCampaign::TYPE_DUE_DATE_REMINDER,
                CustomerCampaign::TYPE_REPEAT_ORDER_REMINDER,
            ])],
            'channel' => ['required', Rule::in([
                CustomerCampaign::CHANNEL_INTERNAL,
                CustomerCampaign::CHANNEL_WHATSAPP_LINK,
            ])],
            'message_template' => ['nullable', 'string', 'max:4000'],
            'audience_filters' => ['nullable', 'array'],
            'audience_filters.segment_ids' => ['nullable', 'array'],
            'audience_filters.segment_ids.*' => ['integer', 'exists:customer_segments,id'],
            'audience_filters.customer_type' => ['nullable', Rule::in(['all', 'member', 'non_member'])],
            'audience_filters.receivable_status' => ['nullable', Rule::in(['all', 'has_receivable', 'overdue', 'due_soon'])],
            'audience_filters.voucher_filter' => ['nullable', Rule::in(['all', 'has_active_voucher', 'no_active_voucher'])],
        ]);
    }
}

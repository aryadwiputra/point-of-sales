<?php

declare(strict_types=1);

namespace App\Services\CrmCampaigns;

use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\Receivable;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CrmInvoiceShareService
{
    public function forTransaction(Transaction $transaction, int $userId): CustomerCampaign
    {
        return DB::transaction(function () use ($transaction, $userId) {
            $campaign = CustomerCampaign::query()->firstOrCreate(
                ['context_key' => 'invoice-share-transaction-'.$transaction->id],
                [
                    'name' => 'Share Invoice '.$transaction->invoice,
                    'type' => CustomerCampaign::TYPE_INVOICE_SHARE,
                    'status' => CustomerCampaign::STATUS_READY,
                    'channel' => CustomerCampaign::CHANNEL_WHATSAPP_LINK,
                    'audience_filters' => ['transaction_id' => $transaction->id],
                    'audience_snapshot' => [[
                        'customer_id' => $transaction->customer_id,
                        'transaction_id' => $transaction->id,
                        'invoice' => $transaction->invoice,
                    ]],
                    'message_template' => 'Invoice {{invoice}}: {{url}}',
                    'processed_at' => now(),
                    'created_by' => $userId,
                ]
            );

            $message = 'Invoice '.$transaction->invoice.': '.route('transactions.public', $transaction->invoice, true);

            $campaign->logs()->updateOrCreate(
                [
                    'transaction_id' => $transaction->id,
                    'customer_id' => $transaction->customer_id,
                ],
                [
                    'channel' => CustomerCampaign::CHANNEL_WHATSAPP_LINK,
                    'status' => CustomerCampaignLog::STATUS_READY_TO_SEND,
                    'payload' => [
                        'message' => $message,
                        'whatsapp_url' => 'https://wa.me/?text='.urlencode($message),
                        'invoice' => $transaction->invoice,
                    ],
                ]
            );

            return $campaign->fresh(['logs.customer', 'logs.transaction']);
        });
    }

    public function forReceivable(Receivable $receivable, int $userId): CustomerCampaign
    {
        return DB::transaction(function () use ($receivable, $userId) {
            $campaign = CustomerCampaign::query()->firstOrCreate(
                ['context_key' => 'invoice-share-receivable-'.$receivable->id],
                [
                    'name' => 'Share Piutang '.$receivable->invoice,
                    'type' => CustomerCampaign::TYPE_INVOICE_SHARE,
                    'status' => CustomerCampaign::STATUS_READY,
                    'channel' => CustomerCampaign::CHANNEL_WHATSAPP_LINK,
                    'audience_filters' => ['receivable_id' => $receivable->id],
                    'audience_snapshot' => [[
                        'customer_id' => $receivable->customer_id,
                        'receivable_id' => $receivable->id,
                        'invoice' => $receivable->invoice,
                    ]],
                    'message_template' => 'Invoice {{invoice}} total {{remaining}} jatuh tempo {{due_date}}',
                    'processed_at' => now(),
                    'created_by' => $userId,
                ]
            );

            $message = sprintf(
                'Pengingat piutang %s. Sisa tagihan Rp %s. Jatuh tempo: %s',
                $receivable->invoice,
                number_format($receivable->remaining, 0, ',', '.'),
                optional($receivable->due_date)?->format('d/m/Y') ?? '-'
            );

            $campaign->logs()->updateOrCreate(
                [
                    'receivable_id' => $receivable->id,
                    'customer_id' => $receivable->customer_id,
                ],
                [
                    'channel' => CustomerCampaign::CHANNEL_WHATSAPP_LINK,
                    'status' => CustomerCampaignLog::STATUS_READY_TO_SEND,
                    'payload' => [
                        'message' => $message,
                        'whatsapp_url' => 'https://wa.me/?text='.urlencode($message),
                        'invoice' => $receivable->invoice,
                    ],
                ]
            );

            return $campaign->fresh(['logs.customer', 'logs.receivable']);
        });
    }
}

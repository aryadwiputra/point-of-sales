<?php

declare(strict_types=1);

namespace App\Services\SalesReturns;

use App\Models\SalesReturn;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSalesReturnService
{
    public function __construct(
        private readonly SalesReturnGuardService $guard,
        private readonly SalesReturnAccessService $access,
        private readonly SalesReturnPayloadService $payloadService,
        private readonly SalesReturnTransformerService $transformer,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(Transaction $transaction, array $data, User $user): SalesReturn
    {
        $this->guard->ensureTablesExist();

        $transaction = $this->access->resolveAccessibleTransaction($user, $transaction->id);
        $payload = $this->payloadService->prepareDraftPayload($transaction, $data);

        $salesReturn = DB::transaction(function () use ($user, $transaction, $payload) {
            $salesReturn = SalesReturn::create([
                'code' => $this->generateCode(),
                'transaction_id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'cashier_id' => $user->id,
                'status' => 'draft',
                'return_type' => $payload['return_type'],
                'refund_amount' => $payload['refund_amount'],
                'credited_amount' => $payload['credited_amount'],
                'total_return_amount' => $payload['total_return_amount'],
                'notes' => $payload['notes'],
            ]);

            $salesReturn->items()->createMany($payload['items']);

            return $salesReturn;
        });

        $salesReturn->load('items.product');
        $this->auditLogService->log(
            event: 'sales_return.created',
            module: 'sales_returns',
            auditable: $salesReturn,
            description: 'Draft retur penjualan dibuat.',
            after: $this->transformer->auditPayload($salesReturn),
        );

        return $salesReturn;
    }

    private function generateCode(): string
    {
        do {
            $code = 'SR-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
        } while (SalesReturn::where('code', $code)->exists());

        return $code;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\SalesReturns;

use App\Models\SalesReturn;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateSalesReturnService
{
    public function __construct(
        private readonly SalesReturnGuardService $guard,
        private readonly SalesReturnAccessService $access,
        private readonly SalesReturnPayloadService $payloadService,
        private readonly SalesReturnTransformerService $transformer,
        private readonly AuditLogService $auditLogService
    ) {}

    public function execute(SalesReturn $salesReturn, array $data, User $user): void
    {
        $this->guard->ensureTablesExist();

        $salesReturn = $this->access->resolveAccessibleSalesReturn($user, $salesReturn->id);
        $this->guard->ensureDraft($salesReturn);
        $before = $this->transformer->auditPayload($salesReturn);

        $payload = $this->payloadService->prepareDraftPayload(
            $salesReturn->transaction,
            $data,
            $salesReturn->id
        );

        DB::transaction(function () use ($salesReturn, $payload) {
            $salesReturn->update([
                'return_type' => $payload['return_type'],
                'refund_amount' => $payload['refund_amount'],
                'credited_amount' => $payload['credited_amount'],
                'total_return_amount' => $payload['total_return_amount'],
                'notes' => $payload['notes'],
            ]);

            $salesReturn->items()->delete();
            $salesReturn->items()->createMany($payload['items']);
        });

        $salesReturn->refresh();
        $salesReturn->load('items.product');
        $this->auditLogService->log(
            event: 'sales_return.updated',
            module: 'sales_returns',
            auditable: $salesReturn,
            description: 'Draft retur penjualan diperbarui.',
            before: $before,
            after: $this->transformer->auditPayload($salesReturn),
        );
    }
}

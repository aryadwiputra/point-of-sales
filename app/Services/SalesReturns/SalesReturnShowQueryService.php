<?php

declare(strict_types=1);

namespace App\Services\SalesReturns;

use App\Models\SalesReturn;
use App\Models\User;

class SalesReturnShowQueryService
{
    public function __construct(
        private readonly SalesReturnGuardService $guard,
        private readonly SalesReturnAccessService $access,
        private readonly SalesReturnTransformerService $transformer
    ) {}

    public function execute(SalesReturn $salesReturn, User $user): array
    {
        $this->guard->ensureTablesExist();

        $salesReturn = $this->access->resolveAccessibleSalesReturn($user, $salesReturn->id);

        return [
            'salesReturn' => $this->transformer->salesReturn($salesReturn),
            'transaction' => $this->transformer->transactionForEditor($salesReturn->transaction, $salesReturn),
        ];
    }
}

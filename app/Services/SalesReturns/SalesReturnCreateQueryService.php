<?php

declare(strict_types=1);

namespace App\Services\SalesReturns;

use App\Models\Transaction;
use App\Models\User;

class SalesReturnCreateQueryService
{
    public function __construct(
        private readonly SalesReturnGuardService $guard,
        private readonly SalesReturnAccessService $access,
        private readonly SalesReturnTransformerService $transformer
    ) {}

    public function execute(Transaction $transaction, User $user): ?array
    {
        $this->guard->ensureTablesExist();

        $transaction = $this->access->resolveAccessibleTransaction($user, $transaction->id);

        if (! $this->transformer->hasReturnableItems($transaction)) {
            return null;
        }

        return [
            'transaction' => $this->transformer->transactionForEditor($transaction),
        ];
    }
}

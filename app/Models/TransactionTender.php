<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionTender extends Model
{
    public const METHOD_CASH = 'cash';

    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const METHOD_MIDTRANS = 'midtrans';

    public const METHOD_XENDIT = 'xendit';

    public const METHOD_QRIS = 'qris';

    public const STATUS_PAID = 'paid';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'transaction_id',
        'method',
        'amount',
        'cash_received',
        'change',
        'bank_account_id',
        'payment_status',
        'payment_reference',
        'payment_url',
        'qr_string',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'cash_received' => 'integer',
            'change' => 'integer',
            'bank_account_id' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Gateway order id used for this tender (invoice or invoice-{method} for splits).
     */
    public function getOrderReferenceAttribute(): string
    {
        // ponytail: derived, not stored — matches PaymentGatewayManager::createTenderPayment
        $siblings = $this->transaction->tenders()->count();

        return $siblings > 1 ? $this->transaction->invoice.'-'.$this->method : $this->transaction->invoice;
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}

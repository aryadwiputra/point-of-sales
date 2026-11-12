<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Antrean/pencatatan pengiriman transaksi BPOS → Bihaju.
 *
 * `external_ref` adalah idempotency key: mengirim nilai yang sama berulang
 * kali tidak menggandakan komisi di sisi Bihaju.
 */
class BihajuTransaction extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    /** @var array<int, string> */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SUBMITTED,
        self::STATUS_SUCCEEDED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'tenant_id',
        'binding_id',
        'transaction_id',
        'external_ref',
        'amount',
        'member_code',
        'status',
        'bihaju_transaction_code',
        'balance_after',
        'attempts',
        'breakdown',
        'response',
        'last_error',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'attempts' => 'integer',
            'breakdown' => 'array',
            'response' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(BihajuBinding::class, 'binding_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCEEDED, self::STATUS_FAILED], true);
    }

    public function markSucceeded(array $response): void
    {
        $this->forceFill([
            'status' => self::STATUS_SUCCEEDED,
            'bihaju_transaction_code' => $response['transaction_code'] ?? null,
            'balance_after' => $response['merchant_balance_after'] ?? null,
            'breakdown' => [
                'merchant_commission' => $response['merchant_commission'] ?? null,
                'member_cashback' => $response['member_cashback'] ?? null,
                'platform_fee' => $response['platform_fee'] ?? null,
            ],
            'response' => $response,
            'last_error' => null,
            'submitted_at' => now(),
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'last_error' => mb_substr($error, 0, 1000),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'invoice' => $this->transaction?->invoice,
            'external_ref' => $this->external_ref,
            'amount' => $this->amount,
            'member_code' => $this->member_code,
            'status' => $this->status,
            'bihaju_transaction_code' => $this->bihaju_transaction_code,
            'balance_after' => $this->balance_after,
            'breakdown' => $this->breakdown,
            'attempts' => $this->attempts,
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

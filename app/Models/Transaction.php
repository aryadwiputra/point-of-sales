<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function ($transaction) {
            $transaction->access_token = (string) Str::uuid();
        });
    }

    protected $fillable = [
        'cashier_id',
        'cashier_shift_id',
        'warehouse_id',
        'customer_id',
        'invoice',
        'cash',
        'change',
        'discount',
        'loyalty_points_earned',
        'loyalty_points_redeemed',
        'loyalty_discount_total',
        'customer_voucher_discount',
        'customer_voucher_code',
        'customer_voucher_name',
        'shipping_cost',
        'grand_total',
        'payment_method',
        'payment_status',
        'payment_reference',
        'payment_url',
        'bank_account_id',
        'tax_rate',
        'tax_total',
        'customer_npwp',
        'discount_approved_by',
        'discount_approved_at',
        'discount_approval_status',
        'access_token',
    ];

    protected $casts = [
        'cashier_id' => 'integer',
        'cashier_shift_id' => 'integer',
        'customer_id' => 'integer',
        'cash' => 'integer',
        'change' => 'integer',
        'discount' => 'integer',
        'loyalty_points_earned' => 'integer',
        'loyalty_points_redeemed' => 'integer',
        'loyalty_discount_total' => 'integer',
        'customer_voucher_discount' => 'integer',
        'shipping_cost' => 'integer',
        'grand_total' => 'integer',
        'bank_account_id' => 'integer',
        'tax_rate' => 'decimal:2',
        'tax_total' => 'integer',
        'discount_approved_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    /**
     * customer
     *
     * @return void
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * cashier
     *
     * @return void
     */
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function cashierShift()
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * bankAccount
     *
     * @return void
     */
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * profits
     *
     * @return void
     */
    public function profits()
    {
        return $this->hasMany(Profit::class);
    }

    public function receivable()
    {
        return $this->hasOne(Receivable::class);
    }

    public function salesReturns()
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function usedCustomerVoucher()
    {
        return $this->hasOne(CustomerVoucher::class, 'used_transaction_id');
    }

    public function campaignLogs()
    {
        return $this->hasMany(CustomerCampaignLog::class);
    }

    public function discountApprover()
    {
        return $this->belongsTo(User::class, 'discount_approved_by');
    }

    public function discountApprovalLogs()
    {
        return $this->hasMany(DiscountApprovalLog::class);
    }

    public function needsDiscountApproval(): bool
    {
        $threshold = (int) \App\Models\Setting::get('discount_approval_threshold', 0);
        $percentThreshold = (int) \App\Models\Setting::get('discount_approval_percent_threshold', 0);

        if ($threshold > 0 && $this->discount >= $threshold) {
            return true;
        }
        if ($percentThreshold > 0 && $this->grand_total > 0) {
            $percent = ($this->discount / $this->grand_total) * 100;
            if ($percent >= $percentThreshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * createdAt
     */
    protected function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d-M-Y H:i:s'),
        );
    }
}

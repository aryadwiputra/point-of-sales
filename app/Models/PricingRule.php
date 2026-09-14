<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    use HasFactory;

    public const KIND_STANDARD_DISCOUNT = 'standard_discount';

    public const KIND_QTY_BREAK = 'qty_break';

    public const KIND_BUNDLE_PRICE = 'bundle_price';

    public const KIND_BUY_X_GET_Y = 'buy_x_get_y';

    public const TARGET_ALL = 'all';

    public const TARGET_PRODUCT = 'product';

    public const TARGET_CATEGORY = 'category';

    public const SCOPE_ALL = 'all';

    public const SCOPE_WALK_IN = 'walk_in';

    public const SCOPE_REGISTERED = 'registered';

    public const SCOPE_MEMBER = 'member';

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    public const TYPE_FIXED_PRICE = 'fixed_price';

    protected $fillable = [
        'name',
        'outlet_id',
        'kind',
        'is_active',
        'priority',
        'target_type',
        'product_id',
        'category_id',
        'customer_scope',
        'eligible_loyalty_tiers',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'preview_quantity_multiplier',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'kind' => 'string',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'product_id' => 'integer',
        'outlet_id' => 'integer',
        'category_id' => 'integer',
        'eligible_loyalty_tiers' => 'array',
        'discount_value' => 'float',
        'created_by' => 'integer',
        'preview_quantity_multiplier' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function qtyBreaks()
    {
        return $this->hasMany(PricingRuleQtyBreak::class)
            ->orderByDesc('min_qty')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function bundleItems()
    {
        return $this->hasMany(PricingRuleBundleItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function buyGetItems()
    {
        return $this->hasMany(PricingRuleBuyGetItem::class)
            ->orderBy('role')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isScheduled(): bool
    {
        return $this->starts_at && $this->starts_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function currentStatusLabel(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->isScheduled()) {
            return 'scheduled';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return 'active';
    }
}

<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSegment extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_MANUAL = 'manual';

    public const TYPE_AUTO = 'auto';

    public const RULE_SPENDING = 'spending';

    public const RULE_PURCHASE_FREQUENCY = 'purchase_frequency';

    public const RULE_RECEIVABLE_BEHAVIOR = 'receivable_behavior';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'type',
        'is_active',
        'description',
        'auto_rule_type',
        'rule_config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'rule_config' => 'array',
    ];

    public function memberships()
    {
        return $this->hasMany(CustomerSegmentMembership::class);
    }

    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_segment_memberships')
            ->withPivot(['source', 'matched_at'])
            ->withTimestamps();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerCampaign extends Model
{
    use HasFactory;

    public const TYPE_PROMO_BROADCAST = 'promo_broadcast';

    public const TYPE_INVOICE_SHARE = 'invoice_share';

    public const TYPE_DUE_DATE_REMINDER = 'due_date_reminder';

    public const TYPE_REPEAT_ORDER_REMINDER = 'repeat_order_reminder';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_CANCELLED = 'cancelled';

    public const CHANNEL_INTERNAL = 'internal';

    public const CHANNEL_WHATSAPP_LINK = 'whatsapp_link';

    protected $fillable = [
        'name',
        'outlet_id',
        'type',
        'status',
        'channel',
        'context_key',
        'audience_filters',
        'audience_snapshot',
        'message_template',
        'processed_at',
        'created_by',
    ];

    protected $casts = [
        'audience_filters' => 'array',
        'audience_snapshot' => 'array',
        'processed_at' => 'datetime',
        'created_by' => 'integer',
        'outlet_id' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(CustomerCampaignLog::class);
    }
}

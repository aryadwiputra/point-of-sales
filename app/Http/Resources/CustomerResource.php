<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'no_telp' => $this->no_telp,
            'address' => $this->address,
            'is_loyalty_member' => (bool) $this->is_loyalty_member,
            'member_code' => $this->member_code,
            'loyalty_tier' => $this->loyalty_tier,
            'loyalty_points' => (int) $this->loyalty_points,
            'loyalty_total_spent' => (float) $this->loyalty_total_spent,
            'loyalty_transaction_count' => (int) $this->loyalty_transaction_count,
            'loyalty_member_since' => optional($this->loyalty_member_since)->toISOString(),
            'last_purchase_at' => optional($this->last_purchase_at)->toISOString(),
            'region' => [
                'province' => $this->province_name,
                'regency' => $this->regency_name,
                'district' => $this->district_name,
                'village' => $this->village_name,
            ],
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}

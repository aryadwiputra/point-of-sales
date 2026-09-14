<?php

namespace Tests\Feature\Inventory;

use App\Models\Outlet;
use App\Models\PricingRule;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutletPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_rules_include_global_and_current_outlet_only(): void
    {
        $outletA = Outlet::create(['code' => 'OUT-A', 'name' => 'Outlet A', 'is_active' => true, 'is_sales_enabled' => true]);
        $outletB = Outlet::create(['code' => 'OUT-B', 'name' => 'Outlet B', 'is_active' => true, 'is_sales_enabled' => true]);
        $global = PricingRule::create(['name' => 'Global', 'is_active' => true, 'discount_type' => 'percentage', 'discount_value' => 5]);
        $local = PricingRule::create(['name' => 'Outlet A', 'outlet_id' => $outletA->id, 'is_active' => true, 'discount_type' => 'percentage', 'discount_value' => 10]);
        $other = PricingRule::create(['name' => 'Outlet B', 'outlet_id' => $outletB->id, 'is_active' => true, 'discount_type' => 'percentage', 'discount_value' => 15]);

        $rules = app(PricingService::class)->getActiveRules(null, $outletA);

        $this->assertEqualsCanonicalizing([$global->id, $local->id], $rules->modelKeys());
        $this->assertFalse($rules->contains('id', $other->id));
    }
}

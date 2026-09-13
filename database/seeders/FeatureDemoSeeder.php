<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerCampaign;
use App\Models\CustomerCampaignLog;
use App\Models\CustomerSegment;
use App\Models\CustomerSegmentMembership;
use App\Models\DineArea;
use App\Models\DineOrder;
use App\Models\DiningTable;
use App\Models\DiscountApprovalLog;
use App\Models\LoyaltyPointHistory;
use App\Models\PriceList;
use App\Models\ProductWarehouse;
use App\Models\PricingRule;
use App\Models\PricingRuleBuyGetItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DineOrderService;
use App\Services\StockTransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeatureDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->requiredTablesExist()) {
            $this->command?->warn('Skipping FeatureDemoSeeder because required tables do not exist.');

            return;
        }

        $admin = User::where('email', 'arya@gmail.com')->first() ?? User::first();
        $cashier = User::where('email', 'cashier@gmail.com')->first() ?? $admin;

        if (! $admin || ! $cashier) {
            $this->command?->warn('Skipping FeatureDemoSeeder because sample users are missing.');

            return;
        }

        $products = Product::orderBy('id')->get()->keyBy('barcode');
        $customers = Customer::orderBy('id')->get()->keyBy('name');

        if ($products->isEmpty() || $customers->isEmpty()) {
            $this->command?->warn('Skipping FeatureDemoSeeder because products or customers are missing.');

            return;
        }

        $this->command?->info('Seeding feature demo data...');

        $this->resetFeatureData();

        Auth::setUser($admin);

        try {
            $warehouses = $this->seedWarehouses($products);
            $this->seedUnits($products);
            $this->seedProductBatches($products, $warehouses);
            $this->seedCompositeProduct($products);
            $this->seedPricingRules($products);
            $this->seedPriceLists($products);
            $this->seedCustomerSegments($customers);
            $this->seedCustomerCampaigns($customers);
            $this->seedStockTransfers($products, $warehouses, $admin);
            $this->seedDineIn($products, $customers, $cashier);
            $this->seedDiscountApprovalLogs($cashier, $admin);
            $this->seedLoyaltyPointHistory($customers);
        } finally {
            Auth::logout();
        }
    }

    private function requiredTablesExist(): bool
    {
        return collect([
            'warehouses',
            'product_warehouse',
            'units',
            'product_units',
            'product_batches',
            'composite_product_items',
            'pricing_rules',
            'pricing_rule_qty_breaks',
            'pricing_rule_bundle_items',
            'pricing_rule_buy_get_items',
            'price_lists',
            'price_list_items',
            'customer_segments',
            'customer_segment_memberships',
            'customer_campaigns',
            'customer_campaign_logs',
            'stock_transfers',
            'stock_transfer_items',
            'dine_areas',
            'dine_tables',
            'dine_orders',
            'dine_order_items',
            'discount_approval_logs',
            'loyalty_point_histories',
        ])->every(fn (string $table) => Schema::hasTable($table));
    }

    private function resetFeatureData(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'dine_order_items',
            'dine_orders',
            'dine_tables',
            'dine_areas',
            'stock_transfer_items',
            'stock_transfers',
            'customer_campaign_logs',
            'customer_campaigns',
            'customer_segment_memberships',
            'customer_segments',
            'price_list_items',
            'price_lists',
            'pricing_rule_buy_get_items',
            'pricing_rule_bundle_items',
            'pricing_rule_qty_breaks',
            'pricing_rules',
            'composite_product_items',
            'product_batches',
            'product_units',
            'discount_approval_logs',
            'loyalty_point_histories',
        ] as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();
    }

    private function seedWarehouses(Collection $products): array
    {
        $pusat = Warehouse::where('code', 'PUSAT')->first();

        if (! $pusat) {
            $pusat = Warehouse::create([
                'code' => 'PUSAT',
                'name' => 'Gudang Pusat',
                'type' => 'main',
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        // DatabaseSeeder creates PUSAT before demo products exist. Ensure the
        // warehouse pivot is present even when PUSAT already exists.
        foreach ($products as $product) {
            ProductWarehouse::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'warehouse_id' => $pusat->id,
                ],
                ['stock' => max(0, (int) $product->stock)]
            );
        }

        $cabang = Warehouse::where('code', 'CABANG')->first();

        if (! $cabang) {
            $cabang = Warehouse::create([
                'code' => 'CABANG',
                'name' => 'Gudang Cabang',
                'type' => 'branch',
                'address' => 'Jl. Merdeka No. 12, Bandung',
                'phone' => '022-7654321',
                'is_active' => true,
                'sort_order' => 1,
            ]);

            foreach ($products as $product) {
                $product->warehouses()->syncWithoutDetaching([$cabang->id => ['stock' => 0]]);
            }
        }

        $this->command?->info('  - Warehouses ready (PUSAT + CABANG).');

        return ['pusat' => $pusat, 'cabang' => $cabang];
    }

    private function seedUnits(Collection $products): void
    {
        $pcs = Unit::where('code', 'PCS')->first() ?? Unit::create(['code' => 'PCS', 'name' => 'Pieces', 'symbol' => 'pcs']);
        $box = Unit::where('code', 'BOX')->first() ?? Unit::create(['code' => 'BOX', 'name' => 'Box', 'symbol' => 'box']);
        $karton = Unit::where('code', 'KARTON')->first() ?? Unit::create(['code' => 'KARTON', 'name' => 'Karton', 'symbol' => 'krt']);

        $targets = [
            'MNM-0001' => ['base' => $pcs, 'alt' => $box, 'factor' => 24],
            'SNK-0001' => ['base' => $pcs, 'alt' => $box, 'factor' => 12],
            'SNK-0003' => ['base' => $pcs, 'alt' => $karton, 'factor' => 40],
            'BMB-0002' => ['base' => $pcs, 'alt' => $box, 'factor' => 6],
        ];

        foreach ($targets as $barcode => $cfg) {
            $product = $products->get($barcode);

            if (! $product) {
                continue;
            }

            $product->units()->syncWithoutDetaching([
                $cfg['base']->id => [
                    'is_base' => true,
                    'conversion_factor' => 1,
                    'buy_price' => $product->buy_price,
                    'sell_price' => $product->sell_price,
                    'barcode' => $product->barcode,
                    'sku_suffix' => null,
                ],
                $cfg['alt']->id => [
                    'is_base' => false,
                    'conversion_factor' => $cfg['factor'],
                    'buy_price' => $product->buy_price * $cfg['factor'],
                    'sell_price' => $product->sell_price * $cfg['factor'],
                    'barcode' => $product->barcode.'-'.$cfg['alt']->code,
                    'sku_suffix' => $cfg['alt']->code,
                ],
            ]);
        }

        $this->command?->info('  - Units attached to sample products.');
    }

    private function seedProductBatches(Collection $products, array $warehouses): void
    {
        $targets = [
            'MNM-0001' => ['BATCH-AQUA-001', now()->addMonths(6)->toDateString()],
            'SSU-0001' => ['BATCH-ULTRA-001', now()->addMonths(4)->toDateString()],
            'SSU-0002' => ['BATCH-YOG-001', now()->addDays(20)->toDateString()],
            'RTI-0001' => ['BATCH-ROTI-001', now()->addDays(10)->toDateString()],
        ];

        foreach ($targets as $barcode => [$batchNumber, $expiredAt]) {
            $product = $products->get($barcode);

            if (! $product) {
                continue;
            }

            ProductBatch::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouses['pusat']->id,
                'batch_number' => $batchNumber,
                'expired_at' => $expiredAt,
                'received_at' => now()->subDays(30)->toDateString(),
                'stock' => (int) floor($product->stock / 2),
            ]);
        }

        $this->command?->info('  - Product batches seeded (incl. expiring soon).');
    }

    private function seedCompositeProduct(Collection $products): void
    {
        $nasiGoreng = $products->get('MKN-0001');
        $sosis = $products->get('MKN-0003');
        $kecap = $products->get('BMB-0001');

        if (! $nasiGoreng || ! $sosis || ! $kecap) {
            return;
        }

        $nasiGoreng->update(['is_composite' => true]);

        $nasiGoreng->components()->sync([
            $sosis->id => ['qty' => 1],
            $kecap->id => ['qty' => 1],
        ]);

        $this->command?->info('  - Composite product (Nasi Goreng) created.');
    }

    private function seedPricingRules(Collection $products): void
    {
        $minuman = $products->get('MNM-0001');
        $snack = $products->get('SNK-0001');
        $indomie = $products->get('SNK-0003');
        $kopi = $products->get('MNM-0003');

        if ($minuman) {
            $rule = PricingRule::create([
                'name' => 'Diskon Minuman 10%',
                'kind' => PricingRule::KIND_STANDARD_DISCOUNT,
                'is_active' => true,
                'priority' => 10,
                'target_type' => PricingRule::TARGET_PRODUCT,
                'product_id' => $minuman->id,
                'category_id' => null,
                'customer_scope' => PricingRule::SCOPE_ALL,
                'eligible_loyalty_tiers' => null,
                'discount_type' => PricingRule::TYPE_PERCENTAGE,
                'discount_value' => 10,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
                'preview_quantity_multiplier' => 1,
                'notes' => 'Demo: diskon standar per produk.',
                'created_by' => auth()->id(),
            ]);
        }

        if ($snack) {
            $rule = PricingRule::create([
                'name' => 'Beli Banyak Snack Lebih Hemat',
                'kind' => PricingRule::KIND_QTY_BREAK,
                'is_active' => true,
                'priority' => 20,
                'target_type' => PricingRule::TARGET_PRODUCT,
                'product_id' => $snack->id,
                'category_id' => null,
                'customer_scope' => PricingRule::SCOPE_ALL,
                'eligible_loyalty_tiers' => null,
                'discount_type' => PricingRule::TYPE_PERCENTAGE,
                'discount_value' => 0,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
                'preview_quantity_multiplier' => 1,
                'notes' => 'Demo: qty break.',
                'created_by' => auth()->id(),
            ]);

            $rule->qtyBreaks()->createMany([
                ['min_qty' => 3, 'discount_type' => PricingRule::TYPE_PERCENTAGE, 'discount_value' => 5, 'sort_order' => 1],
                ['min_qty' => 6, 'discount_type' => PricingRule::TYPE_PERCENTAGE, 'discount_value' => 10, 'sort_order' => 2],
            ]);
        }

        if ($indomie && $kopi) {
            $rule = PricingRule::create([
                'name' => 'Bundle Indomie + Kopi',
                'kind' => PricingRule::KIND_BUNDLE_PRICE,
                'is_active' => true,
                'priority' => 30,
                'target_type' => PricingRule::TARGET_ALL,
                'product_id' => null,
                'category_id' => null,
                'customer_scope' => PricingRule::SCOPE_ALL,
                'eligible_loyalty_tiers' => null,
                'discount_type' => PricingRule::TYPE_FIXED_PRICE,
                'discount_value' => 20000,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
                'preview_quantity_multiplier' => 1,
                'notes' => 'Demo: bundle price.',
                'created_by' => auth()->id(),
            ]);

            $rule->bundleItems()->createMany([
                ['product_id' => $indomie->id, 'quantity' => 1, 'sort_order' => 1],
                ['product_id' => $kopi->id, 'quantity' => 1, 'sort_order' => 2],
            ]);
        }

        if ($indomie) {
            $rule = PricingRule::create([
                'name' => 'Beli 2 Indomie Gratis 1',
                'kind' => PricingRule::KIND_BUY_X_GET_Y,
                'is_active' => true,
                'priority' => 40,
                'target_type' => PricingRule::TARGET_PRODUCT,
                'product_id' => $indomie->id,
                'category_id' => null,
                'customer_scope' => PricingRule::SCOPE_ALL,
                'eligible_loyalty_tiers' => null,
                'discount_type' => PricingRule::TYPE_FIXED_AMOUNT,
                'discount_value' => 0,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonths(3),
                'preview_quantity_multiplier' => 1,
                'notes' => 'Demo: buy x get y.',
                'created_by' => auth()->id(),
            ]);

            $rule->buyGetItems()->createMany([
                ['product_id' => $indomie->id, 'role' => PricingRuleBuyGetItem::ROLE_BUY, 'quantity' => 2, 'sort_order' => 1],
                ['product_id' => $indomie->id, 'role' => PricingRuleBuyGetItem::ROLE_GET, 'quantity' => 1, 'sort_order' => 2],
            ]);
        }

        $this->command?->info('  - Pricing rules seeded (standard, qty break, bundle, buy-get).');
    }

    private function seedPriceLists(Collection $products): void
    {
        $list = PriceList::create([
            'name' => 'Harga Grosir',
            'slug' => 'harga-grosir',
            'customer_scope' => 'all',
            'customer_segment_id' => null,
            'is_active' => true,
            'priority' => 1,
            'notes' => 'Demo: daftar harga grosir.',
        ]);

        foreach ($products->take(6) as $product) {
            $list->items()->create([
                'product_id' => $product->id,
                'price' => (int) round($product->sell_price * 0.9),
            ]);
        }

        $this->command?->info('  - Price list seeded.');
    }

    private function seedCustomerSegments(Collection $customers): void
    {
        $vip = CustomerSegment::create([
            'name' => 'VIP Pelanggan',
            'slug' => 'vip-pelanggan',
            'type' => CustomerSegment::TYPE_MANUAL,
            'is_active' => true,
            'description' => 'Demo: segmen manual untuk pelanggan prioritas.',
            'auto_rule_type' => null,
            'rule_config' => [],
        ]);

        $highSpender = CustomerSegment::create([
            'name' => 'Pembelanja Tinggi',
            'slug' => 'pembelanja-tinggi',
            'type' => CustomerSegment::TYPE_AUTO,
            'is_active' => true,
            'description' => 'Demo: segmen otomatis berdasarkan total belanja.',
            'auto_rule_type' => CustomerSegment::RULE_SPENDING,
            'rule_config' => ['min_total_spent' => 1000000],
        ]);

        $vipCustomer = $customers->get('Andi Nugraha');
        $highSpenderCustomer = $customers->get('Eko Saputra');

        if ($vipCustomer) {
            CustomerSegmentMembership::create([
                'customer_id' => $vipCustomer->id,
                'customer_segment_id' => $vip->id,
                'source' => CustomerSegmentMembership::SOURCE_MANUAL,
                'matched_at' => now(),
            ]);
        }

        if ($highSpenderCustomer) {
            CustomerSegmentMembership::create([
                'customer_id' => $highSpenderCustomer->id,
                'customer_segment_id' => $highSpender->id,
                'source' => CustomerSegmentMembership::SOURCE_AUTO,
                'matched_at' => now(),
            ]);
        }

        $this->command?->info('  - Customer segments seeded.');
    }

    private function seedCustomerCampaigns(Collection $customers): void
    {
        $campaign = CustomerCampaign::create([
            'name' => 'Promo Akhir Bulan',
            'type' => CustomerCampaign::TYPE_PROMO_BROADCAST,
            'status' => CustomerCampaign::STATUS_READY,
            'channel' => CustomerCampaign::CHANNEL_INTERNAL,
            'context_key' => 'promo-akhir-bulan-'.now()->format('Ymd'),
            'audience_filters' => ['segment' => 'vip-pelanggan'],
            'audience_snapshot' => $customers->take(2)->pluck('id')->all(),
            'message_template' => 'Halo {name}, dapatkan diskon spesial akhir bulan!',
            'processed_at' => null,
            'created_by' => auth()->id(),
        ]);

        foreach ($customers->take(2) as $customer) {
            CustomerCampaignLog::create([
                'customer_campaign_id' => $campaign->id,
                'customer_id' => $customer->id,
                'transaction_id' => null,
                'receivable_id' => null,
                'channel' => CustomerCampaign::CHANNEL_INTERNAL,
                'status' => CustomerCampaignLog::STATUS_PENDING,
                'payload' => ['name' => $customer->name],
                'sent_at' => null,
            ]);
        }

        $this->command?->info('  - Customer campaigns seeded.');
    }

    private function seedStockTransfers(Collection $products, array $warehouses, User $admin): void
    {
        $service = app(StockTransferService::class);

        $items = [];
        foreach (['MNM-0001', 'SNK-0001', 'BMB-0002'] as $barcode) {
            $product = $products->get($barcode);

            if ($product) {
                $items[] = ['product_id' => $product->id, 'qty' => 10];
            }
        }

        if (empty($items)) {
            return;
        }

        $transfer = $service->createDraft([
            'source_warehouse_id' => $warehouses['pusat']->id,
            'destination_warehouse_id' => $warehouses['cabang']->id,
            'notes' => 'Demo: transfer stok ke cabang.',
        ], $items, $admin->id);

        $service->send($transfer, $admin->id);
        $service->receive($transfer, $admin->id);

        $this->command?->info('  - Stock transfer completed (PUSAT → CABANG).');
    }

    private function seedDineIn(Collection $products, Collection $customers, User $cashier): void
    {
        $area = DineArea::create(['name' => 'Area Utama', 'sort_order' => 1, 'is_active' => true]);
        $area2 = DineArea::create(['name' => 'Area Teras', 'sort_order' => 2, 'is_active' => true]);

        $table1 = DiningTable::create(['dine_area_id' => $area->id, 'name' => 'Meja 1', 'capacity' => 4, 'pos_x' => 10, 'pos_y' => 10, 'shape' => 'circle', 'sort_order' => 1, 'is_active' => true]);
        $table2 = DiningTable::create(['dine_area_id' => $area->id, 'name' => 'Meja 2', 'capacity' => 6, 'pos_x' => 30, 'pos_y' => 10, 'shape' => 'rectangle', 'sort_order' => 2, 'is_active' => true]);
        $table3 = DiningTable::create(['dine_area_id' => $area2->id, 'name' => 'Meja 3', 'capacity' => 2, 'pos_x' => 10, 'pos_y' => 30, 'shape' => 'circle', 'sort_order' => 1, 'is_active' => true]);

        $customer = $customers->get('Andi Nugraha');
        $product1 = $products->get('MNM-0001');
        $product2 = $products->get('SNK-0001');

        $order = DineOrder::create([
            'dine_table_id' => $table1->id,
            'customer_id' => $customer?->id,
            'status' => DineOrder::STATUS_SUBMITTED,
            'notes' => 'Demo: pesanan dine-in.',
            'payment_option' => DineOrder::PAY_AT_COUNTER,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'cashier_id' => $cashier->id,
            'subtotal' => 0,
            'item_count' => 0,
        ]);

        $subtotal = 0;
        $count = 0;

        if ($product1) {
            $order->items()->create([
                'product_id' => $product1->id,
                'unit_id' => null,
                'conversion_factor' => 1,
                'qty' => 2,
                'price' => $product1->sell_price,
                'note' => null,
            ]);
            $subtotal += $product1->sell_price * 2;
            $count += 2;
        }

        if ($product2) {
            $order->items()->create([
                'product_id' => $product2->id,
                'unit_id' => null,
                'conversion_factor' => 1,
                'qty' => 1,
                'price' => $product2->sell_price,
                'note' => null,
            ]);
            $subtotal += $product2->sell_price;
            $count += 1;
        }

        $order->update(['subtotal' => $subtotal, 'item_count' => $count]);

        $service = app(DineOrderService::class);

        try {
            $service->accept($order);
        } catch (\Throwable $e) {
            $this->command?->warn('  - Dine-in accept skipped: '.$e->getMessage());
        }

        $this->command?->info('  - Dine-in areas, tables, and orders seeded.');
    }

    private function seedDiscountApprovalLogs(User $cashier, User $admin): void
    {
        $transaction = Transaction::orderBy('id')->first();

        if (! $transaction) {
            return;
        }

        DiscountApprovalLog::create([
            'transaction_id' => $transaction->id,
            'cashier_id' => $cashier->id,
            'requested_discount' => 5000,
            'status' => 'approved',
            'responded_by' => $admin->id,
            'responded_at' => now(),
            'notes' => 'Demo: diskon disetujui.',
        ]);

        $this->command?->info('  - Discount approval log seeded.');
    }

    private function seedLoyaltyPointHistory(Collection $customers): void
    {
        $customer = $customers->get('Andi Nugraha');

        if (! $customer) {
            return;
        }

        LoyaltyPointHistory::create([
            'customer_id' => $customer->id,
            'transaction_id' => null,
            'type' => LoyaltyPointHistory::TYPE_EARN,
            'points_delta' => 50,
            'balance_after' => $customer->loyalty_points + 50,
            'amount_delta' => 500000,
            'reference' => 'Demo earn',
            'notes' => 'Demo: riwayat poin loyalty.',
        ]);

        $this->command?->info('  - Loyalty point history seeded.');
    }
}

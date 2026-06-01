<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\CustomerVoucher;
use App\Models\LoyaltyPointHistory;
use App\Models\Payable;
use App\Models\PayablePayment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Profit;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockMutation;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        Cart::truncate();
        LoyaltyPointHistory::truncate();
        CustomerVoucher::truncate();
        CustomerCredit::truncate();
        SalesReturnItem::truncate();
        SalesReturn::truncate();
        CashierShift::truncate();
        StockMutation::truncate();
        ReceivablePayment::truncate();
        PayablePayment::truncate();
        Receivable::truncate();
        Payable::truncate();
        TransactionDetail::truncate();
        Profit::truncate();
        Transaction::truncate();
        ProductUnit::truncate();
        Product::truncate();
        Category::truncate();
        Customer::truncate();
        Supplier::truncate();

        Schema::enableForeignKeyConstraints();

        // Ensure storage directories exist
        Storage::disk('public')->makeDirectory('category');
        Storage::disk('public')->makeDirectory('products');

        $this->command->info('Seeding customers...');
        $customers = $this->seedCustomers();

        $this->command->info('Seeding suppliers...');
        $suppliers = $this->seedSuppliers();

        $this->command->info('Seeding categories with images...');
        $categories = $this->seedCategories();

        $this->command->info('Seeding products with images...');
        $products = $this->seedProducts($categories);

        $this->command->info('Seeding transactions...');
        $this->seedTransactions($customers, $products);

        $this->command->info('Seeding receivables...');
        $this->seedReceivables($customers);

        $this->command->info('Seeding loyalty vouchers...');
        $this->seedCustomerVouchers($customers);

        $this->command->info('Seeding payables...');
        $this->seedPayables($suppliers);

        $this->command->info('Sample data seeding completed!');
    }

    /**
     * Download image from URL and save to storage
     */
    private function downloadImage(string $url, string $folder, string $filename): ?string
    {
        try {
            $this->command->info("  Downloading: {$filename}...");

            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                $extension = 'jpg';
                $fullFilename = $filename.'.'.$extension;

                Storage::disk('public')->put(
                    $folder.'/'.$fullFilename,
                    $response->body()
                );

                return $fullFilename;
            }
        } catch (\Exception $e) {
            $this->command->warn("  Failed to download {$filename}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Seed master customers.
     */
    private function seedCustomers(): Collection
    {
        $customers = collect([
            ['name' => 'Andi Nugraha', 'no_telp' => '6281211111111', 'address' => 'Jl. Melati No. 21, Bandung', 'is_loyalty_member' => true, 'member_code' => 'MEM-ANDI001', 'loyalty_tier' => 'gold', 'loyalty_points' => 180, 'loyalty_total_spent' => 1800000, 'loyalty_transaction_count' => 12, 'loyalty_member_since' => now()->subMonths(8)],
            ['name' => 'Bunga Maharani', 'no_telp' => '6281312345678', 'address' => 'Jl. Mawar No. 5, Jakarta', 'is_loyalty_member' => true, 'member_code' => 'MEM-BUNGA01', 'loyalty_tier' => 'silver', 'loyalty_points' => 60, 'loyalty_total_spent' => 780000, 'loyalty_transaction_count' => 6, 'loyalty_member_since' => now()->subMonths(4)],
            ['name' => 'Cici Amelia', 'no_telp' => '6281512340000', 'address' => 'Jl. Anggrek No. 17, Surabaya'],
            ['name' => 'Davin Pradipta', 'no_telp' => '6285612349911', 'address' => 'Jl. Kenanga No. 2, Yogyakarta'],
            ['name' => 'Eko Saputra', 'no_telp' => '6287712348822', 'address' => 'Jl. Cemara No. 45, Semarang', 'is_loyalty_member' => true, 'member_code' => 'MEM-EKO0001', 'loyalty_tier' => 'platinum', 'loyalty_points' => 420, 'loyalty_total_spent' => 3600000, 'loyalty_transaction_count' => 21, 'loyalty_member_since' => now()->subYear()],
            ['name' => 'Fitri Lestari', 'no_telp' => '6282213345566', 'address' => 'Jl. Sakura No. 7, Medan'],
            ['name' => 'Gina Putri', 'no_telp' => '6281399887766', 'address' => 'Jl. Dahlia No. 12, Malang'],
            ['name' => 'Hendra Wijaya', 'no_telp' => '6285544332211', 'address' => 'Jl. Flamboyan No. 8, Denpasar'],
        ]);

        return $customers
            ->map(fn ($customer) => Customer::create($customer))
            ->keyBy('name');
    }

    private function seedCustomerVouchers(Collection $customers): void
    {
        $blueprints = [
            [
                'customer' => 'Andi Nugraha',
                'code' => 'VCR-ANDI10',
                'name' => 'Voucher Loyal Gold',
                'discount_type' => 'fixed_amount',
                'discount_value' => 10000,
                'minimum_order' => 75000,
            ],
            [
                'customer' => 'Bunga Maharani',
                'code' => 'VCR-BUNGA5',
                'name' => 'Voucher Repeat Order',
                'discount_type' => 'percentage',
                'discount_value' => 5,
                'minimum_order' => 50000,
            ],
            [
                'customer' => 'Eko Saputra',
                'code' => 'VCR-EKO25',
                'name' => 'Voucher Platinum',
                'discount_type' => 'fixed_amount',
                'discount_value' => 25000,
                'minimum_order' => 150000,
            ],
        ];

        foreach ($blueprints as $blueprint) {
            $customer = $customers->get($blueprint['customer']);

            if (! $customer) {
                continue;
            }

            CustomerVoucher::create([
                'customer_id' => $customer->id,
                'code' => $blueprint['code'],
                'name' => $blueprint['name'],
                'discount_type' => $blueprint['discount_type'],
                'discount_value' => $blueprint['discount_value'],
                'minimum_order' => $blueprint['minimum_order'],
                'is_active' => true,
                'starts_at' => now()->subDays(7),
                'expires_at' => now()->addDays(30),
            ]);
        }
    }

    /**
     * Seed master suppliers.
     */
    private function seedSuppliers(): Collection
    {
        $suppliers = collect([
            ['name' => 'PT Sumber Pangan Nusantara', 'phone' => '0215551001', 'email' => 'sales@sumberpangan.test', 'address' => 'Jl. Industri Pangan No. 10, Jakarta'],
            ['name' => 'CV Makmur Jaya Distribusi', 'phone' => '0225551002', 'email' => 'order@makmurjaya.test', 'address' => 'Jl. Soekarno Hatta No. 88, Bandung'],
            ['name' => 'PT Segar Sentosa Abadi', 'phone' => '0315551003', 'email' => 'hello@segarsentosa.test', 'address' => 'Jl. Raya Darmo No. 21, Surabaya'],
            ['name' => 'UD Berkah Retail Grosir', 'phone' => '0245551004', 'email' => 'admin@berkahretail.test', 'address' => 'Jl. Pandanaran No. 45, Semarang'],
        ]);

        return $suppliers
            ->map(fn ($supplier) => Supplier::create($supplier))
            ->keyBy('name');
    }

    /**
     * Seed master categories with downloaded images.
     */
    private function seedCategories(): Collection
    {
        // Categories with Unsplash image URLs (direct download links)
        $categories = collect([
            [
                'name' => 'Minuman',
                'description' => 'Aneka minuman segar dan kemasan',
                'image_url' => 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=400&h=400&fit=crop',
            ],
            [
                'name' => 'Makanan Ringan',
                'description' => 'Camilan dan snack kemasan',
                'image_url' => 'https://images.unsplash.com/photo-1621939514649-280e2ee25f60?w=400&h=400&fit=crop',
            ],
            [
                'name' => 'Makanan Berat',
                'description' => 'Makanan siap saji dan frozen food',
                'image_url' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=400&h=400&fit=crop',
            ],
            [
                'name' => 'Produk Susu',
                'description' => 'Susu, yogurt, dan produk olahan susu',
                'image_url' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=400&h=400&fit=crop',
            ],
            [
                'name' => 'Roti & Kue',
                'description' => 'Roti segar dan aneka kue',
                'image_url' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400&h=400&fit=crop',
            ],
            [
                'name' => 'Bumbu & Rempah',
                'description' => 'Bumbu masak dan rempah-rempah',
                'image_url' => 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?w=400&h=400&fit=crop',
            ],
            [
                'name' => 'Perawatan Tubuh',
                'description' => 'Sabun, shampoo, dan perawatan diri',
                'image_url' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=400&h=400&fit=crop',
            ],
            [
                'name' => 'Kebutuhan Rumah',
                'description' => 'Perlengkapan rumah tangga',
                'image_url' => 'https://images.unsplash.com/photo-1583947215259-38e31be8751f?w=400&h=400&fit=crop',
            ],
        ]);

        return $categories->map(function ($category) {
            $slug = Str::slug($category['name']);
            $image = $this->downloadImage(
                $category['image_url'],
                'category',
                'cat-'.$slug
            );

            return Category::create([
                'name' => $category['name'],
                'description' => $category['description'],
                'image' => $image ?? 'default.jpg',
            ]);
        })->keyBy('name');
    }

    /**
     * Seed products mapped to categories with downloaded images.
     */
    private function seedProducts(Collection $categories): Collection
    {
        // Products with Unsplash image URLs
        $products = collect([
            // Minuman
            ['category' => 'Minuman', 'barcode' => 'MNM-0001', 'title' => 'Aqua Botol 600ml', 'description' => 'Air mineral murni dalam kemasan botol praktis', 'buy_price' => 3000, 'sell_price' => 5000, 'stock' => 200, 'image_url' => 'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?w=300&h=300&fit=crop'],
            ['category' => 'Minuman', 'barcode' => 'MNM-0002', 'title' => 'Teh Botol Sosro 450ml', 'description' => 'Teh manis segar dalam kemasan botol', 'buy_price' => 4000, 'sell_price' => 6000, 'stock' => 150, 'image_url' => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=300&h=300&fit=crop'],
            ['category' => 'Minuman', 'barcode' => 'MNM-0003', 'title' => 'Kopi Susu Gula Aren', 'description' => 'Kopi susu dengan gula aren asli', 'buy_price' => 12000, 'sell_price' => 18000, 'stock' => 80, 'image_url' => 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=300&h=300&fit=crop'],
            ['category' => 'Minuman', 'barcode' => 'MNM-0004', 'title' => 'Jus Jeruk Segar 500ml', 'description' => 'Jus jeruk murni tanpa pengawet', 'buy_price' => 8000, 'sell_price' => 12000, 'stock' => 60, 'image_url' => 'https://images.unsplash.com/photo-1621506289937-a8e4df240d0b?w=300&h=300&fit=crop'],

            // Makanan Ringan
            ['category' => 'Makanan Ringan', 'barcode' => 'SNK-0001', 'title' => 'Chitato Original 68g', 'description' => 'Keripik kentang renyah rasa original', 'buy_price' => 8000, 'sell_price' => 12000, 'stock' => 120, 'image_url' => 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?w=300&h=300&fit=crop'],
            ['category' => 'Makanan Ringan', 'barcode' => 'SNK-0002', 'title' => 'Oreo Vanilla 133g', 'description' => 'Biskuit sandwich dengan krim vanilla', 'buy_price' => 10000, 'sell_price' => 15000, 'stock' => 100, 'image_url' => 'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=300&h=300&fit=crop'],
            ['category' => 'Makanan Ringan', 'barcode' => 'SNK-0003', 'title' => 'Indomie Goreng', 'description' => 'Mie instant goreng favorit Indonesia', 'buy_price' => 2500, 'sell_price' => 3500, 'stock' => 300, 'image_url' => 'https://images.unsplash.com/photo-1612929633738-8fe44f7ec841?w=300&h=300&fit=crop'],
            ['category' => 'Makanan Ringan', 'barcode' => 'SNK-0004', 'title' => 'Pringles Sour Cream', 'description' => 'Keripik kentang premium rasa sour cream', 'buy_price' => 25000, 'sell_price' => 35000, 'stock' => 50, 'image_url' => 'https://images.unsplash.com/photo-1613919113640-25732ec5e61f?w=300&h=300&fit=crop'],

            // Makanan Berat
            ['category' => 'Makanan Berat', 'barcode' => 'MKN-0001', 'title' => 'Nasi Goreng Frozen', 'description' => 'Nasi goreng siap saji tinggal panaskan', 'buy_price' => 15000, 'sell_price' => 22000, 'stock' => 40, 'image_url' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=300&h=300&fit=crop'],
            ['category' => 'Makanan Berat', 'barcode' => 'MKN-0002', 'title' => 'Ayam Goreng Frozen', 'description' => 'Ayam goreng krispy siap goreng', 'buy_price' => 25000, 'sell_price' => 38000, 'stock' => 35, 'image_url' => 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?w=300&h=300&fit=crop'],
            ['category' => 'Makanan Berat', 'barcode' => 'MKN-0003', 'title' => 'Sosis Sapi 500g', 'description' => 'Sosis sapi premium isi 12 pcs', 'buy_price' => 35000, 'sell_price' => 48000, 'stock' => 45, 'image_url' => 'https://images.unsplash.com/photo-1587735243615-c03f25aaff15?w=300&h=300&fit=crop'],

            // Produk Susu
            ['category' => 'Produk Susu', 'barcode' => 'SSU-0001', 'title' => 'Ultra Milk 1L', 'description' => 'Susu UHT full cream', 'buy_price' => 16000, 'sell_price' => 21000, 'stock' => 80, 'image_url' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=300&h=300&fit=crop'],
            ['category' => 'Produk Susu', 'barcode' => 'SSU-0002', 'title' => 'Yogurt Cimory 250ml', 'description' => 'Yogurt drink rasa strawberry', 'buy_price' => 8000, 'sell_price' => 12000, 'stock' => 60, 'image_url' => 'https://images.unsplash.com/photo-1488477181946-6428a0291777?w=300&h=300&fit=crop'],
            ['category' => 'Produk Susu', 'barcode' => 'SSU-0003', 'title' => 'Keju Cheddar 165g', 'description' => 'Keju cheddar slice praktis', 'buy_price' => 22000, 'sell_price' => 30000, 'stock' => 40, 'image_url' => 'https://images.unsplash.com/photo-1486297678162-eb2a19b0a32d?w=300&h=300&fit=crop'],

            // Roti & Kue
            ['category' => 'Roti & Kue', 'barcode' => 'RTI-0001', 'title' => 'Roti Tawar Sari Roti', 'description' => 'Roti tawar lembut tanpa kulit', 'buy_price' => 12000, 'sell_price' => 16000, 'stock' => 50, 'image_url' => 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?w=300&h=300&fit=crop'],
            ['category' => 'Roti & Kue', 'barcode' => 'RTI-0002', 'title' => 'Donat Coklat', 'description' => 'Donat lembut dengan topping coklat', 'buy_price' => 5000, 'sell_price' => 8000, 'stock' => 30, 'image_url' => 'https://images.unsplash.com/photo-1551024601-bec78aea704b?w=300&h=300&fit=crop'],
            ['category' => 'Roti & Kue', 'barcode' => 'RTI-0003', 'title' => 'Croissant Butter', 'description' => 'Croissant dengan butter premium', 'buy_price' => 10000, 'sell_price' => 15000, 'stock' => 25, 'image_url' => 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=300&h=300&fit=crop'],

            // Bumbu & Rempah
            ['category' => 'Bumbu & Rempah', 'barcode' => 'BMB-0001', 'title' => 'Kecap Manis ABC 600ml', 'description' => 'Kecap manis kualitas premium', 'buy_price' => 18000, 'sell_price' => 25000, 'stock' => 70, 'image_url' => 'https://images.unsplash.com/photo-1472476443507-c7a5948772fc?w=300&h=300&fit=crop'],
            ['category' => 'Bumbu & Rempah', 'barcode' => 'BMB-0002', 'title' => 'Minyak Goreng 2L', 'description' => 'Minyak goreng sawit berkualitas', 'buy_price' => 28000, 'sell_price' => 38000, 'stock' => 90, 'image_url' => 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=300&h=300&fit=crop'],
            ['category' => 'Bumbu & Rempah', 'barcode' => 'BMB-0003', 'title' => 'Gula Pasir 1kg', 'description' => 'Gula pasir putih premium', 'buy_price' => 14000, 'sell_price' => 18000, 'stock' => 100, 'image_url' => 'https://images.unsplash.com/photo-1581622558663-b2e33377dfb2?w=300&h=300&fit=crop'],

            // Perawatan Tubuh
            ['category' => 'Perawatan Tubuh', 'barcode' => 'PRW-0001', 'title' => 'Sabun Lifebuoy 85g', 'description' => 'Sabun mandi antibakteri', 'buy_price' => 4000, 'sell_price' => 6500, 'stock' => 150, 'image_url' => 'https://images.unsplash.com/photo-1600857062241-98e5dba7f214?w=300&h=300&fit=crop'],
            ['category' => 'Perawatan Tubuh', 'barcode' => 'PRW-0002', 'title' => 'Shampoo Pantene 170ml', 'description' => 'Shampoo anti rontok', 'buy_price' => 22000, 'sell_price' => 32000, 'stock' => 60, 'image_url' => 'https://images.unsplash.com/photo-1631729371254-42c2892f0e6e?w=300&h=300&fit=crop'],
            ['category' => 'Perawatan Tubuh', 'barcode' => 'PRW-0003', 'title' => 'Pasta Gigi Pepsodent 190g', 'description' => 'Pasta gigi pencegah gigi berlubang', 'buy_price' => 12000, 'sell_price' => 18000, 'stock' => 100, 'image_url' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=300&h=300&fit=crop'],

            // Kebutuhan Rumah
            ['category' => 'Kebutuhan Rumah', 'barcode' => 'RMH-0001', 'title' => 'Tisu Paseo 250 Sheet', 'description' => 'Tisu wajah lembut dan kuat', 'buy_price' => 15000, 'sell_price' => 22000, 'stock' => 80, 'image_url' => 'https://images.unsplash.com/photo-1584556812952-905ffd0c611a?w=300&h=300&fit=crop'],
            ['category' => 'Kebutuhan Rumah', 'barcode' => 'RMH-0002', 'title' => 'Sabun Cuci Piring 800ml', 'description' => 'Sabun cuci piring anti lemak', 'buy_price' => 12000, 'sell_price' => 18000, 'stock' => 90, 'image_url' => 'https://images.unsplash.com/photo-1585441695325-21557ab93f7e?w=300&h=300&fit=crop'],
            ['category' => 'Kebutuhan Rumah', 'barcode' => 'RMH-0003', 'title' => 'Pewangi Pakaian 900ml', 'description' => 'Pelembut dan pewangi pakaian', 'buy_price' => 18000, 'sell_price' => 26000, 'stock' => 70, 'image_url' => 'https://images.unsplash.com/photo-1626806819282-2c1dc01a5e0c?w=300&h=300&fit=crop'],
        ]);

        return $products->map(function ($product) use ($categories) {
            $category = $categories->get($product['category']);

            // Download product image
            $slug = Str::slug($product['title']);
            $image = $this->downloadImage(
                $product['image_url'],
                'products',
                'prod-'.$slug
            );

            $createdProduct = Product::create([
                'category_id' => $category?->id,
                'image' => $image ?? 'default.jpg',
                'barcode' => $product['barcode'],
                'title' => $product['title'],
                'description' => $product['description'],
                'buy_price' => $product['buy_price'],
                'sell_price' => $product['sell_price'],
                'stock' => $product['stock'],
            ]);

            $this->seedProductUnits($createdProduct, $product);

            return $createdProduct;
        })->keyBy('barcode');
    }

    private function seedProductUnits(Product $product, array $blueprint): void
    {
        foreach ($this->productUnitBlueprints($blueprint) as $unit) {
            $product->units()->create([
                'label' => $unit['label'],
                'conversion_qty' => $unit['conversion_qty'],
                'is_base_unit' => $unit['is_base_unit'] ?? false,
                'sell_price' => $unit['sell_price'],
                'buy_price' => $unit['buy_price'],
                'barcode' => $unit['barcode'],
            ]);
        }
    }

    private function productUnitBlueprints(array $product): array
    {
        $units = [
            [
                'label' => $this->baseUnitLabel($product),
                'conversion_qty' => 1,
                'is_base_unit' => true,
                'sell_price' => $product['sell_price'],
                'buy_price' => $product['buy_price'],
                'barcode' => $product['barcode'],
            ],
        ];

        $bulkUnits = [
            'MNM-0001' => [['label' => 'dus', 'conversion_qty' => 24, 'sell_price' => 112000, 'buy_price' => 72000, 'barcode' => 'MNM-0001-DUS']],
            'MNM-0002' => [['label' => 'krat', 'conversion_qty' => 24, 'sell_price' => 135000, 'buy_price' => 96000, 'barcode' => 'MNM-0002-KRT']],
            'SNK-0003' => [['label' => 'dus', 'conversion_qty' => 40, 'sell_price' => 132000, 'buy_price' => 100000, 'barcode' => 'SNK-0003-DUS']],
            'SSU-0001' => [['label' => 'karton', 'conversion_qty' => 12, 'sell_price' => 240000, 'buy_price' => 192000, 'barcode' => 'SSU-0001-KTN']],
            'RTI-0002' => [['label' => 'lusin', 'conversion_qty' => 12, 'sell_price' => 90000, 'buy_price' => 60000, 'barcode' => 'RTI-0002-LSN']],
            'BMB-0001' => [['label' => 'dus', 'conversion_qty' => 12, 'sell_price' => 285000, 'buy_price' => 216000, 'barcode' => 'BMB-0001-DUS']],
            'BMB-0002' => [['label' => 'karton', 'conversion_qty' => 6, 'sell_price' => 220000, 'buy_price' => 168000, 'barcode' => 'BMB-0002-KTN']],
            'BMB-0003' => [['label' => 'karung 25kg', 'conversion_qty' => 25, 'sell_price' => 430000, 'buy_price' => 350000, 'barcode' => 'BMB-0003-KRG']],
            'PRW-0001' => [['label' => 'pack', 'conversion_qty' => 6, 'sell_price' => 37000, 'buy_price' => 24000, 'barcode' => 'PRW-0001-PCK']],
            'RMH-0001' => [['label' => 'karton', 'conversion_qty' => 12, 'sell_price' => 250000, 'buy_price' => 180000, 'barcode' => 'RMH-0001-KTN']],
        ];

        return array_merge($units, $bulkUnits[$product['barcode']] ?? []);
    }

    private function baseUnitLabel(array $product): string
    {
        if (Str::contains($product['title'], ['Aqua', 'Teh Botol', 'Jus', 'Ultra Milk', 'Yogurt', 'Kecap', 'Minyak', 'Shampoo', 'Sabun Cuci', 'Pewangi'])) {
            return 'botol';
        }

        if (Str::contains($product['title'], ['Gula Pasir'])) {
            return 'kg';
        }

        return 'pcs';
    }

    /**
     * Seed historical transactions, transaction details, and profits.
     */
    private function seedTransactions(Collection $customers, Collection $products): void
    {
        $cashier = User::where('email', 'cashier@gmail.com')->first() ?? User::first();

        if (! $cashier) {
            return;
        }

        $blueprints = [
            [
                'customer' => 'Andi Nugraha',
                'discount' => 5000,
                'cash' => 100000,
                'items' => [
                    ['barcode' => 'MNM-0001', 'unit_barcode' => 'MNM-0001-DUS', 'qty' => 1],
                    ['barcode' => 'SNK-0001', 'qty' => 2],
                    ['barcode' => 'RTI-0001', 'qty' => 1],
                ],
            ],
            [
                'customer' => 'Bunga Maharani',
                'discount' => 0,
                'cash' => 150000,
                'items' => [
                    ['barcode' => 'SSU-0001', 'unit_barcode' => 'SSU-0001-KTN', 'qty' => 1],
                    ['barcode' => 'RTI-0002', 'qty' => 3],
                    ['barcode' => 'PRW-0001', 'qty' => 2],
                ],
            ],
            [
                'customer' => 'Cici Amelia',
                'discount' => 10000,
                'cash' => 200000,
                'items' => [
                    ['barcode' => 'MKN-0002', 'qty' => 2],
                    ['barcode' => 'BMB-0002', 'qty' => 1],
                    ['barcode' => 'RMH-0001', 'qty' => 2],
                ],
            ],
            [
                'customer' => 'Davin Pradipta',
                'discount' => 0,
                'cash' => 80000,
                'items' => [
                    ['barcode' => 'MNM-0003', 'qty' => 2],
                    ['barcode' => 'SNK-0003', 'unit_barcode' => 'SNK-0003-DUS', 'qty' => 0.5],
                    ['barcode' => 'SSU-0002', 'qty' => 2],
                ],
            ],
            [
                'customer' => 'Fitri Lestari',
                'discount' => 15000,
                'cash' => 250000,
                'items' => [
                    ['barcode' => 'PRW-0002', 'qty' => 1],
                    ['barcode' => 'BMB-0001', 'unit_barcode' => 'BMB-0001-DUS', 'qty' => 1],
                    ['barcode' => 'MKN-0003', 'qty' => 2],
                    ['barcode' => 'RMH-0003', 'qty' => 1],
                ],
            ],
            [
                'customer' => null,
                'discount' => 0,
                'cash' => 50000,
                'items' => [
                    ['barcode' => 'MNM-0002', 'qty' => 2],
                    ['barcode' => 'SNK-0002', 'qty' => 1],
                ],
            ],
        ];

        foreach ($blueprints as $blueprint) {
            $customer = $blueprint['customer']
                ? $customers->get($blueprint['customer'])
                : null;

            $items = collect($blueprint['items'])
                ->map(function ($item) use ($products) {
                    $product = $products->get($item['barcode']);

                    if (! $product) {
                        return null;
                    }

                    $unit = $this->findProductUnit($product, $item['unit_barcode'] ?? $item['barcode']);
                    $qty = (float) $item['qty'];
                    $baseQty = $qty * (float) $unit->conversion_qty;
                    $lineTotal = (int) round($unit->sell_price * $qty);

                    return [
                        'product' => $product,
                        'unit' => $unit,
                        'qty' => $qty,
                        'base_qty' => $baseQty,
                        'line_total' => $lineTotal,
                        'profit' => (int) round(($unit->sell_price - $unit->buy_price) * $qty),
                    ];
                })
                ->filter();

            if ($items->isEmpty()) {
                continue;
            }

            $discount = max(0, $blueprint['discount']);
            $gross = $items->sum('line_total');
            $grandTotal = max(0, $gross - $discount);
            $cashPaid = max($grandTotal, $blueprint['cash']);
            $change = $cashPaid - $grandTotal;

            $transaction = Transaction::create([
                'cashier_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'invoice' => 'TRX-'.Str::upper(Str::random(8)),
                'cash' => $cashPaid,
                'change' => $change,
                'discount' => $discount,
                'grand_total' => $grandTotal,
            ]);

            foreach ($items as $item) {
                $detail = new TransactionDetail([
                    'product_id' => $item['product']->id,
                    'qty' => $item['qty'],
                    'base_unit_price' => $item['product']->sell_price,
                    'unit_price' => $item['unit']->sell_price,
                    'unit_buy_price' => $item['unit']->buy_price,
                    'price' => $item['line_total'],
                ]);

                $detail->forceFill([
                    'product_unit_id' => $item['unit']->id,
                    'unit_label' => $item['unit']->label,
                    'unit_conversion_qty' => $item['unit']->conversion_qty,
                ]);

                $transaction->details()->save($detail);

                $transaction->profits()->create([
                    'total' => $item['profit'],
                ]);

                $item['product']->decrement('stock', $item['base_qty']);
            }
        }
    }

    private function findProductUnit(Product $product, string $barcode): ProductUnit
    {
        return ProductUnit::query()
            ->where('product_id', $product->id)
            ->where('barcode', $barcode)
            ->first()
            ?? ProductUnit::query()
                ->where('product_id', $product->id)
                ->where('is_base_unit', true)
                ->first();
    }

    /**
     * Seed receivables and their payments.
     */
    private function seedReceivables(Collection $customers): void
    {
        $cashier = User::where('email', 'cashier@gmail.com')->first() ?? User::first();

        $sourceTransactions = Transaction::with('customer')
            ->whereNotNull('customer_id')
            ->take(3)
            ->get();

        foreach ($sourceTransactions as $index => $transaction) {
            $paid = match ($index) {
                0 => (float) ($transaction->grand_total * 0.4),
                1 => (float) ($transaction->grand_total * 0.7),
                default => 0,
            };

            $status = $paid <= 0
                ? 'unpaid'
                : ($paid >= $transaction->grand_total ? 'paid' : 'partial');

            $receivable = Receivable::create([
                'customer_id' => $transaction->customer_id,
                'transaction_id' => $transaction->id,
                'invoice' => 'RCV-'.$transaction->invoice,
                'total' => $transaction->grand_total,
                'paid' => $paid,
                'due_date' => now()->addDays(($index + 1) * 7)->toDateString(),
                'status' => $status,
                'note' => 'Piutang dari transaksi penjualan '.$transaction->invoice,
            ]);

            if ($paid > 0) {
                ReceivablePayment::create([
                    'receivable_id' => $receivable->id,
                    'paid_at' => now()->subDays(2 + $index)->toDateString(),
                    'amount' => $paid,
                    'method' => 'cash',
                    'user_id' => $cashier?->id,
                    'note' => 'Pembayaran awal piutang',
                ]);
            }

            $transaction->update([
                'payment_method' => 'credit',
                'payment_status' => $status === 'paid' ? 'paid' : 'unpaid',
                'cash' => (int) $paid,
                'change' => 0,
            ]);
        }

        $manualReceivables = [
            [
                'customer' => 'Gina Putri',
                'invoice' => 'RCV-MANUAL-001',
                'total' => 185000,
                'paid' => 50000,
                'due_date' => now()->addDays(10)->toDateString(),
                'status' => 'partial',
                'note' => 'Piutang manual untuk pembelian grosir bulanan',
            ],
            [
                'customer' => 'Hendra Wijaya',
                'invoice' => 'RCV-MANUAL-002',
                'total' => 275000,
                'paid' => 0,
                'due_date' => now()->subDays(3)->toDateString(),
                'status' => 'overdue',
                'note' => 'Piutang manual yang sudah melewati jatuh tempo',
            ],
        ];

        foreach ($manualReceivables as $item) {
            $customer = $customers->get($item['customer']);

            if (! $customer) {
                continue;
            }

            $receivable = Receivable::create([
                'customer_id' => $customer->id,
                'invoice' => $item['invoice'],
                'total' => $item['total'],
                'paid' => $item['paid'],
                'due_date' => $item['due_date'],
                'status' => $item['status'],
                'note' => $item['note'],
            ]);

            if ($item['paid'] > 0) {
                ReceivablePayment::create([
                    'receivable_id' => $receivable->id,
                    'paid_at' => now()->subDays(1)->toDateString(),
                    'amount' => $item['paid'],
                    'method' => 'bank_transfer',
                    'user_id' => $cashier?->id,
                    'note' => 'Pembayaran sebagian piutang manual',
                ]);
            }
        }
    }

    /**
     * Seed payables and their payments.
     */
    private function seedPayables(Collection $suppliers): void
    {
        $cashier = User::where('email', 'cashier@gmail.com')->first() ?? User::first();

        $blueprints = [
            [
                'supplier' => 'PT Sumber Pangan Nusantara',
                'document_number' => 'PYB-0001',
                'total' => 450000,
                'paid' => 150000,
                'due_date' => now()->addDays(14)->toDateString(),
                'status' => 'partial',
                'note' => 'Pengadaan stok minuman dan snack',
            ],
            [
                'supplier' => 'CV Makmur Jaya Distribusi',
                'document_number' => 'PYB-0002',
                'total' => 720000,
                'paid' => 0,
                'due_date' => now()->addDays(21)->toDateString(),
                'status' => 'unpaid',
                'note' => 'Pengadaan produk rumah tangga',
            ],
            [
                'supplier' => 'PT Segar Sentosa Abadi',
                'document_number' => 'PYB-0003',
                'total' => 390000,
                'paid' => 390000,
                'due_date' => now()->subDays(2)->toDateString(),
                'status' => 'paid',
                'note' => 'Pembelian produk susu dan frozen food',
            ],
            [
                'supplier' => 'UD Berkah Retail Grosir',
                'document_number' => 'PYB-0004',
                'total' => 510000,
                'paid' => 100000,
                'due_date' => now()->subDays(5)->toDateString(),
                'status' => 'overdue',
                'note' => 'Pengadaan barang campuran jatuh tempo',
            ],
        ];

        foreach ($blueprints as $item) {
            $supplier = $suppliers->get($item['supplier']);

            if (! $supplier) {
                continue;
            }

            $payable = Payable::create([
                'supplier_id' => $supplier->id,
                'document_number' => $item['document_number'],
                'total' => $item['total'],
                'paid' => $item['paid'],
                'due_date' => $item['due_date'],
                'status' => $item['status'],
                'note' => $item['note'],
            ]);

            if ($item['paid'] > 0) {
                PayablePayment::create([
                    'payable_id' => $payable->id,
                    'paid_at' => now()->subDays(3)->toDateString(),
                    'amount' => $item['paid'],
                    'method' => 'bank_transfer',
                    'user_id' => $cashier?->id,
                    'note' => 'Pembayaran hutang supplier',
                ]);
            }
        }
    }
}

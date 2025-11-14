<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Profit;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
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
        TransactionDetail::truncate();
        Profit::truncate();
        Transaction::truncate();
        Product::truncate();
        Category::truncate();
        Customer::truncate();

        Schema::enableForeignKeyConstraints();

        $placeholders = $this->ensurePlaceholderImages();

        $customers = $this->seedCustomers();
        $categories = $this->seedCategories($placeholders['category']);
        $products = $this->seedProducts($categories, $placeholders['product']);

        $this->seedTransactions($customers, $products);
    }

    /**
     * Ensure we have at least one placeholder image stored under the public disk.
     */
    private function ensurePlaceholderImages(): array
    {
        $source = public_path('assets/photo/auth.jpg');

        $categoryFile = 'sample-category.jpg';
        $productFile = 'sample-product.jpg';

        if (file_exists($source)) {
            if (!Storage::disk('public')->exists('category/' . $categoryFile)) {
                Storage::disk('public')->put('category/' . $categoryFile, file_get_contents($source));
            }

            if (!Storage::disk('public')->exists('products/' . $productFile)) {
                Storage::disk('public')->put('products/' . $productFile, file_get_contents($source));
            }
        }

        return [
            'category' => $categoryFile,
            'product' => $productFile,
        ];
    }

    /**
     * Seed master customers.
     */
    private function seedCustomers(): Collection
    {
        $customers = collect([
            ['name' => 'Andi Nugraha', 'no_telp' => '6281211111111', 'address' => 'Jl. Melati No. 21, Bandung'],
            ['name' => 'Bunga Maharani', 'no_telp' => '6281312345678', 'address' => 'Jl. Mawar No. 5, Jakarta'],
            ['name' => 'Cici Amelia', 'no_telp' => '6281512340000', 'address' => 'Jl. Anggrek No. 17, Surabaya'],
            ['name' => 'Davin Pradipta', 'no_telp' => '6285612349911', 'address' => 'Jl. Kenanga No. 2, Yogyakarta'],
            ['name' => 'Eko Saputra', 'no_telp' => '6287712348822', 'address' => 'Jl. Cemara No. 45, Semarang'],
            ['name' => 'Fitri Lestari', 'no_telp' => '6282213345566', 'address' => 'Jl. Sakura No. 7, Medan'],
        ]);

        return $customers
            ->map(fn ($customer) => Customer::create($customer))
            ->keyBy('name');
    }

    /**
     * Seed master categories.
     */
    private function seedCategories(string $image): Collection
    {
        $categories = collect([
            ['name' => 'Beverages', 'description' => 'Aneka minuman kemasan dingin dan panas'],
            ['name' => 'Snacks', 'description' => 'Camilan kemasan siap saji'],
            ['name' => 'Fresh Produce', 'description' => 'Buah dan sayuran segar pilihan'],
            ['name' => 'Household', 'description' => 'Kebutuhan rumah tangga harian'],
            ['name' => 'Personal Care', 'description' => 'Produk kebersihan dan perawatan diri'],
        ]);

        return $categories
            ->map(fn ($category) => Category::create([
                'name' => $category['name'],
                'description' => $category['description'],
                'image' => $image,
            ]))
            ->keyBy('name');
    }

    /**
     * Seed products mapped to categories.
     */
    private function seedProducts(Collection $categories, string $image): Collection
    {
        $products = collect([
            ['category' => 'Beverages', 'barcode' => 'BRG-0001', 'title' => 'Cold Brew Coffee 250ml', 'description' => 'Kopi Arabica rumahan dengan rasa manis alami.', 'buy_price' => 25000, 'sell_price' => 35000, 'stock' => 80],
            ['category' => 'Beverages', 'barcode' => 'BRG-0002', 'title' => 'Thai Tea Literan', 'description' => 'Thai tea original dengan susu kental manis.', 'buy_price' => 30000, 'sell_price' => 42000, 'stock' => 60],
            ['category' => 'Snacks', 'barcode' => 'BRG-0003', 'title' => 'Keripik Singkong Balado', 'description' => 'Keripik singkong renyah rasa balado pedas manis.', 'buy_price' => 12000, 'sell_price' => 18000, 'stock' => 150],
            ['category' => 'Snacks', 'barcode' => 'BRG-0004', 'title' => 'Granola Bar Cokelat', 'description' => 'Granola bar sehat dengan kacang-kacangan premium.', 'buy_price' => 15000, 'sell_price' => 22000, 'stock' => 100],
            ['category' => 'Fresh Produce', 'barcode' => 'BRG-0005', 'title' => 'Paket Salad Buah', 'description' => 'Campuran buah segar potong siap saji.', 'buy_price' => 20000, 'sell_price' => 32000, 'stock' => 70],
            ['category' => 'Fresh Produce', 'barcode' => 'BRG-0006', 'title' => 'Sayur Organik Mix', 'description' => 'Paket kangkung, bayam, dan selada organik.', 'buy_price' => 18000, 'sell_price' => 27000, 'stock' => 90],
            ['category' => 'Household', 'barcode' => 'BRG-0007', 'title' => 'Sabun Cair Lemon 1L', 'description' => 'Sabun cair anti bakteri aroma lemon segar.', 'buy_price' => 22000, 'sell_price' => 32000, 'stock' => 110],
            ['category' => 'Household', 'barcode' => 'BRG-0008', 'title' => 'Tisu Dapur 2 Ply', 'description' => 'Tisu dapur serbaguna dua lapis.', 'buy_price' => 9000, 'sell_price' => 15000, 'stock' => 200],
            ['category' => 'Personal Care', 'barcode' => 'BRG-0009', 'title' => 'Hand Sanitizer 250ml', 'description' => 'Hand sanitizer food grade non lengket.', 'buy_price' => 17000, 'sell_price' => 25000, 'stock' => 140],
            ['category' => 'Personal Care', 'barcode' => 'BRG-0010', 'title' => 'Shampoo Botani 500ml', 'description' => 'Shampoo botani untuk semua jenis rambut.', 'buy_price' => 28000, 'sell_price' => 40000, 'stock' => 95],
        ]);

        return $products
            ->map(function ($product) use ($categories, $image) {
                $category = $categories->get($product['category']);

                return Product::create([
                    'category_id' => $category?->id,
                    'image' => $image,
                    'barcode' => $product['barcode'],
                    'title' => $product['title'],
                    'description' => $product['description'],
                    'buy_price' => $product['buy_price'],
                    'sell_price' => $product['sell_price'],
                    'stock' => $product['stock'],
                ]);
            })
            ->keyBy('barcode');
    }

    /**
     * Seed historical transactions, transaction details, and profits.
     */
    private function seedTransactions(Collection $customers, Collection $products): void
    {
        $cashier = User::where('email', 'cashier@gmail.com')->first() ?? User::first();

        if (!$cashier) {
            return;
        }

        $blueprints = [
            [
                'customer' => 'Andi Nugraha',
                'discount' => 5000,
                'cash' => 200000,
                'items' => [
                    ['barcode' => 'BRG-0001', 'qty' => 2],
                    ['barcode' => 'BRG-0003', 'qty' => 3],
                ],
            ],
            [
                'customer' => 'Bunga Maharani',
                'discount' => 0,
                'cash' => 150000,
                'items' => [
                    ['barcode' => 'BRG-0005', 'qty' => 2],
                    ['barcode' => 'BRG-0009', 'qty' => 1],
                ],
            ],
            [
                'customer' => 'Fitri Lestari',
                'discount' => 10000,
                'cash' => 180000,
                'items' => [
                    ['barcode' => 'BRG-0007', 'qty' => 2],
                    ['barcode' => 'BRG-0008', 'qty' => 4],
                    ['barcode' => 'BRG-0010', 'qty' => 1],
                ],
            ],
            [
                'customer' => null,
                'discount' => 0,
                'cash' => 75000,
                'items' => [
                    ['barcode' => 'BRG-0004', 'qty' => 1],
                    ['barcode' => 'BRG-0006', 'qty' => 1],
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

                    if (!$product) {
                        return null;
                    }

                    $lineTotal = $product->sell_price * $item['qty'];

                    return [
                        'product' => $product,
                        'qty' => $item['qty'],
                        'line_total' => $lineTotal,
                        'profit' => ($product->sell_price - $product->buy_price) * $item['qty'],
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
                'invoice' => 'TRX-' . Str::upper(Str::random(8)),
                'cash' => $cashPaid,
                'change' => $change,
                'discount' => $discount,
                'grand_total' => $grandTotal,
            ]);

            foreach ($items as $item) {
                $transaction->details()->create([
                    'product_id' => $item['product']->id,
                    'qty' => $item['qty'],
                    'price' => $item['line_total'],
                ]);

                $transaction->profits()->create([
                    'total' => $item['profit'],
                ]);

                $item['product']->decrement('stock', $item['qty']);
            }
        }
    }
}

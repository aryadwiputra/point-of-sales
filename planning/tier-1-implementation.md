# Tier 1 Implementation Plan — v2.1

Target: 4 modul, ~12-16 hari effort.

---

## 1. PPN/PPh Tax Management

### Objective
Dukungan PPN 11% (configurable) pada transaksi, inclusive/exclusive tax mode, NPWP/NIB toko, dan faktur pajak sederhana.

### Why
Wajib untuk UMKM dengan omzet > 4,8M/thn. Invoice tanpa PPN tidak sah secara perpajakan. Tanpa ini, sistem tidak bisa dipakai oleh toko yang sudah berNPWP.

### Definitions

| Term | Meaning |
|------|---------|
| PPN | Pajak Pertambahan Nilai (11% standar Indonesia) |
| Tax Exclusive | Harga produk belum termasuk PPN. PPN ditambahkan di grand total |
| Tax Inclusive | Harga produk sudah termasuk PPN. PPN dipisah untuk reporting |
| NPWP | Nomor Pokok Wajib Pajak — identitas pajak toko |
| Faktur Pajak | Dokumen resmi yang mencatat PPN |

---

### Phase 1.1 — Database & Settings

#### Migration: `add_tax_columns_to_products`

```php
Schema::table('products', function (Blueprint $table) {
    $table->enum('tax_type', ['exclusive', 'inclusive'])->default('exclusive');
    $table->decimal('tax_rate', 5, 2)->default(11.00);
});
```

#### Migration: `add_tax_columns_to_transactions`

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->decimal('tax_rate', 5, 2)->nullable()->default(11.00);
    $table->bigInteger('tax_total')->default(0);
    $table->string('customer_npwp', 20)->nullable();
});
```

#### Migration: `add_tax_settings`

Seeder update atau migration:
```php
// settings table — value + description
['key' => 'store_npwp', 'value' => '', 'description' => 'NPWP Toko'],
['key' => 'store_nib',  'value' => '', 'description' => 'NIB Toko'],
['key' => 'tax_default_rate', 'value' => '11.00', 'description' => 'Default tarif PPN'],
```

#### Model Changes: `Product`

```php
protected function casts(): array
{
    return [
        'tax_rate' => 'decimal:2',
        'tax_type' => 'string',
        // ...existing
    ];
}
```

No custom accessor needed — tax rate stored as decimal.

#### Model Changes: `Transaction`

```php
// casts
'tax_rate' => 'decimal:2',
'tax_total' => 'integer',

// fillable
'tax_rate', 'tax_total', 'customer_npwp',
```

#### Settings UI: `Settings/Store.jsx`

Tambah field:
- NPWP (text, format `XX.XXX.XXX.X-XXX.XXX`)
- NIB (text)
- Default tarif PPN (number, 2 decimal)

Backend: `SettingController.updateStoreProfile()` — validasi + simpan.

---

### Phase 1.2 — Tax Calculation Engine

#### Service: `app/Services/TaxService.php`

```php
class TaxService
{
    /**
     * Calculate tax for a single line item
     *
     * @param int $lineTotal  Price after promotion discount
     * @param string $taxType  exclusive|inclusive
     * @param float $taxRate   Percentage (11.00 = 11%)
     * @return array{tax_amount: int, line_total_before_tax: int, line_total_after_tax: int}
     */
    public function calculateLineItem(int $lineTotal, string $taxType, float $taxRate): array
    {
        if ($taxRate <= 0) {
            return [
                'tax_amount' => 0,
                'line_total_before_tax' => $lineTotal,
                'line_total_after_tax' => $lineTotal,
            ];
        }

        if ($taxType === 'inclusive') {
            // Price includes tax: tax = total - (total / (1 + rate/100))
            $taxAmount = (int) round($lineTotal - ($lineTotal / (1 + $taxRate / 100)));
            $beforeTax = $lineTotal - $taxAmount;
        } else {
            // Price excludes tax: tax = total * rate/100
            $taxAmount = (int) round($lineTotal * $taxRate / 100);
            $beforeTax = $lineTotal;
        }

        return [
            'tax_amount' => $taxAmount,
            'line_total_before_tax' => $beforeTax,
            'line_total_after_tax' => $beforeTax + $taxAmount,
        ];
    }

    /**
     * Calculate total tax for the entire transaction
     *
     * @param array $items  Each item: {line_total, tax_type, tax_rate}
     * @return array{tax_total: int, items: array}
     */
    public function calculateTransactionTax(array $items): array
    {
        $taxTotal = 0;
        $result = [];

        foreach ($items as $item) {
            $taxResult = $this->calculateLineItem(
                $item['line_total'],
                $item['tax_type'] ?? 'exclusive',
                (float) ($item['tax_rate'] ?? 11.00)
            );
            $taxTotal += $taxResult['tax_amount'];
            $result[] = [
                ...$item,
                ...$taxResult,
            ];
        }

        return [
            'tax_total' => $taxTotal,
            'items' => $result,
        ];
    }
}
```

#### Integration: `PricingService` & `LoyaltyService`

Di `previewCheckout()` atau `previewCart()`:

```php
// After calculating grand_total, inject tax
$taxService = app(TaxService::class);
$taxResult = $taxService->calculateTransactionTax($items);

return [
    ...$existingResult,
    'tax_total' => $taxResult['tax_total'],
    'grand_total' => $baseGrandTotal + $taxResult['tax_total'],
];
```

#### Checkout Store: `TransactionController.store()`

Tambah ke transaction create:
```php
'tax_rate' => data_get($checkoutPreview, 'summary.tax_rate', 11.00),
'tax_total' => data_get($checkoutPreview, 'summary.tax_total', 0),
```

---

### Phase 1.3 — Frontend Display

#### POS Checkout: `PaymentPanel.jsx`

Tambah line sebelum grand total:
```
Subtotal          Rp 100.000
Diskon Promo      Rp  10.000
PPN 11%           Rp   9.900
────────────────────────────
Grand Total       Rp  99.900
```

Jika `tax_type===inclusive`, tampilkan:
```
Harga Termasuk PPN
PPN 11%           Rp  9.900
```

#### Transaction Print: `Print.jsx`

Tambah baris PPN di preview invoice dan receipt.

#### PDF Invoice: `resources/views/pdf/invoice.blade.php`

Tambah:
```html
<tr>
    <td>PPN {{ number_format($transaction->tax_rate, 0) }}%</td>
    <td class="text-right">{{ number_format($transaction->tax_total, 0, ',', '.') }}</td>
</tr>
```

#### PDF Receipt & Thermal Receipt

Sama — tambah baris PPN sebelum total.

#### Store Profile Settings

Tambah field NPWP + NIB di `Settings/Store.jsx` dengan validasi format.

#### Transaction History: kolom NPWP (optional, jika diisi)

---

### Phase 1.4 — Tax Report

#### `TaxReportController` (optional v2.1.1)

- Filter by date range
- Summary: total penjualan, total PPN, jumlah faktur
- Export ke Excel
- Route: `/reports/tax`

---

### Files Affected

| Layer | Files |
|-------|-------|
| Migration | `add_tax_columns_to_products`, `add_tax_columns_to_transactions`, `add_tax_settings` — 3 baru |
| Model | `Product` (casts), `Transaction` (casts + fillable) — 2 modif |
| Service | `TaxService.php` — 1 baru |
| Service modif | `PricingService.php`, `LoyaltyService.php` — 2 modif |
| Controller | `TransactionController.php`, `SettingController.php` — 2 modif |
| Form Request | `ProductController` store/update — tambah validasi |
| Frontend | `Product/Create.jsx`, `Product/Edit.jsx`, `Settings/Store.jsx`, `POS/PaymentPanel.jsx`, `Transactions/Print.jsx` — 5 modif |
| PDF | `invoice.blade.php`, `receipt.blade.php`, `shipping.blade.php` — 3 modif |
| Test | `TaxCalculationTest.php` — 1 baru |
| **Total** | **~20 files** |

### Permission Impact
None — semua user bisa lihat/mengisi.

### Rollout
- Phase 1.1-1.3 bisa rilis bersama (core feature)
- Phase 1.4 (tax report) bisa menyusul

---

## 2. Import/Export CSV + Excel

### Objective
Import master data dari spreadsheet. Export laporan ke Excel.

### Why
Dealbreaker adopsi. UMKM punya 500-2000+ produk dari supplier, input 1/1 tidak feasible.

---

### Phase 2.1 — Install Dependency

```bash
composer require maatwebsite/laravel-excel
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"
```

### Phase 2.2 — Export Classes

#### `app/Exports/ProductsExport.php`

```php
class ProductsExport implements FromCollection, WithHeadings, WithMapping
{
    public function headings(): array
    {
        return ['Barcode', 'SKU', 'Nama', 'Kategori', 'Harga Beli', 'Harga Jual', 'Stok', 'Min Stok', 'Tipe Pajak', 'Tarif Pajak'];
    }

    public function map($product): array
    {
        return [
            $product->barcode,
            $product->sku,
            $product->title,
            $product->category?->name,
            $product->buy_price,
            $product->sell_price,
            $product->stock,
            $product->min_stock,
            $product->tax_type,
            $product->tax_rate,
        ];
    }

    public function collection()
    {
        return Product::with('category')->get();
    }
}
```

#### Export classes lain:
- `CustomersExport.php` — nama, telepon, alamat, wilayah
- `TransactionsExport.php` — invoice, tanggal, customer, grand_total, status
- `StockMutationExport.php` — produk, tipe, qty, before/after, warehouse, tanggal

### Phase 2.3 — Import Classes

#### `app/Imports/ProductsImport.php`

```php
class ProductsImport implements ToModel, WithValidation, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    public function model(array $row)
    {
        $category = Category::firstOrCreate(['name' => $row['kategori'] ?? 'Umum']);

        return new Product([
            'barcode' => $row['barcode'],
            'sku' => $row['sku'] ?? $row['barcode'],
            'title' => $row['nama'],
            'category_id' => $category->id,
            'buy_price' => (int) $row['harga_beli'],
            'sell_price' => (int) $row['harga_jual'],
            'stock' => (int) ($row['stok'] ?? 0),
            'min_stock' => (int) ($row['min_stok'] ?? 0),
            'tax_type' => $row['tipe_pajak'] ?? 'exclusive',
            'tax_rate' => (float) ($row['tarif_pajak'] ?? 11.00),
        ]);
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'unique:products,barcode'],
            'nama' => ['required', 'string', 'max:255'],
            'harga_beli' => ['nullable', 'numeric', 'min:0'],
            'harga_jual' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function batchSize(): int
    {
        return 100;
    }
}
```

#### Import classes lain:
- `CustomersImport.php` — nama, telepon, alamat

### Phase 2.4 — Controllers & Routes

#### `ImportController`

```php
Route::post('/import/products', [ImportController::class, 'importProducts'])->name('import.products');
Route::post('/import/customers', [ImportController::class, 'importCustomers'])->name('import.customers');
Route::get('/import/template/{type}', [ImportController::class, 'downloadTemplate'])->name('import.template');
```

**Import flow:**
1. POST file (xlsx/csv) + pilih tipe
2. Queue job untuk proses background
3. Return result: success count, error rows
4. Frontend: progress bar + error download

#### `ExportController`

```php
Route::get('/export/products', [ExportController::class, 'exportProducts'])->name('export.products');
Route::get('/export/customers', [ExportController::class, 'exportCustomers'])->name('export.customers');
Route::get('/export/transactions', [ExportController::class, 'exportTransactions'])->name('export.transactions');
Route::get('/export/stock-mutations', [ExportController::class, 'exportStockMutations'])->name('export.stock-mutations');
```

**Export flow:**
1. GET dengan filter params
2. Stream download Excel file

### Phase 2.5 — Frontend

#### Import Modal

Di `Products/Index.jsx` dan `Customers/Index.jsx` — tombol "Import" buka modal:
1. Download template button
2. File upload (drag-and-drop)
3. Submit → loading → result

#### Export Button

Di halaman yang sama — tombol "Download Excel":
- Submit current filters via GET
- Stream file download

#### Report Export

Di `Reports/Sales.jsx` dan `Reports/Profit.jsx` — tombol "Download Excel" dengan filter aktif.

---

### Files Affected

| Layer | Files |
|-------|-------|
| Install | `composer require maatwebsite/laravel-excel` |
| Export | `ProductsExport.php`, `CustomersExport.php`, `TransactionsExport.php`, `StockMutationExport.php` — 4 baru |
| Import | `ProductsImport.php`, `CustomersImport.php` — 2 baru |
| Controller | `ImportController.php`, `ExportController.php` — 2 baru |
| Routes | Tambah 7 route |
| Frontend | `Products/Index.jsx`, `Customers/Index.jsx`, `Reports/Sales.jsx`, `Reports/Profit.jsx` — 4 modif |
| Permission | `PermissionSeeder.php` — 4 permission baru |
| Role | `RoleSeeder.php` — 2 role baru |
| Test | `ImportTest.php`, `ExportTest.php` — 2 baru |
| **Total** | **~18 files** |

### Permission Impact

```php
$create('products-import');
$create('products-export');
$create('customers-import');
$create('customers-export');
```

### Rollout
Release bersamaan semua — fitur saling terkait.

---

## 3. Unit Conversion (Multi-Satuan)

### Objective
Satu produk dalam multiple satuan dengan konversi stok otomatis.

### Why
Standar POS Indonesia. Tanpa ini, produk seperti beras (kg/karung), sabun (pcs/karton), atau minyak (liter/gallon) tidak bisa di-handle secara akurat.

---

### Phase 3.1 — Database

#### Migration: `create_units_table`

```php
Schema::create('units', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10)->unique();        // PCS, BOX, KTG, KG, LTR
    $table->string('name', 50);                  // Pieces, Box, Karton, Kilogram, Liter
    $table->string('symbol', 10);               // pcs, box, karton, kg, ltr
});
```

Seed default units: PCS, BOX, KARTON, KG, LITER, METER, PAK, DUS

#### Migration: `create_product_units_table`

```php
Schema::create('product_units', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('unit_id')->constrained();
    $table->boolean('is_base')->default(false);
    $table->decimal('conversion_factor', 15, 4)->default(1);  // 1 box = 12 pcs
    $table->bigInteger('buy_price');                           // Harga beli per unit ini
    $table->bigInteger('sell_price');                          // Harga jual per unit ini
    $table->string('barcode', 100)->nullable();               // Barcode spesifik per unit
    $table->string('sku_suffix', 20)->nullable();              // SKU suffix: PROD-BOX
    $table->timestamps();

    $table->unique(['product_id', 'unit_id']);
});
```

#### Migration: `add_unit_columns_to_carts`

```php
Schema::table('carts', function (Blueprint $table) {
    $table->foreignId('unit_id')->nullable()->constrained('units');
    $table->decimal('conversion_factor', 15, 4)->default(1);
});
```

#### Migration: `add_unit_columns_to_transaction_details`

```php
Schema::table('transaction_details', function (Blueprint $table) {
    $table->foreignId('unit_id')->nullable()->constrained('units');
    $table->decimal('conversion_factor', 15, 4)->default(1);
});
```

#### Model: `Unit`

```php
class Unit extends Model
{
    protected $fillable = ['code', 'name', 'symbol'];
}
```

#### Model: `ProductUnit` (Pivot)

```php
class ProductUnit extends Pivot
{
    public $incrementing = true;
    protected $table = 'product_units';

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
            'conversion_factor' => 'decimal:4',
            'buy_price' => 'integer',
            'sell_price' => 'integer',
        ];
    }
}
```

#### Model Changes: `Product`

```php
public function units(): BelongsToMany
{
    return $this->belongsToMany(Unit::class, 'product_units')
        ->withPivot(['is_base', 'conversion_factor', 'buy_price', 'sell_price', 'barcode', 'sku_suffix'])
        ->using(ProductUnit::class)
        ->withTimestamps();
}

public function baseUnit(): ?Unit
{
    return $this->units()->wherePivot('is_base', true)->first();
}
```

### Phase 3.2 — Service

#### `app/Services/UnitConversionService.php`

```php
class UnitConversionService
{
    /**
     * Convert qty from one unit to base unit
     */
    public function toBaseUnit(Product $product, Unit $unit, int $qty): int
    {
        $pu = $product->units()->where('unit_id', $unit->id)->first();
        $factor = $pu?->pivot->conversion_factor ?? 1;
        return (int) round($qty * $factor);
    }

    /**
     * Convert base stock to display stock in given unit
     */
    public function fromBaseUnit(Product $product, Unit $unit, int $baseQty): float
    {
        $pu = $product->units()->where('unit_id', $unit->id)->first();
        $factor = $pu?->pivot->conversion_factor ?? 1;
        return $factor > 0 ? $baseQty / $factor : $baseQty;
    }

    /**
     * Get effective price for a given unit
     */
    public function getPrice(Product $product, Unit $unit, string $type = 'sell_price'): int
    {
        $pu = $product->units()->where('unit_id', $unit->id)->first();
        return (int) ($pu?->pivot->{$type} ?? $product->{$type});
    }

    /**
     * Get unit label for display
     */
    public function getUnitLabel(Product $product, ?int $unitId): string
    {
        if (! $unitId) return 'pcs';
        $unit = Unit::find($unitId);
        return $unit?->symbol ?? 'pcs';
    }
}
```

### Phase 3.3 — Controller Changes

#### ProductController — Store/Update

Tambah validasi + relasi sync untuk units:

```php
$request->validate([
    // ...existing fields
    'units' => ['nullable', 'array'],
    'units.*.unit_id' => ['required', 'exists:units,id'],
    'units.*.conversion_factor' => ['required', 'numeric', 'min:0.0001'],
    'units.*.buy_price' => ['required', 'numeric', 'min:0'],
    'units.*.sell_price' => ['required', 'numeric', 'min:0'],
    'units.*.is_base' => ['boolean'],
]);

// Sync pivot
$product->units()->sync(collect($request->units)->mapWithKeys(fn ($u) => [
    $u['unit_id'] => [
        'is_base' => $u['is_base'] ?? false,
        'conversion_factor' => $u['conversion_factor'],
        'buy_price' => $u['buy_price'],
        'sell_price' => $u['sell_price'],
        'barcode' => $u['barcode'] ?? null,
        'sku_suffix' => $u['sku_suffix'] ?? null,
    ],
]));
```

#### TransactionController — addToCart

```php
$unitId = $request->unit_id;
$unit = Unit::findOrFail($unitId);
$conversionFactor = $product->units()->where('unit_id', $unitId)->first()?->pivot->conversion_factor ?? 1;

// Check stock in base unit
$baseQty = app(UnitConversionService::class)->toBaseUnit($product, $unit, $request->qty);

// Check pivot stock
$warehouseProduct = $product->warehouses()->where('warehouse_id', $warehouseId)->first();
$availableBaseStock = $warehouseProduct?->pivot->stock ?? 0;
if ($availableBaseStock < $baseQty) {
    return redirect()->back()->with('error', 'Stok tidak mencukupi.');
}

// Simpan di cart dengan unit info
Cart::create([
    'cashier_id' => auth()->user()->id,
    'warehouse_id' => $warehouseId,
    'product_id' => $request->product_id,
    'unit_id' => $unitId,
    'conversion_factor' => $conversionFactor,
    'qty' => $request->qty,                          // qty in selected unit
    'price' => $unitPrice * $request->qty,            // price in selected unit
]);
```

#### TransactionController — Store

Saat checkout, decrement stok dalam base unit:

```php
foreach ($carts as $cart) {
    $baseQty = $cart->conversion_factor > 1
        ? (int) round($cart->qty * $cart->conversion_factor)
        : $cart->qty;

    ProductWarehouse::where([
        'product_id' => $cart->product_id,
        'warehouse_id' => $warehouseId,
    ])->decrement('stock', $baseQty);

    // Detail transaksi
    $transaction->details()->create([
        // ... existing
        'unit_id' => $cart->unit_id,
        'conversion_factor' => $cart->conversion_factor,
    ]);
}
```

### Phase 3.4 — Frontend

#### Product Form — Tab "Satuan"

Base unit: dropdown (PCS, KG, LTR, dll).
Additional units: table dengan kolom unit, conversion factor, buy price, sell price, barcode.

```
Satuan Dasar: [PCS ▼]
──────────────────────────────────────
Satuan   | Faktor | Harga Beli | Harga Jual | Barcode
─────────|────────|────────────|────────────|─────────
BOX      | 12     | 60,000     | 72,000     | [     ]
KARTON   | 144    | 600,000    | 720,000    | [     ]
```

Validation: total faktor konversi harus >= 1.

#### POS Product Grid

Di `ProductGrid.jsx` — untuk produk dengan multiple units, tambah dropdown unit:
```
[Produk A]           Rp 6.000
Satuan: [PCS ▼] 
```

Saat ganti unit, harga berubah.

#### POS Cart Panel

Tampilkan unit di qty column:
```
Item            Qty    Price
Aqua 600ml      2 box  Rp 24,000
                24 pcs
```

### Phase 3.5 — Stock Mutations

Semua stock mutation tetap di base unit. `stock_before`/`stock_after` selalu dalam base unit.

UI mutation history — tampilkan dalam base unit + label.

---

### Files Affected

| Layer | Files |
|-------|-------|
| Migration | `create_units_table`, `create_product_units_table`, `add_unit_columns_to_carts`, `add_unit_columns_to_transaction_details` — 4 baru |
| Seeder | `UnitSeeder.php` — default units |
| Model | `Unit.php`, `ProductUnit.php` — 2 baru |
| Model modif | `Product.php`, `Cart.php`, `TransactionDetail.php` — 3 modif |
| Service | `UnitConversionService.php` — 1 baru |
| Controller | `ProductController.php`, `TransactionController.php` — 2 modif |
| Frontend | `Products/Create.jsx`, `Products/Edit.jsx`, `POS/ProductGrid.jsx`, `POS/CartPanel.jsx` — 4 modif |
| Test | `UnitConversionTest.php` — 1 baru |
| **Total** | **~18 files** |

### Permission Impact
None.

### Rollout
Bisa dirilis sendiri. Produk existing dianggap unit PCS dengan faktor 1.

---

## 4. Reorder Point + Auto-PO Suggestion

### Objective
Minimum stock configurable per produk, notifikasi, dan auto-suggestion untuk restock.

### Why
Mencegah stock-out. Dashboard + notifikasi yang proaktif.

---

### Phase 4.1 — Database

#### Migration: `add_min_max_stock_to_products`

```php
Schema::table('products', function (Blueprint $table) {
    $table->integer('min_stock')->default(0);
    $table->integer('max_stock')->default(0);
});
```

#### Migration: `add_min_max_stock_to_product_warehouse`

```php
Schema::table('product_warehouse', function (Blueprint $table) {
    $table->integer('min_stock')->default(0);
    $table->integer('max_stock')->default(0);
});
```

### Phase 4.2 — Model Changes

#### Product

```php
public function isLowStock(?int $warehouseId = null): bool
{
    if ($this->min_stock <= 0) return false;

    if ($warehouseId) {
        $pw = $this->warehouses()->where('warehouse_id', $warehouseId)->first();
        return $pw && (int) $pw->pivot->stock <= $this->min_stock;
    }

    return $this->stockTotal() <= $this->min_stock;
}

public function suggestedOrderQty(): int
{
    if ($this->max_stock <= 0 || $this->min_stock <= 0) return 0;
    return max(0, $this->max_stock - $this->stockTotal());
}
```

### Phase 4.3 — Service

#### `app/Services/ReorderService.php`

```php
class ReorderService
{
    /**
     * Get all products that need restocking
     */
    public function getLowStockProducts(?int $warehouseId = null): Collection
    {
        $query = Product::where('min_stock', '>', 0);

        if ($warehouseId) {
            $query->whereHas('warehouses', function ($q) use ($warehouseId) {
                $q->where('product_warehouse.warehouse_id', $warehouseId)
                  ->whereColumn('product_warehouse.stock', '<=', 'products.min_stock');
            });
        } else {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        return $query->orderBy('stock')->limit(20)->get();
    }

    /**
     * Create draft purchase order from low stock products
     */
    public function createDraftPurchaseOrder(Collection $products, int $userId): PurchaseOrder
    {
        $service = app(PurchaseOrderService::class);

        $items = $products->filter(fn ($p) => $p->suggestedOrderQty() > 0)
            ->map(fn ($p) => [
                'product_id' => $p->id,
                'qty_ordered' => $p->suggestedOrderQty(),
                'unit_price' => $p->buy_price,
            ])->values()->toArray();

        return $service->createOrder(
            data: ['notes' => 'Auto-generated from restock suggestion'],
            items: $items,
            userId: $userId
        );
    }
}
```

### Phase 4.4 — Controller Changes

#### DashboardController — Widget

Tambah query di `DashboardController.index()`:

```php
$lowStockProducts = app(ReorderService::class)->getLowStockProducts();

return Inertia::render('Dashboard/Dashboard', [
    // ...existing
    'lowStockProducts' => $lowStockProducts->map(fn ($p) => [
        'id' => $p->id,
        'title' => $p->title,
        'stock' => $p->stockTotal(),
        'min_stock' => $p->min_stock,
        'suggested_order_qty' => $p->suggestedOrderQty(),
    ]),
]);
```

#### HandleInertiaRequests — Notifikasi

Ubah kondisi dari `stock <= 0` jadi `stock <= min_stock AND min_stock > 0`:

```php
$lowStockNotifications = Product::where('min_stock', '>', 0)
    ->whereColumn('stock', '<=', 'min_stock')
    // ...rest same
```

#### ProductController — Form

Tambah field `min_stock` dan `max_stock`:

```php
$request->validate([
    // ...existing
    'min_stock' => ['nullable', 'integer', 'min:0'],
    'max_stock' => ['nullable', 'integer', 'min:0'],
]);
```

### Phase 4.5 — Frontend

#### Product Form

Tambah 2 field di form produk:
- Min Stok (number, 0 = tidak ada alert)
- Max Stok (number, 0 = tidak auto-suggest)

#### Dashboard Widget

Card baru "Produk Perlu Restock":
```
┌──────────────────────────────────┐
│ Produk Perlu Restock        [3]  │
├──────────────────────────────────┤
│ Aqua 600ml        Stok: 2/20     │
│                   Min: 10        │
│                   → Pesan 18     │
│──────────────────────────────────│
│ Indomie Goreng    Stok: 5/50     │
│                   Min: 20        │
│                   → Pesan 45     │
│──────────────────────────────────│
│ [Buat Draft PO dari Semua]       │
└──────────────────────────────────┘
```

#### Sidebar Badge

Badge di menu "Produk" untuk jumlah produk yang perlu di-restock.

#### Auto-PO Button

"Buat Draft PO" → redirect ke halaman PO draft dengan items pre-populated.

---

### Files Affected

| Layer | Files |
|-------|-------|
| Migration | `add_min_max_stock_to_products`, `add_min_max_stock_to_product_warehouse` — 2 baru |
| Model modif | `Product.php` — 3 method baru |
| Service | `ReorderService.php` — 1 baru |
| Controller | `ProductController.php`, `DashboardController.php` — 2 modif |
| Middleware | `HandleInertiaRequests.php` — modif kondisi notifikasi |
| Frontend | `Products/Create.jsx`, `Products/Edit.jsx`, `Dashboard/Dashboard.jsx` — 3 modif |
| Test | `ReorderPointTest.php` — 1 baru |
| **Total** | **~12 files** |

### Permission Impact
None.

### Rollout
Bisa sendiri. Produk existing = min_stock=0 (tidak ada alert).

---

## Release Checklist v2.1

| Modul | Code Complete | Test | Docs | Dependencies |
|-------|-------------|------|------|-------------|
| Tax Management | | | | None |
| Import/Export | | | | `maatwebsite/excel` |
| Unit Conversion | | | | None |
| Reorder Point | | | | None |

### Backward Compatibility
Semua modul backward compatible — data lama tetap berfungsi dengan default values.

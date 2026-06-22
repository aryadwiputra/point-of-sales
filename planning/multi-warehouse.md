# Multi-Warehouse Module — Execution Plan

## Objective

Memisahkan stok produk per lokasi fisik (gudang pusat, cabang toko, gudang penyangga). Saat ini `products.stock` adalah integer tunggal — semua produk punya satu stok global. Modul ini memungkinkan bisnis dengan >1 lokasi operasional.

## Why Now

- Blocker #1 untuk scale: semua bisnis ritel dengan >1 cabang butuh ini
- Semua modul inventory sudah mature (stock mutation, stock opname, purchasing chain)
- Foundation sudah siap tinggal tambah dimensi warehouse

## Definitions

| Term | Meaning |
|------|---------|
| Warehouse | Lokasi fisik penyimpanan stok (gudang pusat, cabang, gudang penyangga) |
| Main Warehouse | Gudang utama/default, dibuat saat seeder |
| Branch Warehouse | Cabang toko yang juga jual langsung ke konsumen |
| Stock Warehouse | Gudang penyangga (tidak menjual langsung) |

---

## Phase 1 — Foundation: Warehouse CRUD + Product-Warehouse Pivot

### 1.1 Migration: `create_warehouses_table`

| Column | Type | Notes |
|--------|------|-------|
| id | bigIncrements | PK |
| code | string(20), unique | `WH-001`, `PUSAT`, `CABANG-A` |
| name | string(100) | Nama gudang/cabang |
| type | enum: `main`, `branch`, `warehouse` | |
| address | text, nullable | |
| phone | string(20), nullable | |
| is_active | boolean, default true | |
| sort_order | integer, default 0 | Urutan tampilan |
| timestamps | | |

### 1.2 Migration: `create_product_warehouse_table`

| Column | Type | Constraints |
|--------|------|-------------|
| id | bigIncrements | PK |
| product_id | bigInteger | FK → products, ON DELETE CASCADE |
| warehouse_id | bigInteger | FK → warehouses, ON DELETE CASCADE |
| stock | integer, default 0 | |
| timestamps | | |

**Unique constraint:** `(product_id, warehouse_id)`

### 1.3 Seeder: Default Warehouse + Migrate Existing Stock

```php
// Di DatabaseSeeder, setelah migrasi warehouse
$pusat = Warehouse::create([
    'code' => 'PUSAT',
    'name' => 'Gudang Pusat',
    'type' => 'main',
    'is_active' => true,
]);

// Pindahin stok existing ke pivot
DB::statement("INSERT INTO product_warehouse (product_id, warehouse_id, stock, created_at, updated_at)
    SELECT id, {$pusat->id}, stock, NOW(), NOW() FROM products");
```

### 1.4 Model: `Warehouse`

```php
class Warehouse extends Model
{
    protected $fillable = ['code', 'name', 'type', 'address', 'phone', 'is_active', 'sort_order'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('stock')
            ->using(ProductWarehouse::class);
    }

    public function scopeActive($q) { $q->where('is_active', true); }
}
```

### 1.5 Model: `ProductWarehouse` (Pivot)

```php
class ProductWarehouse extends Pivot
{
    public $incrementing = true;
    public $timestamps = true;
}
```

### 1.6 Model Changes: `Product`

**Add:**
```php
public function warehouses(): BelongsToMany
{
    return $this->belongsToMany(Warehouse::class)
        ->withPivot('stock')
        ->using(ProductWarehouse::class);
}

// Helper: total stock across all warehouses
public function stockTotal(): int
{
    return (int) $this->warehouses()->sum('product_warehouse.stock');
}
```

**Deprecate:** `Product.stock` column — tetap ada untuk backward compat, tapi logika baca/tulis pindah ke pivot.

### 1.7 Permission (via PermissionSeeder)

```php
$permissions[] = ['name' => 'warehouses-access', 'guard_name' => 'web'];
$permissions[] = ['name' => 'warehouses-create', 'guard_name' => 'web'];
$permissions[] = ['name' => 'warehouses-update', 'guard_name' => 'web'];
$permissions[] = ['name' => 'warehouses-delete', 'guard_name' => 'web'];
```

### 1.8 Controller & Routes

**`WarehouseController`** (di `app/Http/Controllers/Apps/`):
- `index` — list warehouses (permission: warehouses-access)
- `store` — create (permission: warehouses-create, step_up)
- `update` — edit (permission: warehouses-update, step_up)
- `destroy` — delete with guard (permission: warehouses-delete, step_up)

**Routes** (di `routes/web.php` dalam group dashboard):
```php
Route::resource('settings/warehouses', WarehouseController::class)
    ->except('show')
    ->middlewareFor('index', 'permission:warehouses-access')
    ->middlewareFor('store', ['permission:warehouses-create', 'step_up'])
    ->middlewareFor('update', ['permission:warehouses-update', 'step_up'])
    ->middlewareFor('destroy', ['permission:warehouses-delete', 'step_up']);
```

### 1.9 Frontend: `Settings/Warehouses.jsx`

- Table: code, name, type badge, is_active toggle, stock count, sort order
- Create/Edit modal (inline di halaman yang sama)
- Delete: konfirmasi, cek apakah warehouse masih punya stok > 0

### 1.10 Test: `WarehouseTest`

- CRUD warehouse (permission test per action)
- Default warehouse dibuat saat seeding
- Pivot terisi untuk produk existing
- Soft guard: tidak bisa hapus warehouse yang masih punya stok

---

## Phase 2 — Warehouse-aware Cashier Shift

### 2.1 Migration: `add_warehouse_id_to_cashier_shifts`

```php
Schema::table('cashier_shifts', function (Blueprint $table) {
    $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
});
```

### 2.2 Model Changes: `CashierShift`

```php
public function warehouse(): BelongsTo
{
    return $this->belongsTo(Warehouse::class);
}
```

### 2.3 UX: Open Shift → Pilih Warehouse

Dropdown warehouse di form buka shift. Hanya warehouse type `branch` dan `main` yang tampil (bukan warehouse penyangga).

### 2.4 Flow

- Saat buka shift, kasir memilih warehouse
- Semua transaksi di shift itu terikat ke warehouse tsb
- Tutup shift: warehouse tidak bisa diubah

---

## Phase 3 — Warehouse-aware Transaction Flow

### 3.1 Migration: `add_warehouse_id_to_transactions`

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
});
```

### 3.2 Migration: `add_warehouse_id_to_carts`

```php
Schema::table('carts', function (Blueprint $table) {
    $table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
});
```

### 3.3 Changes: TransactionController

**Search product:**
```php
$warehouseId = $activeShift->warehouse_id;
$products = Product::whereHas('warehouses', function ($q) use ($warehouseId) {
    $q->where('product_warehouse.warehouse_id', $warehouseId)
      ->where('product_warehouse.stock', '>', 0);
})->get();
```

**Add to cart:**
```php
$cart->warehouse_id = $warehouseId;
```

**Store transaction (checkout):**
```php
ProductWarehouse::where([
    'product_id' => $item->product_id,
    'warehouse_id' => $warehouseId,
])->decrement('stock', $item->qty);
```

**Get held carts** — filter by warehouse_id dari shift.

### 3.4 Changes: `StockMutationService`

Tambah parameter `warehouse_id` ke semua method:

```php
public function recordPurchaseInbound(
    Product $product,
    GoodsReceiving $goodsReceiving,
    int $qty,
    int $stockBefore,
    int $stockAfter,
    ?string $notes = null,
    ?int $userId = null,
    ?int $warehouseId = null
): StockMutation {
    // ...
}
```

Migration: `add_warehouse_id_to_stock_mutations`

### 3.5 Frontend Changes

| Page | Change |
|------|--------|
| POS Product Grid | Filter product yang punya stok > 0 di warehouse shift aktif |
| Transaction History | Dropdown filter by warehouse |
| Transaction Detail | Tampilkan warehouse asal |

---

## Phase 4 — Warehouse-aware Purchasing Chain

### 4.1 Migrations

```php
// purchase_orders
$table->foreignId('warehouse_id')->nullable()->constrained('warehouses');

// goods_receivings
$table->foreignId('warehouse_id')->nullable()->constrained('warehouses');

// supplier_returns
$table->foreignId('warehouse_id')->nullable()->constrained('warehouses');

// stock_opnames
$table->foreignId('warehouse_id')->nullable()->constrained('warehouses');

// sales_returns
$table->foreignId('warehouse_id')->nullable()->constrained('warehouses');
```

### 4.2 Changes per Module

**Purchase Order:**
- Form PO tambah field "Tujuan Gudang"
- Default dari warehouse utama
- Saat Goods Receiving, stok masuk ke warehouse yang ditentukan di PO

**Goods Receiving:**
- Warehouse sudah ditentukan dari PO (read-only)
- `StockMutationService.recordPurchaseInbound` decrement di warehouse tujuan

**Supplier Return:**
- Stok keluar dari warehouse asal barang
- Pilih warehouse saat create return

**Stock Opname:**
- Pilih warehouse yang akan diopname
- Hanya tampilkan product_warehouse untuk warehouse tsb
- Adjustment update stock di pivot warehouse tsb

**Sales Return:**
- Restock masuk ke warehouse asal transaksi
- Jika transaksi sudah punya warehouse_id, restock ke situ

---

## Phase 5 — Stock Transfer Antar Warehouse

### 5.1 Migration: `create_stock_transfers_table`

```php
Schema::create('stock_transfers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('source_warehouse_id')->constrained('warehouses');
    $table->foreignId('destination_warehouse_id')->constrained('warehouses');
    $table->string('document_number', 30)->unique();
    $table->enum('status', ['draft', 'in_transit', 'completed', 'cancelled'])->default('draft');
    $table->text('notes')->nullable();
    $table->foreignId('created_by')->constrained('users');
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});

Schema::create('stock_transfer_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained();
    $table->integer('qty');
    $table->timestamps();
});
```

### 5.2 Models

**`StockTransfer`:**
- `sourceWarehouse()`, `destinationWarehouse()`, `items()`, `creator()`
- `scopeDraft()`, `scopeInTransit()`, `scopeCompleted()`
- `send()` — update status ke in_transit, kurangi stok source
- `receive()` — update status ke completed, tambah stok destination, catat stock mutation

**`StockTransferItem`:**
- `product()`, `stockTransfer()`

### 5.3 Service: `StockTransferService`

```php
class StockTransferService
{
    public function createDraft(array $data, int $userId): StockTransfer
    public function send(StockTransfer $transfer, int $userId): void
    public function receive(StockTransfer $transfer, int $userId): void
    public function cancel(StockTransfer $transfer, int $userId): void
}
```

**Send flow:**
1. Validate: source warehouse punya cukup stok untuk semua item
2. Kurangi stock di `product_warehouse` (source)
3. Set `status = in_transit`, simpan timestamp
4. Catat `StockMutation` untuk setiap item (type: out, reference: stock_transfer)

**Receive flow:**
1. Tambah stock di `product_warehouse` (destination)
2. Set `status = completed`, simpan `completed_at`
3. Catat `StockMutation` untuk setiap item (type: in, reference: stock_transfer)

### 5.4 Controller & Routes

**`StockTransferController`:**
- `index` — list semua transfer (permission: stock-transfers-access)
- `create` — form transfer (permission: stock-transfers-create)
- `store` — simpan draft (permission: stock-transfers-create)
- `show` — detail (permission: stock-transfers-access)
- `send` — kirim barang (permission: stock-transfers-send)
- `receive` — terima barang (permission: stock-transfers-receive)
- `cancel` — batalkan (permission: stock-transfers-cancel)

```php
Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
Route::get('/stock-transfers/create', [StockTransferController::class, 'create'])->name('stock-transfers.create');
Route::post('/stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->name('stock-transfers.show');
Route::post('/stock-transfers/{stockTransfer}/send', [StockTransferController::class, 'send'])->name('stock-transfers.send');
Route::post('/stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');
Route::post('/stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');
```

### 5.5 Frontend: `StockTransfers/`

**`Index.jsx`**: Tabel dengan status badge, source → destination, total items, created_at

**`Create.jsx`**: Form:
1. Pilih source warehouse
2. Pilih destination warehouse (≠ source)
3. Tabel: search product → add → input qty
4. Submit → draft

**`Show.jsx`**: Detail + actions:
- Draft: tombol Send, Cancel
- In Transit: tombol Receive
- Completed/Cancelled: readonly

### 5.6 Permissions

```php
['name' => 'stock-transfers-access', 'guard_name' => 'web'];
['name' => 'stock-transfers-create', 'guard_name' => 'web'];
['name' => 'stock-transfers-send', 'guard_name' => 'web'];
['name' => 'stock-transfers-receive', 'guard_name' => 'web'];
['name' => 'stock-transfers-cancel', 'guard_name' => 'web'];
```

---

## Phase 6 — UI Filtering & Reports

### 6.1 Warehouse Selector Component

Buat reusable `WarehouseFilter.jsx` — dropdown yang muncul di:
- Transaction History
- Stock Mutation list
- Stock Opname list
- Product Index (opsional)
- Reports (Sales, Profit, Insights)

### 6.2 Report Modifications

- Sales Report: filter by warehouse + total per warehouse
- Profit Report: breakdown per warehouse
- Stock Value Report: nilai stok per warehouse
- Aging: filter by warehouse (receivable/payable tidak terikat warehouse, tapi transaksi asal bisa difilter)

### 6.3 Dashboard Widget

- Card: "Total Stok" → jadi "Total Stok per Gudang" (breakdown)
- Low stock notification: tampilkan warehouse mana yang low stock

---

## Summary of Deliverables per Phase

| Phase | Migrations | Models | Services | Controllers | JS Pages | Tests |
|-------|-----------|--------|----------|-------------|----------|-------|
| 1 | 2 | 2 new, 1 modif | 0 | 1 | 1 | 1 |
| 2 | 1 | 1 modif | 1 modif | 1 modif | 1 modif | 1 modif |
| 3 | 2 | 2 modif | 1 modif | 2 modif | 3 modif | 2 modif |
| 4 | 5 | 5 modif | 1 modif | 5 modif | 5 modif | 5 modif |
| 5 | 2 | 2 new, 1 service | 1 new | 1 new | 3 new | 1 new |
| 6 | 0 | 0 | 0 | 2 modif | 5 modif | 0 |

**Total perkiraan:** ~12 migrations, ~6 model (2 baru, 4 modif), ~2 service baru, ~10 service modif, ~3 controller baru, ~15 controller modif, ~10 JS page baru/modif.

---

## Rollout Strategy

1. **Phase 1 (Foundation):** Bisa di-release sendiri. Semua produk existing otomatis masuk warehouse PUSAT. Admin bisa manage warehouse. Tidak ada perubahan UX untuk kasir.
2. **Phase 2 (Shift):** Setelah ini, kasir harus pilih warehouse saat buka shift. Backward compat: warehouse_id nullable, default ke PUSAT.
3. **Phase 3 (Transactions):** Transaksi mulai tercatat per warehouse. Stok dikurangi dari warehouse shift aktif.
4. **Phase 4 (Purchasing):** PO, GR, supplier return, stock opname, sales return jadi warehouse-aware.
5. **Phase 5 (Transfer):** Fitur baru — transfer stok antar gudang.
6. **Phase 6 (Reports):** Penyempurnaan UI filtering.

Setiap phase backward compatible: data lama tetap berfungsi dengan warehouse PUSAT sebagai default.

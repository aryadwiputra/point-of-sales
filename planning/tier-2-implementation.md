# Tier 2 Implementation Plan — v2.2

Target: 4 modul.

---

## 5. Discount Approval Workflow

### Objective
Diskon melebihi threshold memerlukan approval supervisor. Mencegah fraud dan human error.

### Why
Kritis untuk toko dengan banyak kasir. Tanpa approval workflow, kasir bisa memberikan diskon sembarangan.

---

### Flow

```
Cashier input diskon > threshold
                ↓
Checkout Preview — tampil "Menunggu Approval Supervisor"
                ↓
Notifikasi ke supervisor (dashboard + toast)
                ↓
Supervisor: Approve / Deny  (via PIN atau tombol)
                ↓
Approved → Transaksi lanjut
Denied   → Diskon di-reset, checkout bisa lanjut tanpa diskon
Timeout  → Auto-deny setelah 5 menit
```

### Phase 5.1 — Database

#### Migration: `add_discount_approval_settings`

```php
// settings table
['key' => 'discount_approval_threshold', 'value' => '0', 'description' => 'Nominal diskon maksimal tanpa approval. 0 = nonaktif'],
['key' => 'discount_approval_percent_threshold', 'value' => '0', 'description' => 'Persentase diskon maksimal tanpa approval. 0 = nonaktif'],
['key' => 'discount_approval_timeout', 'value' => '300', 'description' => 'Timeout approval dalam detik'],
```

#### Migration: `add_discount_approval_to_transactions`

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->foreignId('discount_approved_by')->nullable()->constrained('users');
    $table->timestamp('discount_approved_at')->nullable();
    $table->string('discount_approval_status', 20)->nullable(); // pending, approved, denied
});
```

#### Migration: `create_discount_approval_logs`

```php
Schema::create('discount_approval_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('transaction_id')->constrained();
    $table->foreignId('cashier_id')->constrained('users');
    $table->integer('requested_discount');
    $table->string('status', 20); // pending, approved, denied
    $table->foreignId('responded_by')->nullable()->constrained('users');
    $table->timestamp('responded_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

### Phase 5.2 — Model Changes

#### Transaction

```php
public function discountApprover(): BelongsTo
{
    return $this->belongsTo(User::class, 'discount_approved_by');
}

public function discountApprovalLogs(): HasMany
{
    return $this->hasMany(DiscountApprovalLog::class);
}

public function needsDiscountApproval(): bool
{
    $threshold = (int) Setting::get('discount_approval_threshold', 0);
    $percentThreshold = (int) Setting::get('discount_approval_percent_threshold', 0);

    if ($threshold > 0 && $this->discount >= $threshold) return true;
    if ($percentThreshold > 0 && $this->grand_total > 0) {
        $percent = ($this->discount / $this->grand_total) * 100;
        if ($percent >= $percentThreshold) return true;
    }
    return false;
}
```

### Phase 5.3 — Controller Changes

#### TransactionController.store()

Tambahkan logika sebelum finalisasi:

```php
if ($appliedManualDiscount > 0 && $transaction->needsDiscountApproval()) {
    $transaction->update([
        'discount_approval_status' => 'pending',
        'payment_status' => 'pending_approval',
    ]);

    DiscountApprovalLog::create([
        'transaction_id' => $transaction->id,
        'cashier_id' => auth()->id(),
        'requested_discount' => $appliedManualDiscount,
        'status' => 'pending',
    ]);

    // Simpan session agar redirect ke halaman approval
    return to_route('transactions.index')
        ->with('info', 'Transaksi menunggu approval supervisor.');
}
```

#### DiscountApprovalController

```php
class DiscountApprovalController extends Controller
{
    public function approve(Transaction $transaction)
    {
        $this->ensureUserIsSupervisor();

        $transaction->update([
            'discount_approval_status' => 'approved',
            'discount_approved_by' => auth()->id(),
            'discount_approved_at' => now(),
            'payment_status' => 'paid',
        ]);

        $this->logApproval($transaction, 'approved');

        return back()->with('success', 'Diskon transaksi disetujui.');
    }

    public function deny(Request $request, Transaction $transaction)
    {
        $this->ensureUserIsSupervisor();

        $transaction->update([
            'discount_approval_status' => 'denied',
            'discount_approved_by' => auth()->id(),
            'discount_approved_at' => now(),
            'payment_status' => 'paid',       // tetap bayar tanpa diskon
            'discount' => 0,                   // reset diskon
        ]);

        $this->logApproval($transaction, 'denied', $request->notes);

        return back()->with('success', 'Diskon ditolak. Transaksi dilanjutkan tanpa diskon.');
    }

    public function pending()
    {
        $pendingTransactions = Transaction::where('discount_approval_status', 'pending')
            ->with(['cashier:id,name', 'customer:id,name'])
            ->get();

        return Inertia::render('Dashboard/DiscountApprovals', [
            'pendingTransactions' => $pendingTransactions,
        ]);
    }
}
```

### Phase 5.4 — Frontend

#### POS Checkout Preview

Jika `discount > threshold`, tampilkan badge merah:
```
┌─────────────────────────────────┐
│ ⚠ Menunggu Approval Supervisor  │
│ Diskon: Rp 50,000               │
│ Supervisor: Masukkan PIN atau    │
│ buka halaman approval           │
└─────────────────────────────────┘
```

#### Halaman Approval

`DiscountApprovals.jsx` — list semua transaksi yang pending:
- Invoice, kasir, nominal diskon, waktu request
- Tombol Approve / Deny (dengan alasan)
- Auto-refresh setiap 30 detik

### Phase 5.5 — Notifikasi

Sidebar notification bell — tambah badge "Pending Approvals" untuk user dengan permission `discounts-approve`.

### Permission Impact

```php
['name' => 'discounts-approve', 'guard_name' => 'web'];
```

### Files Affected
~15 files: 3 migrations, 1 model baru, 1 controller baru, 4 frontend modif, 1 permission, 1 role

### Effort
2-3 hari

---

## 6. Multi-Price List

### Objective
Harga berbeda per kelompok pelanggan — retail, grosir, member, reseller. Simple, tanpa lewat pricing rules engine.

### Why
Pricing rules engine terlalu kompleks untuk kebutuhan "harga member Rp 5.000, harga grosir Rp 4.500".

---

### Phase 6.1 — Database

#### Migration: `create_price_lists_table`

```php
Schema::create('price_lists', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('slug', 100)->unique();
    $table->enum('customer_scope', ['all', 'walk_in', 'registered', 'member', 'segment'])->default('all');
    $table->foreignId('customer_segment_id')->nullable()->constrained('customer_segments');
    $table->boolean('is_active')->default(true);
    $table->integer('priority')->default(0);
    $table->text('notes')->nullable();
    $table->timestamps();
});

Schema::create('price_list_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->bigInteger('price');
    $table->timestamps();
    $table->unique(['price_list_id', 'product_id']);
});
```

#### Migration: `add_price_list_id_to_transactions`

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->foreignId('price_list_id')->nullable()->constrained('price_lists');
});
```

### Phase 6.2 — Model

```php
class PriceList extends Model
{
    protected $fillable = ['name', 'slug', 'customer_scope', 'customer_segment_id', 'is_active', 'priority', 'notes'];

    public function items(): HasMany { return $this->hasMany(PriceListItem::class); }
    public function segment(): BelongsTo { return $this->belongsTo(CustomerSegment::class); }
}

class PriceListItem extends Model
{
    protected $fillable = ['price_list_id', 'product_id', 'price'];
    public function priceList(): BelongsTo { return $this->belongsTo(PriceList::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
```

### Phase 6.3 — Service

```php
class PriceListService
{
    public function getApplicablePriceList(?Customer $customer): ?PriceList
    {
        if (! $customer) {
            return PriceList::where('customer_scope', 'walk_in')->active()->first();
        }

        // Prioritaskan price list dengan score tertinggi
        $lists = PriceList::active()->orderBy('priority', 'desc')->get();

        foreach ($lists as $list) {
            if ($list->customer_scope === 'all') return $list;
            if ($list->customer_scope === 'registered') return $list;
            if ($list->customer_scope === 'member' && $customer->is_loyalty_member) return $list;
            if ($list->customer_scope === 'segment' && $list->segment_id) {
                if ($customer->segments()->where('customer_segment_id', $list->customer_segment_id)->exists()) return $list;
            }
        }

        return null;
    }

    public function getProductPrice(Product $product, ?PriceList $priceList): ?int
    {
        if (! $priceList) return null;
        return $priceList->items()->where('product_id', $product->id)->value('price');
    }
}
```

### Phase 6.4 — Controller

#### PriceListController

CRUD price list + items. Route: `/settings/price-lists/*`

#### Transaction Pricelist Integration

Di `TransactionController.addToCart()` — cek price list customer:

```php
$customer = $request->customer_id ? Customer::find($request->customer_id) : null;
$priceList = app(PriceListService::class)->getApplicablePriceList($customer);
$priceListPrice = $priceList ? app(PriceListService::class)->getProductPrice($product, $priceList) : null;
$effectivePrice = $priceListPrice ?? $product->sell_price;
```

### Phase 6.5 — Frontend

#### Product Index / Edit

Tampilkan harga per price list di product detail.

#### POS — Customer Select

Saat customer dipilih → auto-apply price list. Tampilkan label "Harga Grosir" atau "Harga Member" di product grid.

#### Price List CRUD

Halaman baru di Settings: daftar price list + inline product price editor (search product → set price).

### Permission Impact

```php
$create('price-lists-access');
$create('price-lists-create');
$create('price-lists-update');
$create('price-lists-delete');
```

### Files Affected
~15 files.

### Effort
3-4 hari.

---

## 7. Batch / Expiry Date Tracking

### Objective
Batch number + expired date per penerimaan barang. FEFO (First Expiry First Out) saat checkout.

### Why
Kritis untuk FMCG, makanan, minuman, obat, kimia. Tanggung jawab legal.

---

### Phase 7.1 — Database

#### Migration: `create_product_batches_table`

```php
Schema::create('product_batches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('warehouse_id')->constrained();
    $table->string('batch_number', 100);
    $table->date('expired_at')->nullable();
    $table->date('received_at');
    $table->integer('stock')->default(0);
    $table->timestamps();

    $table->unique(['product_id', 'warehouse_id', 'batch_number']);
});
```

#### Migration: `add_batch_id_to_transaction_details`

```php
Schema::table('transaction_details', function (Blueprint $table) {
    $table->foreignId('product_batch_id')->nullable()->constrained('product_batches');
});
```

#### Migration: `add_batch_reference_to_stock_mutations`

```php
Schema::table('stock_mutations', function (Blueprint $table) {
    $table->string('batch_number', 100)->nullable();
    $table->date('expired_at')->nullable();
});
```

### Phase 7.2 — Model

```php
class ProductBatch extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'batch_number', 'expired_at', 'received_at', 'stock'];

    protected function casts(): array
    {
        return [
            'expired_at' => 'date',
            'received_at' => 'date',
            'stock' => 'integer',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('expired_at', '<=', now()->addDays($days))
            ->where('expired_at', '>', now())
            ->where('stock', '>', 0);
    }

    public function scopeExpired($query)
    {
        return $query->where('expired_at', '<', now())->where('stock', '>', 0);
    }
}
```

### Phase 7.3 — Service

```php
class BatchService
{
    /**
     * Get batches available for a product in a warehouse, FEFO order
     */
    public function getAvailableBatches(int $productId, int $warehouseId, int $qtyNeeded): Collection
    {
        return ProductBatch::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('stock', '>', 0)
            ->orderBy('expired_at')  // FEFO: closest expiry first
            ->orderBy('received_at')  // then FIFO
            ->get();
    }

    /**
     * Allocate qty from batches (FEFO)
     */
    public function allocate(Product $product, int $warehouseId, int $qty): array
    {
        $batches = $this->getAvailableBatches($product->id, $warehouseId, $qty);
        $allocations = [];
        $remaining = $qty;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $take = min($batch->stock, $remaining);
            $allocations[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'expired_at' => $batch->expired_at,
                'qty' => $take,
            ];
            $remaining -= $take;
        }

        return $allocations;
    }
}
```

### Phase 7.4 — Integration Points

#### Goods Receiving — Input Batch

Form goods receiving — tambah input batch number + expired date per item:

```
┌─────────────────────────────────────────┐
│ Penerimaan Barang — PO-20260622-0001    │
├─────────────────────────────────────────┤
│ Produk        | Qty | Batch    | Exp    │
│───────────────|─────|──────────|────────│
│ Aqua 600ml    | 100 | BATCH-01 | 2027-06 │
│ Indomie Goreng| 200 | BATCH-A  | 2026-12 │
└─────────────────────────────────────────┘
```

#### Checkout — FEFO Allocation

Saat checkout, panggil `BatchService::allocate()` untuk setiap line item.

Simpan `product_batch_id` di `transaction_detail`.

Kurangi stok batch (`ProductBatch::decrement`).

#### Stock Mutation — Batch Reference

Semua stock mutation yang terkait batch — catat batch_number + expired_at.

#### Dashboard — Expiration Alerts

Card baru "Produk Mendekati Expired":
```
┌─────────────────────────────────────┐
│ ⚠ Expiring Soon (30 hari)     [5]   │
├─────────────────────────────────────┤
│ Susu Ultra 1L    Exp: 12 Jul 2026   │
│                  Stok: 24 batch: B-1 │
│ Minyak Goreng    Exp: 20 Jul 2026   │
│                  Stok: 50 batch: MG  │
└─────────────────────────────────────┘
```

### Files Affected
~18 files.

### Effort
4-5 hari.

---

## 8. Composite Products / Kits

### Objective
Produk bundle dari komponen. Contoh: "Paket Sembako" terdiri dari beras 5kg + minyak 2L + gula 1kg.

### Why
Kebutuhan riil toko. Memisahkan pricing rule bundle (diskon) dari composite product (produk fisik baru).

---

### Phase 8.1 — Database

#### Migration: `add_is_composite_to_products`

```php
Schema::table('products', function (Blueprint $table) {
    $table->boolean('is_composite')->default(false);
});
```

#### Migration: `create_composite_product_items`

```php
Schema::create('composite_product_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('composite_product_id')->constrained('products')->cascadeOnDelete();
    $table->foreignId('component_product_id')->constrained('products');
    $table->decimal('qty', 15, 4)->default(1);   // bisa desimal (0.5 kg)
    $table->timestamps();
    $table->unique(['composite_product_id', 'component_product_id']);
});
```

### Phase 8.2 — Model

```php
// Product model additions
public function components(): BelongsToMany
{
    return $this->belongsToMany(Product::class, 'composite_product_items', 'composite_product_id', 'component_product_id')
        ->withPivot('qty')
        ->withTimestamps();
}

public function compositeOf(): BelongsToMany
{
    return $this->belongsToMany(Product::class, 'composite_product_items', 'component_product_id', 'composite_product_id');
}

public function compositeStock(): int
{
    if (! $this->is_composite) return $this->stock;
    $minStock = null;
    foreach ($this->components as $component) {
        $available = (int) floor($component->stockTotal() / $component->pivot->qty);
        $minStock = $minStock === null ? $available : min($minStock, $available);
    }
    return $minStock ?? 0;
}
```

### Phase 8.3 — Checkout Flow

Saat composite product di-add ke cart:

```php
// Di TransactionController.addToCart()
if ($product->is_composite) {
    $product->load('components');
    foreach ($product->components as $component) {
        $componentQty = $component->pivot->qty * $request->qty;
        // Kurangi stok komponen
        ProductWarehouse::where([
            'product_id' => $component->id,
            'warehouse_id' => $warehouseId,
        ])->decrement('stock', (int) ceil($componentQty));

        // Catat stock mutation per komponen
    }

    // Harga = sum(harga komponen)
    $totalPrice = $product->components->sum(fn ($c) => $c->sell_price * $c->pivot->qty) * $request->qty;
}
```

### Phase 8.4 — Frontend

#### Product Form

Checkbox "Produk Gabungan (Composite)" → tampil tabel komponen:
```
┌──────────────────────────────────────────┐
│ ☑ Produk Gabungan                         │
├──────────────────────────────────────────┤
│ Komponen              | Qty               │
│───────────────────────|───────────────────│
│ Beras 5kg            | 1                 │
│ Minyak Goreng 2L     | 1                 │
│ Gula Pasir 1kg       | 1                 │
│ [Cari produk...]                         │
└──────────────────────────────────────────┘
Harga: Otomatis dari jumlah harga komponen
Stok: Min(beras.stok, minyak.stok, gula.stok)
```

#### POS Product Grid

Composite products — badge "Paket":
```
[Paket Sembako]         Rp 85,000 🏷️ Paket
```

### Permission Impact
None.

### Files Affected
~12 files.

### Effort
3-4 hari.

---

## Release Checklist v2.2

| Modul | Code | Test | Dependencies |
|-------|------|------|-------------|
| Discount Approval | | | None |
| Multi-Price List | | | None |
| Batch/Expiry | | | None |
| Composite Products | | | None |

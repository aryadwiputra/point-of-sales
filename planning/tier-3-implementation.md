# Tier 3 Implementation Plan — v2.3+

Target: 4 modul strategis untuk diferensiasi jangka panjang.

---

## 9. Customer Portal (Self-Service)

### Objective
Pelanggan bisa akses invoice, riwayat transaksi, status piutang via link publik tanpa login.

### Why
Kurangi beban admin tanya "kapan bayar?". Tingkatkan collection rate. Bedain dari POS lain.

---

### Flow

```
Transaksi selesai
    ↓
Generate token akses (UUID, disimpan di DB)
    ↓
Invoice/share page include URL: /share/transactions/{invoice}?token=xxx
    ↓
Pelanggan buka link → lihat:
    - Invoice detail + PDF download
    - Riwayat transaksi (jika punya no_telp yang sama)
    - Status piutang + aging
    - Tombol "Bayar Sekarang" (via existing payment gateway)
```

### Phase 9.1 — Database

#### Migration: `add_access_token_to_transactions`

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->string('access_token', 36)->nullable()->unique()->after('invoice');
});
```

#### Migration: `add_access_token_to_receivables`

```php
Schema::table('receivables', function (Blueprint $table) {
    $table->string('access_token', 36)->nullable()->unique()->after('invoice');
});
```

### Phase 9.2 — Model

Transaction: auto-generate token saat create.
```php
protected static function booted(): void
{
    static::creating(function ($transaction) {
        $transaction->access_token = (string) Str::uuid();
    });
}
```

### Phase 9.3 — Controller

```php
class PublicPortalController extends Controller
{
    public function showTransaction(Request $request, $invoice)
    {
        $transaction = Transaction::where('invoice', $invoice)
            ->where('access_token', $request->token)
            ->with(['details.product', 'customer', 'receivable'])
            ->firstOrFail();

        return Inertia::render('Public/TransactionDetail', [
            'transaction' => $transaction,
        ]);
    }

    public function payReceivable(Request $request, Receivable $receivable)
    {
        abort_if($receivable->transaction->access_token !== $request->token, 403);

        // Redirect ke payment gateway
        $paymentUrl = app(PaymentGatewayManager::class)
            ->createPayment($receivable->transaction, 'midtrans', PaymentSetting::first());

        return redirect($paymentUrl['payment_url']);
    }
}
```

### Phase 9.4 — Routes

```php
Route::get('/share/transactions/{invoice}', [PublicPortalController::class, 'showTransaction'])->name('public.transaction');
Route::post('/share/receivables/{receivable}/pay', [PublicPortalController::class, 'payReceivable'])->name('public.receivable.pay');
```

### Phase 9.5 — Frontend

#### `Public/TransactionDetail.jsx`

Layout tanpa sidebar — guest layout:
- Header: logo toko + nama toko
- Invoice number + status badge
- Tabel item
- Total breakdown
- Status pembayaran
- Tombol Download PDF
- Jika ada receivable: "Bayar Piutang" button
- Jika sudah lunas: "Terima Kasih" message

### Permission Impact
None — route publik dengan token validation.

### Security Notes
- Token UUID v4 — tidak bisa ditebak
- Token hanya bisa dipakai untuk 1 invoice (kecuali multi-invoice per customer via email)
- Tidak ada data sensitif (hanya nama produk, harga, status)
- Rate limit per IP

### Files Affected
~8 files: 2 migrations, 1 controller, 3 frontend pages, 2 route

### Effort
4-5 hari.

---

## 10. Mobile POS / Tablet Optimization

### Objective
Touch-friendly UI untuk tablet. Barcode scan via kamera HP. PWA install ke home screen.

### Why
Banyak UMKM pakai tablet murah sebagai POS station.

---

### Phase 10.1 — Barcode Scanner

Install library:
```bash
npm install html5-qrcode
```

#### `components/POS/BarcodeScanner.jsx`

```jsx
import { Html5Qrcode } from "html5-qrcode";

export default function BarcodeScanner({ onScan }) {
    const scannerRef = useRef(null);
    const [scanning, setScanning] = useState(false);

    const startScanning = () => {
        const scanner = new Html5Qrcode("barcode-scanner");
        scanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 150 } },
            (decodedText) => {
                scanner.stop();
                setScanning(false);
                onScan(decodedText);
            }
        );
    };

    return (
        <div>
            <button onClick={startScanning}>
                {scanning ? "Scanning..." : "Scan Barcode"}
            </button>
            <div id="barcode-scanner" />
        </div>
    );
}
```

Integrasi ke POS: ganti input barcode biasa dengan kamera scanner toggle.

### Phase 10.2 — PWA

#### `public/manifest.json`

```json
{
    "name": "Point of Sales",
    "short_name": "POS",
    "start_url": "/",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#4f46e5",
    "icons": [
        { "src": "/images/icon-192.png", "sizes": "192x192", "type": "image/png" },
        { "src": "/images/icon-512.png", "sizes": "512x512", "type": "image/png" }
    ]
}
```

#### Service Worker

`public/sw.js` — cache strategy:
- Cache-first untuk assets (JS, CSS, fonts)
- Network-first untuk API calls (produk, customer)
- Fallback ke cached data saat offline

Register di `resources/js/app.jsx`:
```js
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js');
}
```

### Phase 10.3 — Touch Optimization

Check existing `tailwind.config.js`:
```
minHeight: { touch: '2.75rem', 'touch-lg': '3rem' }
minWidth: { touch: '2.75rem', 'touch-lg': '3rem' }
```

Sudah ada — pastikan semua interactive element pakai `min-h-touch`:
- Buttons
- Input fields
- Select dropdowns
- Table rows (tap target)

### Phase 10.4 — Fullscreen Mode

Tambah tombol fullscreen di POS page:
```jsx
const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
};
```

### Files Affected
~6 files: 1 library install, 1 component baru, PWA manifest, service worker, 2 frontend modif

### Effort
3-4 hari.

---

## 11. Offline Mode

### Objective
Transaksi tetap jalan saat internet putus. Queue transaksi offline, sync saat online.

### Why
Kills competitors. Banyak lokasi UMKM dengan internet tidak stabil. Ini yang bikin POS ini beda.

---

### Architecture

```
Online Mode                           Offline Mode
┌──────────┐    API     ┌────────┐    ┌──────────┐    IndexedDB   ┌────────┐
│  Browser  │ ────────→ │ Server │    │  Browser  │ ←──────────→  │ Cache  │
│ (React)   │ ←──────── │        │    │ (React)   │               │        │
└──────────┘           └────────┘    └──────────┘               └────────┘
                                             │ Queue transaksi
                                             ↓
                                      Saat online: sync queue via Background Sync
```

### Phase 11.1 — Service Worker

`public/sw.js`:

```js
const CACHE_NAME = 'pos-cache-v1';
const MASTER_DATA_URLS = ['/api/products', '/api/customers', '/api/pricing'];

// Install — cache master data
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(MASTER_DATA_URLS))
    );
});

// Fetch — cache-first for master data, network-first for transactions
self.addEventListener('fetch', (event) => {
    if (event.request.url.includes('/api/')) {
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request))
        );
    }
});

// Background Sync
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-transactions') {
        event.waitUntil(syncPendingTransactions());
    }
});

async function syncPendingTransactions() {
    const db = await openDB();
    const pending = await db.getAll('pending_transactions');

    for (const tx of pending) {
        try {
            const response = await fetch('/transactions/store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(tx.data),
            });
            if (response.ok) {
                await db.delete('pending_transactions', tx.id);
            }
        } catch (e) {
            console.error('Sync failed for transaction', tx.id, e);
        }
    }
}
```

### Phase 11.2 — IndexedDB Cache

Gunakan library `idb`:
```bash
npm install idb
```

#### `resources/js/Utils/offlineDb.js`

```js
import { openDB } from 'idb';

const dbPromise = openDB('pos-offline', 1, {
    upgrade(db) {
        db.createObjectStore('products', { keyPath: 'id' });
        db.createObjectStore('customers', { keyPath: 'id' });
        db.createObjectStore('pending_transactions', { keyPath: 'id', autoIncrement: true });
    },
});

export async function cacheProducts(products) {
    const db = await dbPromise;
    const tx = db.transaction('products', 'readwrite');
    for (const product of products) {
        await tx.store.put(product);
    }
    await tx.done;
}

export async function getCachedProducts() {
    const db = await dbPromise;
    return db.getAll('products');
}

export async function queueTransaction(transactionData) {
    const db = await dbPromise;
    return db.add('pending_transactions', {
        data: transactionData,
        created_at: new Date().toISOString(),
        synced: false,
    });
}

export async function getPendingCount() {
    const db = await dbPromise;
    return db.count('pending_transactions');
}
```

### Phase 11.3 — Online/Offline Detection

#### `resources/js/Context/OnlineStatusContext.jsx`

```jsx
const OnlineStatusContext = createContext();

export function OnlineStatusProvider({ children }) {
    const [isOnline, setIsOnline] = useState(navigator.onLine);

    useEffect(() => {
        const goOnline = () => setIsOnline(true);
        const goOffline = () => setIsOnline(false);
        window.addEventListener('online', goOnline);
        window.addEventListener('offline', goOffline);
        return () => {
            window.removeEventListener('online', goOnline);
            window.removeEventListener('offline', goOffline);
        };
    }, []);

    return (
        <OnlineStatusContext.Provider value={isOnline}>
            {children}
        </OnlineStatusContext.Provider>
    );
}
```

### Phase 11.4 — Transaction Flow (Offline)

```js
// Di POS/TransactionFlow — checkout
async function handleCheckout(formData) {
    if (navigator.onLine) {
        // Normal online checkout
        router.post(route('transactions.store'), formData);
    } else {
        // Offline: queue to IndexedDB
        await queueTransaction(formData);
        toast.success('Transaksi disimpan offline. Akan sync saat online.');
        // Clear cart locally
        // Tampilkan invoice number sementara (local-generated)
    }
}
```

### Phase 11.5 — Sync Indicator

Banner di POS header:
```
🔴 Offline — 3 transaksi menunggu sync    [Sync Now]
🟢 Online — Tersinkronisasi
```

### Files Affected
~10 files: 1 library, service worker, offlineDb utility, OnlineStatusContext, 3 frontend modif

### Effort
5-7 hari.

---

## 12. Thermal Printer Integration

### Objective
Auto-print receipt ke thermal printer (ESC/POS protocol) dari browser langsung.

### Why
UMKM pakai thermal printer murah. Ini ekspektasi, bukan fitur tambahan.

---

### Phase 12.1 — Server-side Print (Fallback)

```bash
composer require mike42/escpos-php
```

#### `app/Services/ThermalPrintService.php`

```php
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class ThermalPrintService
{
    public function printReceipt(Transaction $transaction, string $connectorType = 'file', string $connectorPath = '/dev/usb/lp0')
    {
        $connector = match ($connectorType) {
            'network' => new NetworkPrintConnector($connectorPath, 9100),
            'windows' => new WindowsPrintConnector($connectorPath),
            default => new FilePrintConnector($connectorPath),
        };

        $printer = new Printer($connector);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("TOKO ANDA\n");
        $printer->text("Jl. Contoh No. 123\n");
        $printer->feed();
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("Invoice: {$transaction->invoice}\n");
        $printer->text("Tanggal: {$transaction->created_at->format('d/m/Y H:i')}\n");
        $printer->feed();

        foreach ($transaction->details as $detail) {
            $printer->text("{$detail->product->title} x{$detail->qty}\n");
            $printer->text("  " . number_format($detail->price) . "\n");
        }

        $printer->feed();
        $printer->text("Total: " . number_format($transaction->grand_total) . "\n");
        $printer->feed(2);
        $printer->cut();
        $printer->close();
    }
}
```

### Phase 12.2 — WebUSB / WebSerial (Browser-side)

Untuk auto-print dari browser tanpa backend:

```js
// POS/PaymentPanel.jsx — setelah checkout sukses
async function printReceipt(transaction) {
    try {
        const device = await navigator.usb.requestDevice({ filters: [] });
        await device.open();
        await device.selectConfiguration(1);
        await device.claimInterface(0);

        const encoder = new TextEncoder();
        const data = encoder.encode(receiptToEscPos(transaction));
        await device.transferOut(1, data);

        toast.success('Receipt printed');
    } catch (e) {
        // Fallback: PDF download
        window.open(route('pdf.transactions.receipt', transaction.invoice));
    }
}

function receiptToEscPos(transaction) {
    // Build ESC/POS raw commands
    let text = '\x1b\x40'; // Initialize
    text += '\x1b\x61\x01'; // Center align
    text += 'TOKO ANDA\n\n';
    text += '\x1b\x61\x00'; // Left align
    text += `Invoice: ${transaction.invoice}\n`;
    // ... items
    text += '\x1d\x56\x00'; // Cut paper
    return text;
}
```

### Phase 12.3 — Printer Settings

Halaman Settings:

```
Printer Type: [USB / Network / Windows]
Connection:   [/dev/usb/lp0  |  192.168.1.100:9100]
Paper Width:  [80mm ▼]
Charset:      [UTF-8 ▼]
Auto-print:   [✓] Cetak otomatis setelah transaksi
```

### Files Affected
~8 files: 1 composer install, 1 service, 2 frontend modif, 1 settings page

### Effort
3-4 hari.

---

## 13. Marketplace Integration

### Objective
Sinkronisasi stok produk ke Tokopedia/Shopee. Import order dari marketplace.

### Why
Ekosistem. Toko offline + online stok sama.

---

### Architecture

```
Point of Sales ◄────► Queue Job ◄────► Marketplace API
     │                      │
     ▼                      ▼
Local DB            Failed log retry
```

### Phase 13.1 — Settings

```php
// settings table
['key' => 'marketplace_tokopedia_enabled', 'value' => 'false'],
['key' => 'marketplace_tokopedia_api_key', 'value' => ''],
['key' => 'marketplace_shopee_enabled', 'value' => 'false'],
['key' => 'marketplace_shopee_api_key', 'value' => ''],
['key' => 'marketplace_sync_frequency', 'value' => 'every_30_minutes'],
```

### Phase 13.2 — Service

```php
class MarketplaceService
{
    public function syncProductToTokopedia(Product $product): array
    {
        // POST product data to Tokopedia API
        // Return: success/fail + marketplace_product_id
    }

    public function syncProductToShopee(Product $product): array
    {
        // POST to Shopee API
    }

    public function importOrdersFromTokopedia(): Collection
    {
        // GET orders from Tokopedia API
        // Map to local order format
        // Return: array of pending orders
    }

    public function syncStock(Product $product): void
    {
        // Update stock on all enabled marketplaces
    }
}
```

### Phase 13.3 — Queue Job

```php
class SyncStockToMarketplace implements ShouldQueue
{
    public function __construct(public Product $product) {}

    public function handle(MarketplaceService $service): void
    {
        $service->syncStock($this->product);
    }
}
```

Trigger di `ProductController.update()`:
```php
SyncStockToMarketplace::dispatch($product);
```

### Phase 13.4 — Schedule

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->call(function () {
        app(MarketplaceService::class)->importOrdersFromTokopedia();
        app(MarketplaceService::class)->importOrdersFromShopee();
    })->everyThirtyMinutes();
}
```

### Files Affected
~10 files: 1 service, 1 job, scheduled task, settings page

### Effort
5-7 hari per marketplace.

---

## 14. Multi-Currency

### Objective
Support IDR, USD, SGD, MYR dengan kurs harian.

### Why
Daerah perbatasan, turis, atau bisnis valas.

---

### Phase 14.1 — Database

#### Migration: `create_currencies_table`

```php
Schema::create('currencies', function (Blueprint $table) {
    $table->id();
    $table->string('code', 3)->unique();         // IDR, USD, SGD
    $table->string('name', 50);                  // Indonesian Rupiah
    $table->string('symbol', 10);                // Rp, $, S$
    $table->decimal('exchange_rate', 15, 4)->default(1);
    $table->boolean('is_base')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

Seed: IDR (base), USD, SGD, MYR.

#### Migration: `add_currency_to_transactions`

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->string('currency_code', 3)->default('IDR');
    $table->decimal('exchange_rate', 15, 4)->default(1);
});
```

### Phase 14.2 — POS Checkout

Dropdown currency di POS. Saat ganti currency:
- Harga produk dikonversi via exchange_rate
- Pembayaran di currency tersebut
- Laporan tetap dalam base currency (IDR)

### Files Affected
~6 files: 2 migrations, 1 seeder, 2 frontend modif

### Effort
2-3 hari.

---

## Release Plan v2.3+

| Release | Target | Modules | Timeline |
|---------|--------|---------|----------|
| **v2.3** | Growth | Customer Portal, Mobile POS (PWA), Thermal Printer | ~3 minggu |
| **v2.4** | Scale | Offline Mode | ~2 minggu |
| **v3.0** | Ecosystem | Marketplace Integration, Multi-Currency | ~4 minggu |

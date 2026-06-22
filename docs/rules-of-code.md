# Rules of Code - Pragmatic Service Repository Pattern

Version: 3.0
Framework: Laravel 12, compatible with Laravel 13 direction
PHP Standard: PSR-12

---

## 1. Core Principles

Semua kode backend wajib mengikuti:

- PSR-12
- SOLID Principles
- DRY, KISS, dan readable code
- Convention over Configuration
- Thin Controller
- Service sebagai application boundary
- Repository dan DTO digunakan secara pragmatis, bukan dogmatis

Tujuan utama aturan ini adalah menjaga codebase POS tetap:

- predictable
- maintainable
- testable
- scalable
- tidak over-engineered untuk kebutuhan kecil

---

## 2. Project Structure

Struktur utama yang direkomendasikan:

```text
app/
+-- DTOs/
+-- Enums/
+-- Exceptions/
+-- Helpers/
+-- Http/
|   +-- Controllers/
|   +-- Middleware/
|   +-- Requests/
|   |   +-- Category/
|   |   |   +-- StoreCategoryRequest.php
|   |   |   +-- UpdateCategoryRequest.php
|   |   +-- Region/
|   |   |   +-- GetRegenciesRequest.php
|   |   |   +-- GetDistrictsRequest.php
|   |   +-- Supplier/
|   |       +-- StoreSupplierRequest.php
|   +-- Resources/
+-- Models/
+-- Repositories/
|   +-- BaseRepository.php
|   +-- ProductRepository.php
|   +-- TransactionRepository.php
+-- Services/
|   +-- BaseService.php
|   +-- Queries/
|   |   +-- RegionQueryService.php
|   |   +-- DashboardSummaryQueryService.php
|   +-- Products/
|   |   +-- CreateProductService.php
|   |   +-- UpdateProductService.php
|   +-- Transactions/
|       +-- CheckoutTransactionService.php
+-- Traits/
+-- Policies/
+-- Providers/
```

Catatan:

- `Repositories/` tidak wajib berisi repository untuk setiap model.
- `Services/Queries/` digunakan untuk read-only lookup atau page payload yang ringan.
- `DTOs/` digunakan hanya saat payload cukup kompleks atau butuh type safety tinggi.

---

## 3. Naming Convention

### 3.1 Controller

Gunakan suffix `Controller`.

```php
CategoryController
TransactionController
PaymentWebhookController
```

### 3.2 Request

Gunakan suffix `Request`.

```php
StoreSupplierRequest
UpdateProductRequest
GetRegenciesRequest
```

Simpan request di folder domain agar mudah dicari.

```text
app/Http/Requests/Category/StoreCategoryRequest.php
app/Http/Requests/Supplier/UpdateSupplierRequest.php
app/Http/Requests/Region/GetRegenciesRequest.php
```

Namespace mengikuti folder domain.

```php
App\Http\Requests\Category\StoreCategoryRequest
App\Http\Requests\Supplier\UpdateSupplierRequest
App\Http\Requests\Region\GetRegenciesRequest
```

### 3.3 Command / Use-Case Service

Gunakan suffix `Service` dengan nama action yang jelas.

```php
CreateSupplierService
UpdateCategoryService
CheckoutTransactionService
CompleteSalesReturnService
```

### 3.4 Query Service

Gunakan suffix `QueryService`.

```php
RegionQueryService
SupplierIndexQueryService
DashboardSummaryQueryService
```

### 3.5 Repository

Gunakan suffix `Repository`.

```php
ProductRepository
TransactionRepository
SalesReturnRepository
```

### 3.6 DTO

Gunakan suffix `Dto`.

```php
CheckoutTransactionDto
CreateSalesReturnDto
UpdateProductDto
```

### 3.7 Trait

Gunakan suffix `Trait` atau nama behavior yang sangat jelas.

```php
UploadsImagesTrait
```

Trait hanya dibuat jika ada reusable behavior nyata, bukan sekadar memindahkan kode.

---

## 4. Controller Rules

Controller wajib tipis.

Controller hanya bertanggung jawab untuk:

- menerima request
- menggunakan FormRequest untuk validasi
- membangun DTO jika memang diperlukan
- memanggil Service
- mengembalikan response, redirect, Inertia page, atau JSON

Controller dilarang:

- query Model langsung
- memanggil Repository langsung
- menjalankan business logic
- menjalankan workflow transaksi database
- melakukan manipulasi data kompleks

Benar:

```php
public function store(
    StoreSupplierRequest $request,
    CreateSupplierService $service
) {
    $service->execute($request->validated());

    return back()->with('success', 'Supplier berhasil ditambahkan.');
}
```

Salah:

```php
public function store(Request $request)
{
    $data = $request->validate([...]);

    Supplier::create($data);

    return back();
}
```

---

## 5. Service Rules

Service adalah batas utama aplikasi.

Service bertanggung jawab untuk:

- business logic
- domain validation
- workflow mutation
- orchestration antar repository/service
- transaction boundary
- pemanggilan external gateway
- audit log untuk perubahan penting

Service boleh berbentuk:

- command/use-case service untuk aksi mutation atau workflow bisnis
- query service untuk read-only lookup atau page payload

Service action-based direkomendasikan untuk domain besar, tetapi modul kecil boleh memakai service yang lebih ringkas selama tidak menjadi god service.

Contoh domain besar yang sebaiknya memakai service action-based:

- transaction checkout
- sales return completion
- stock opname finalization
- product creation/update with units and image
- payment webhook handling
- receivable/payable payment

Contoh modul kecil yang boleh lebih ringkas:

- region lookup
- low-stock notification read marker
- simple settings payload

---

## 6. QueryService Pattern

QueryService digunakan untuk kebutuhan read-only yang kecil, spesifik, atau lebih cocok sebagai page payload.

QueryService boleh mengakses Model langsung jika:

- query bersifat read-only
- query kecil dan spesifik
- tidak ada mutation
- tidak ada business workflow
- tidak ada kebutuhan reuse lintas banyak service

Lokasi:

```text
app/Services/Queries/RegionQueryService.php
app/Services/Queries/DashboardSummaryQueryService.php
```

Atau jika lebih natural secara domain:

```text
app/Services/Suppliers/SupplierIndexQueryService.php
```

Contoh:

```php
<?php

declare(strict_types=1);

namespace App\Services\Queries;

use Illuminate\Support\Collection;
use Laravolt\Indonesia\Models\City;

class RegionQueryService
{
    public function regencies(string $provinceCode): Collection
    {
        return City::query()
            ->where('province_code', $provinceCode)
            ->select('code', 'name')
            ->orderBy('name')
            ->get();
    }
}
```

Controller tetap hanya memanggil service:

```php
public function regencies(
    GetRegenciesRequest $request,
    RegionQueryService $service
) {
    return $service->regencies($request->validated('province_id'));
}
```

QueryService dilarang:

- membuat, mengubah, atau menghapus data
- menjalankan domain workflow
- menyimpan audit log mutation
- menjadi tempat business rule kompleks

Jika QueryService mulai kompleks atau reusable lintas domain, pindahkan data access-nya ke Repository.

---

## 7. Repository Rules

Repository tidak wajib untuk semua model.

Repository digunakan jika memberi nilai nyata, misalnya:

- domain besar
- query reusable
- query kompleks
- pagination/filter/sorting yang dipakai banyak tempat
- eager loading yang perlu distandarkan
- lock for update
- aggregate query penting
- operasi data yang dipakai banyak service

Repository hanya bertanggung jawab untuk:

- data access
- query builder
- eager loading
- filtering
- pagination
- sorting
- locking
- aggregate database query

Repository dilarang:

- business logic
- response formatting
- validation request
- redirect/Inertia/JSON response
- audit wording

Controller tidak boleh memanggil Repository langsung.

Flow yang benar:

```text
Controller -> Service -> Repository -> Model -> Database
```

Flow yang dilarang:

```text
Controller -> Repository
Controller -> Model
```

---

## 8. BaseRepository Pattern

Gunakan `BaseRepository` untuk CRUD dasar yang memang reusable.

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    protected Model $model;

    abstract protected function model(): Model;

    public function __construct()
    {
        $this->model = $this->model();
    }

    public function query(): Builder
    {
        return $this->model->query();
    }

    public function all(): Collection
    {
        return $this->query()->get();
    }

    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage);
    }

    public function findById(int|string $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    public function update(int|string $id, array $data): bool
    {
        return (bool) $this->query()
            ->whereKey($id)
            ->update($data);
    }

    public function delete(int|string $id): bool
    {
        return (bool) $this->query()
            ->whereKey($id)
            ->delete();
    }
}
```

Child repository:

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class ProductRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new Product();
    }

    public function findSellableByBarcode(string $barcode): ?Product
    {
        return $this->query()
            ->with('units')
            ->where('barcode', $barcode)
            ->where('stock', '>', 0)
            ->first();
    }
}
```

---

## 9. BaseService Pattern

`BaseService` boleh digunakan untuk service yang membutuhkan standardized execution flow.

Tidak semua QueryService wajib extend `BaseService`.

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

abstract class BaseService
{
    public function execute(mixed ...$payload): mixed
    {
        try {
            return $this->success($this->handle(...$payload));
        } catch (ValidationException|AuthorizationException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            return $this->error($throwable);
        }
    }

    abstract protected function handle(mixed ...$payload): mixed;

    protected function success(mixed $data = null, string $message = 'Success'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function error(Throwable $throwable): array
    {
        Log::error($throwable);

        return [
            'success' => false,
            'message' => config('app.debug')
                ? $throwable->getMessage()
                : 'Internal Server Error',
            'data' => null,
        ];
    }
}
```

Catatan:

- Untuk web/Inertia flows, service boleh mengembalikan model, collection, array payload, atau domain result.
- Controller tetap menentukan redirect/Inertia/json response.
- Jangan sembunyikan `ValidationException`, `AuthorizationException`, atau `ModelNotFoundException`.

---

## 10. DTO Rules

DTO tidak wajib untuk semua request.

Gunakan DTO jika:

- payload kompleks
- payload dipakai lintas method/service
- butuh type safety tinggi
- struktur data domain penting
- ada nested items
- ada calculation context

Domain yang direkomendasikan memakai DTO:

- transaction checkout
- cart mutation dengan pricing context
- sales return
- stock opname
- product with units
- payment webhook
- purchase/goods receiving

Untuk CRUD kecil, service boleh menerima array tervalidasi dari FormRequest.

Benar untuk CRUD kecil:

```php
$service->execute($request->validated());
```

Benar untuk domain kompleks:

```php
$service->execute(CheckoutTransactionDto::fromRequest($request));
```

DTO example:

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

class CreateSupplierDto
{
    public function __construct(
        public string $name,
        public ?string $phone,
        public ?string $email,
        public ?string $address,
    ) {
    }
}
```

---

## 11. Validation Rules

Gunakan FormRequest untuk validasi controller.

FormRequest wajib dikelompokkan per domain.

Benar:

```php
App\Http\Requests\Supplier\StoreSupplierRequest
App\Http\Requests\Category\UpdateCategoryRequest
App\Http\Requests\Transaction\CheckoutTransactionRequest
```

Hindari validasi inline di controller:

```php
$request->validate([...]);
```

Pengecualian hanya untuk controller framework/auth bawaan atau perubahan sangat kecil yang belum sempat direfactor.

---

## 12. Response Rules

Controller bertanggung jawab pada response akhir:

- Inertia render
- redirect
- back with flash message
- JSON response
- file/pdf response

Service tidak boleh bergantung pada Inertia atau redirect.

Format JSON API internal yang direkomendasikan:

```json
{
  "success": true,
  "message": "Success",
  "data": {}
}
```

Gunakan API Resource jika transformasi response kompleks atau dipakai lintas endpoint.

---

## 13. Database Rules

Gunakan database transaction untuk workflow mutation yang menyentuh lebih dari satu tabel.

```php
DB::transaction(function () {
    //
});
```

Transaction boundary sebaiknya berada di Service, bukan Controller.

Gunakan locking untuk flow sensitif:

- checkout transaction
- cashier shift close/open
- stock mutation
- sales return completion
- payment confirmation
- receivable/payable payment

Hindari N+1 query dengan eager loading.

---

## 14. Enum Rules

Gunakan PHP Enum untuk static value yang penting dan stabil.

```php
enum PaymentStatus: string
{
    case PAID = 'paid';
    case PENDING = 'pending';
    case UNPAID = 'unpaid';
}
```

Enum direkomendasikan untuk status domain besar seperti payment, shift, return, stock mutation, dan order status.

---

## 15. Error Handling

Gunakan custom exception untuk error domain penting.

```php
throw new InsufficientStockException();
```

Hindari generic exception untuk business rule.

```php
throw new Exception('Error');
```

Gunakan `ValidationException::withMessages()` untuk error input/domain yang harus kembali ke form.

---

## 16. Query Rules

Dilarang query di:

- Controller
- Blade/View
- React page

Query hanya boleh berada di:

- QueryService untuk read-only lookup kecil
- Repository untuk query reusable/kompleks/domain-heavy
- Model relationship/scope untuk reusable local query behavior

Model scope boleh dipakai untuk kondisi reusable yang melekat pada model.

Contoh:

```php
$query->active();
$query->open();
$query->held();
```

---

## 17. Security Rules

Wajib:

- validasi semua input
- gunakan mass assignment protection
- gunakan permission/policy sesuai kebutuhan
- sanitize upload file
- jangan expose stack trace production
- jangan gunakan `env()` selain di config
- jangan bypass middleware permission di route dashboard

---

## 18. Logging Rules

Gunakan logging untuk:

- exception
- external API
- transaksi penting
- authentication issue
- perubahan data kritikal

Audit log direkomendasikan untuk:

- user/role/permission changes
- transaction payment confirmation
- stock mutation
- sales return
- cashier shift open/close
- payment settings

---

## 19. API Standards

Gunakan RESTful naming untuk endpoint API.

```text
GET    /users
POST   /users
GET    /users/{id}
PUT    /users/{id}
DELETE /users/{id}
```

Untuk Inertia dashboard, route boleh mengikuti kebutuhan UX, tetapi tetap gunakan nama route yang jelas dan konsisten.

---

## 20. Testing Rules

Wajib:

- feature test untuk endpoint penting
- unit test untuk service domain penting
- mock repository saat testing service jika repository dipakai
- test QueryService jika query-nya punya filter/sorting/edge case penting

Prioritaskan test untuk:

- checkout transaction
- sales return
- stock opname
- payment webhook
- cashier shift
- receivable/payable payment
- authorization consistency

---

## 21. Clean Code Rules

Wajib:

- meaningful naming
- single responsibility
- early return
- hindari nested terlalu dalam
- hindari duplicated code
- hapus dead code
- komentar hanya untuk logic yang tidak obvious

Service kecil lebih baik daripada god service.

Repository kecil lebih baik daripada query tersebar di banyak tempat, tetapi repository kosong yang hanya membungkus satu query sederhana tidak wajib dibuat.

---

## 22. Architecture Flow

### 22.1 Simple Lookup

Untuk read-only lookup kecil:

```text
Request
  |
Controller
  |
FormRequest
  |
RegionQueryService
  |
Model
  |
Database
```

### 22.2 Domain Command

Untuk mutation atau business workflow:

```text
Request
  |
Controller
  |
FormRequest
  |
DTO or validated array
  |
Command Service
  |
Repository
  |
Model
  |
Database
```

### 22.3 Read Page Payload

Untuk page Inertia:

```text
Request
  |
Controller
  |
IndexQueryService
  |
Model or Repository
  |
Inertia props
```

Controller tetap yang memanggil `Inertia::render()`.

---

## 23. Full Example Flow

### Simple Region Lookup

```text
GetRegenciesRequest
  |
RegionController
  |
RegionQueryService
  |
Laravolt City Model
```

### Supplier Create

```text
StoreSupplierRequest
  |
SupplierController
  |
CreateSupplierService
  |
Supplier Model or SupplierRepository
```

Untuk supplier sederhana, service boleh memakai Model langsung jika belum ada kebutuhan repository reusable.

### Transaction Checkout

```text
CheckoutTransactionRequest
  |
TransactionController
  |
CheckoutTransactionDto
  |
CheckoutTransactionService
  |
CartRepository / ProductRepository / TransactionRepository
  |
Models
```

Untuk transaksi, repository direkomendasikan karena domain kompleks dan butuh locking/transaction boundary.

---

## 24. Forbidden Rules

Dilarang:

- fat controller
- controller query Model langsung
- controller memanggil Repository langsung
- business logic di controller
- mutation workflow di QueryService
- query database di Blade/View
- duplicated code
- hardcoded string untuk domain status penting
- menggunakan `env()` selain di config
- facade berlebihan dalam service
- god service
- repository tanpa nilai tambah yang dipaksakan untuk semua model
- DTO kosong yang hanya menyalin array tanpa kebutuhan type safety

---

## 25. Recommended Packages

Development:

- laravel/pint
- larastan/larastan
- barryvdh/laravel-debugbar

Permission:

- spatie/laravel-permission

Query Builder:

- spatie/laravel-query-builder

Activity Log:

- spatie/laravel-activitylog

---

## 26. Git Standards

Gunakan branch dan commit yang jelas.

Branch examples:

```text
feature/service-repository-foundation
refactor/transaction-services
fix/payment-webhook-status
```

Commit examples:

```text
feat: add pragmatic service repository foundation
refactor: move category logic into services
fix: preserve payment webhook status mapping
```

---

## 27. Final Principles

Prioritas utama:

1. Consistency
2. Simplicity
3. Maintainability
4. Testability
5. Scalability
6. Readability

Gunakan service repository pattern secara pragmatis:

- domain kompleks memakai service + repository + DTO jika perlu
- lookup kecil memakai QueryService
- CRUD kecil boleh memakai service + validated array
- controller selalu tipis dan hanya bicara dengan service

Pattern ini cocok untuk POS karena domain transaksi, inventory, payment, retur, shift, piutang, hutang, loyalty, dan audit log terus berkembang. Namun pattern tidak boleh dipakai secara kaku sampai membuat class yang tidak punya nilai nyata.

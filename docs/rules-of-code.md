# Laravel 13 Coding Standards

## Base Service Repository Pattern Architecture

Version: 2.0  
Framework: Laravel 13  
PHP Standard: PSR-12

---

# Table of Contents

1. General Rules
2. Project Structure
3. Naming Convention
4. Controller Rules
5. BaseRepository Pattern
6. Repository Rules
7. BaseService Pattern
8. Service Rules
9. DTO Rules
10. Validation Rules
11. Response Rules
12. Database Rules
13. Enum Rules
14. Error Handling
15. Query Rules
16. Security Rules
17. Logging Rules
18. API Standards
19. Git Standards
20. Testing Rules
21. Clean Code Rules
22. Architecture Flow
23. Full Example Flow
24. Forbidden Rules
25. Recommended Packages
26. Final Principles

---

# 1. General Rules

## 1.1 PHP Standards

Semua kode wajib mengikuti:

- PSR-12
- SOLID Principles
- DRY (Don't Repeat Yourself)
- KISS (Keep It Simple)
- Clean Architecture
- Convention over Configuration

---

## 1.2 Formatter

Gunakan Laravel Pint.

Install:

```bash
composer require laravel/pint --dev
```

````

Run formatter:

```bash id="t2d8fk"
./vendor/bin/pint
```

---

## 1.3 Strict Typing

WAJIB menggunakan strict typing.

```php id="tb99x0"
declare(strict_types=1);
```

---

# 2. Project Structure

Gunakan struktur berikut:

```text id="7h9wdb"
app/
├── DTOs/
├── Enums/
├── Exceptions/
├── Helpers/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Repositories/
│   ├── BaseRepository.php
│   ├── UserRepository.php
│   └── ProductRepository.php
├── Services/
│   ├── BaseService.php
│   ├── User/
│   │   ├── CreateUserService.php
│   │   ├── UpdateUserService.php
│   │   └── DeleteUserService.php
├── Traits/
├── Policies/
└── Providers/
```

---

# 3. Naming Convention

## 3.1 Controller

Gunakan suffix `Controller`.

```php id="t2c0wm"
UserController
AuthController
OrderController
```

---

## 3.2 Repository

Gunakan suffix `Repository`.

```php id="xvrh5y"
UserRepository
OrderRepository
```

---

## 3.3 Service

Gunakan suffix `Service`.

```php id="4wrpq0"
CreateUserService
UpdateUserService
DeleteUserService
```

---

## 3.4 DTO

Gunakan suffix `Dto`.

```php id="k7mrm6"
CreateUserDto
UpdateOrderDto
```

---

## 3.5 Request Validation

Gunakan suffix `Request`.

```php id="qaq6p9"
StoreUserRequest
UpdateProductRequest
```

---

## 3.6 API Resource

Gunakan suffix `Resource`.

```php id="zhbrvj"
UserResource
OrderResource
```

---

# 4. Controller Rules

## 4.1 Controller Harus Thin

Controller hanya bertanggung jawab untuk:

- menerima request
- memanggil service
- return response

Controller DILARANG:

- query database
- business logic
- manipulasi data kompleks

---

## 4.2 Controller Example

```php id="fs2x4p"
class UserController extends Controller
{
    public function store(
        StoreUserRequest $request,
        CreateUserService $service
    ) {
        return response()->json(
            $service->execute(
                new CreateUserDto(
                    ...$request->validated()
                )
            )
        );
    }
}
```

---

# 5. BaseRepository Pattern

## 5.1 Semua Repository WAJIB Extends BaseRepository

Repository tidak perlu membuat fungsi CRUD dasar berulang.

Semua CRUD standard disediakan oleh `BaseRepository`.

---

## 5.2 BaseRepository Structure

```php id="e6zqax"
<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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

    public function paginate(
        int $perPage = 10
    ): LengthAwarePaginator {
        return $this->query()->paginate($perPage);
    }

    public function findById(
        int|string $id
    ): ?Model {
        return $this->query()->find($id);
    }

    public function create(
        array $data
    ): Model {
        return $this->query()->create($data);
    }

    public function update(
        int|string $id,
        array $data
    ): bool {
        return $this->query()
            ->whereKey($id)
            ->update($data);
    }

    public function delete(
        int|string $id
    ): bool {
        return (bool) $this->query()
            ->whereKey($id)
            ->delete();
    }
}
```

---

# 6. Repository Rules

## 6.1 Repository Hanya Untuk Data Access

Repository hanya bertanggung jawab untuk:

- query database
- eager loading
- filtering
- pagination
- sorting

Repository DILARANG:

- business logic
- manipulasi response
- validasi bisnis

---

## 6.2 Child Repository Example

```php id="jlwm90"
<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new User();
    }

    public function findByEmail(
        string $email
    ): ?User {
        return $this->query()
            ->where('email', $email)
            ->first();
    }
}
```

---

# 7. BaseService Pattern

## 7.1 Semua Service WAJIB Extends BaseService

Semua service memiliki flow standar:

```text id="j4w7ib"
execute()
   ↓
handle()
   ↓
success() / error()
```

---

## 7.2 BaseService Responsibilities

BaseService bertanggung jawab untuk:

- standardized flow
- centralized error handling
- centralized success response
- logging

---

## 7.3 BaseService Structure

```php id="rq10vc"
<?php

namespace App\Services;

use Throwable;
use Illuminate\Support\Facades\Log;

abstract class BaseService
{
    public function execute(
        mixed ...$payload
    ): mixed {
        try {
            $result = $this->handle(...$payload);

            return $this->success($result);
        } catch (Throwable $throwable) {
            return $this->error($throwable);
        }
    }

    abstract protected function handle(
        mixed ...$payload
    ): mixed;

    protected function success(
        mixed $data = null,
        string $message = 'Success'
    ): array {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function error(
        Throwable $throwable
    ): array {
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

---

# 8. Service Rules

## 8.1 Service Adalah Tempat Business Logic

Semua business logic WAJIB berada di service.

Contoh:

- approval
- transaction flow
- data manipulation
- calculation
- domain validation

---

## 8.2 Service Tidak Boleh Query Langsung

BENAR:

```php id="oqfmqy"
$this->userRepository->findById($id);
```

SALAH:

```php id="xmr7e2"
User::find($id);
```

---

## 8.3 Service Harus Single Responsibility

Disarankan:

```text id="f19z3y"
CreateUserService
UpdateUserService
DeleteUserService
```

Hindari:

```text id="v9jlwm"
UserService
```

yang berisi terlalu banyak logic.

---

## 8.4 Child Service Example

```php id="08px6o"
<?php

namespace App\Services\User;

use App\DTOs\CreateUserDto;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\BaseService;

class CreateUserService extends BaseService
{
    public function __construct(
        protected UserRepository $userRepository
    ) {
    }

    protected function handle(
        mixed ...$payload
    ): User {
        /** @var CreateUserDto $dto */
        $dto = $payload[0];

        return $this->userRepository->create([
            'name' => strtoupper($dto->name),
            'email' => $dto->email,
        ]);
    }
}
```

---

# 9. DTO Rules

## 9.1 Gunakan DTO Untuk Data Transfer

DTO digunakan untuk:

- type safety
- readable payload
- IDE autocomplete
- clean parameter handling

---

## 9.2 DTO Example

```php id="26wdh6"
<?php

namespace App\DTOs;

class CreateUserDto
{
    public function __construct(
        public string $name,
        public string $email,
    ) {
    }
}
```

---

# 10. Validation Rules

## 10.1 Gunakan Form Request

VALID:

```php id="ewj8s5"
StoreUserRequest
```

INVALID:

```php id="glc2z5"
$request->validate([...]);
```

---

# 11. Response Rules

## 11.1 Gunakan Standardized Response

Format response:

```json id="06m6qf"
{
    "success": true,
    "message": "Success",
    "data": {}
}
```

---

## 11.2 Gunakan API Resource Jika Dibutuhkan

Untuk transformasi kompleks gunakan Resource.

```php id="fyavrb"
return new UserResource($user);
```

---

# 12. Database Rules

## 12.1 Gunakan Transaction

```php id="7rbrk7"
DB::transaction(function () {
    //
});
```

---

## 12.2 Hindari N+1 Query

WAJIB eager loading.

---

# 13. Enum Rules

Gunakan PHP Enum untuk static value.

```php id="s03nry"
enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
```

---

# 14. Error Handling

## 14.1 Gunakan Custom Exception

```php id="n6zx4z"
throw new UserNotFoundException();
```

---

## 14.2 Jangan Gunakan Generic Exception

SALAH:

```php id="0t2qt0"
throw new Exception('Error');
```

---

# 15. Query Rules

## 15.1 DILARANG Query di View

SALAH:

```blade id="rl7wux"
@foreach (User::all() as $user)
```

---

# 16. Security Rules

WAJIB:

- validasi semua input
- gunakan mass assignment protection
- gunakan authorization policy
- sanitize upload file
- jangan expose stack trace production
- jangan gunakan `env()` selain di config

---

# 17. Logging Rules

Gunakan logging untuk:

- exception
- external API
- transaksi penting
- authentication issue

---

# 18. API Standards

## 18.1 Gunakan RESTful Naming

```text id="7k5phq"
GET    /users
POST   /users
GET    /users/{id}
PUT    /users/{id}
DELETE /users/{id}
```

---

# 19. Git Standards

## 19.1 Branch Naming

```text id="vw0azx"
feature/user-management
fix/login-validation
hotfix/payment-bug
```

---

## 19.2 Commit Naming

Gunakan Conventional Commit.

```text id="kr5cgf"
feat: add create user service
fix: resolve login validation
refactor: improve base repository
```

---

# 20. Testing Rules

WAJIB:

- feature test untuk endpoint
- unit test untuk service
- mock repository saat testing service

---

# 21. Clean Code Rules

WAJIB:

- meaningful naming
- single responsibility
- hindari nested terlalu dalam
- gunakan early return
- hindari duplicated code

---

# 22. Architecture Flow

```text id="l9gkqc"
Request
   ↓
Controller
   ↓
DTO
   ↓
Service
   ↓
Repository
   ↓
Model
   ↓
Database
```

---

# 23. Full Example Flow

## Request

```text id="2qff1v"
StoreUserRequest
```

↓

## DTO

```text id="k2h4kl"
CreateUserDto
```

↓

## Service

```text id="9x7klh"
CreateUserService
```

↓

## Repository

```text id="6c73dl"
UserRepository
```

↓

## Model

```text id="2z7j4f"
User
```

---

# 24. Forbidden Rules

DILARANG:

- fat controller
- god service
- business logic di controller
- query database di controller
- query database di blade
- duplicated code
- hardcoded string
- menggunakan `env()` selain di config
- facade berlebihan dalam service

---

# 25. Recommended Packages

## Development

- laravel/pint
- larastan/larastan
- barryvdh/laravel-debugbar

---

## Permission

- spatie/laravel-permission

---

## Query Builder

- spatie/laravel-query-builder

---

## Activity Log

- spatie/laravel-activitylog

---

# 26. Final Principles

Code harus:

- readable
- maintainable
- scalable
- reusable
- predictable
- testable

Prioritas utama:

1. Consistency
2. Simplicity
3. Maintainability
4. Scalability
5. Readability

---

# 27. Conclusion

Arsitektur Base Service Repository Pattern digunakan untuk:

- standardisasi service flow
- centralized error handling
- reusable CRUD logic
- clean business logic separation
- scalable enterprise architecture

Pattern ini sangat cocok untuk:

- ERP
- POS
- HRIS
- Campus System
- Finance System
- Enterprise API

```

```
````

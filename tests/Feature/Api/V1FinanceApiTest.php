<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class V1FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->markEmailAsVerified();

        $this->customer = Customer::create([
            'name' => 'Customer Finance',
            'no_telp' => '0811111111',
            'address' => 'Jl. Test No. 1',
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Supplier Finance',
        ]);

        Warehouse::create([
            'code' => 'WH-FIN',
            'name' => 'Gudang Finance',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    private function makeReceivable(array $overrides = []): Receivable
    {
        return Receivable::create(array_merge([
            'customer_id' => $this->customer->id,
            'invoice' => 'REC-'.strtoupper(Str::random(8)),
            'total' => 500000,
            'paid' => 0,
            'status' => 'unpaid',
            'due_date' => now()->addDays(7)->toDateString(),
        ], $overrides));
    }

    private function makePayable(array $overrides = []): Payable
    {
        return Payable::create(array_merge([
            'supplier_id' => $this->supplier->id,
            'document_number' => 'PAY-'.strtoupper(Str::random(8)),
            'total' => 300000,
            'paid' => 0,
            'status' => 'unpaid',
            'due_date' => now()->addDays(14)->toDateString(),
        ], $overrides));
    }

    public function test_receivables_index_lists_receivables(): void
    {
        $receivable = $this->makeReceivable();

        $response = $this->getJson('/api/v1/receivables');

        $response->assertOk()
            ->assertJsonPath('data.0.invoice', $receivable->invoice)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_receivables_index_filters_by_status(): void
    {
        $this->makeReceivable();
        $this->makeReceivable(['invoice' => 'PAID-1', 'status' => 'paid']);

        $response = $this->getJson('/api/v1/receivables?status=paid');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.invoice', 'PAID-1');
    }

    public function test_receivable_show_returns_details(): void
    {
        $receivable = $this->makeReceivable();

        $response = $this->getJson("/api/v1/receivables/{$receivable->id}");

        $response->assertOk()
            ->assertJsonPath('data.receivable.invoice', $receivable->invoice)
            ->assertJsonPath('data.receivable.remaining', 500000);
    }

    public function test_receivable_pay_marks_as_paid(): void
    {
        $receivable = $this->makeReceivable();

        $response = $this->postJson("/api/v1/receivables/{$receivable->id}/pay", [
            'amount' => 500000,
            'paid_at' => now()->toDateTimeString(),
            'method' => 'cash',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'paid' => 500000,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('receivable_payments', [
            'receivable_id' => $receivable->id,
            'amount' => 500000,
        ]);
    }

    public function test_receivable_pay_rejects_amount_over_remaining(): void
    {
        $receivable = $this->makeReceivable();

        $response = $this->postJson("/api/v1/receivables/{$receivable->id}/pay", [
            'amount' => 500001,
            'paid_at' => now()->toDateTimeString(),
            'method' => 'cash',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'paid' => 0,
            'status' => 'unpaid',
        ]);
    }

    public function test_receivable_pay_partial_keeps_partial_status(): void
    {
        $receivable = $this->makeReceivable();

        $response = $this->postJson("/api/v1/receivables/{$receivable->id}/pay", [
            'amount' => 200000,
            'paid_at' => now()->toDateTimeString(),
            'method' => 'transfer',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'paid' => 200000,
            'status' => 'partial',
        ]);
    }

    public function test_receivable_pay_requires_receivables_pay_ability(): void
    {
        $receivable = $this->makeReceivable();
        Sanctum::actingAs($this->user, ['receivables-access']);

        $response = $this->postJson("/api/v1/receivables/{$receivable->id}/pay", [
            'amount' => 100000,
            'paid_at' => now()->toDateTimeString(),
            'method' => 'cash',
        ]);

        $response->assertForbidden();
    }

    public function test_receivables_index_requires_access_ability(): void
    {
        $user = User::factory()->create();
        $user->markEmailAsVerified();
        Sanctum::actingAs($user, ['products-access']);

        $this->getJson('/api/v1/receivables')->assertForbidden();
    }

    public function test_receivable_collection_notes_update(): void
    {
        $receivable = $this->makeReceivable();

        $response = $this->patchJson("/api/v1/receivables/{$receivable->id}/collection-notes", [
            'collection_notes' => 'Sudah dihubungi via WhatsApp',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'collection_notes' => 'Sudah dihubungi via WhatsApp',
        ]);
    }

    public function test_receivables_aging_returns_buckets(): void
    {
        $this->makeReceivable();
        $this->makeReceivable(['invoice' => 'OVERDUE-1', 'due_date' => now()->subDays(45)->toDateString()]);

        $response = $this->getJson('/api/v1/receivables/aging');

        $response->assertOk()
            ->assertJsonCount(5, 'data.aging_summary');
        $this->assertEquals(1, collect($response->json('data.aging_summary'))
            ->firstWhere('bucket', 'current')['count']);
        $this->assertEquals(1, collect($response->json('data.aging_summary'))
            ->firstWhere('bucket', '31-60')['count']);
    }

    public function test_receivables_customer_statement(): void
    {
        $this->makeReceivable();

        $response = $this->getJson("/api/v1/receivables/customer-statement?customer_id={$this->customer->id}");

        $response->assertOk()
            ->assertJsonPath('data.customer.id', $this->customer->id)
            ->assertJsonPath('data.total_outstanding', 500000);
    }

    public function test_payables_index_lists_payables(): void
    {
        $payable = $this->makePayable();

        $response = $this->getJson('/api/v1/payables');

        $response->assertOk()
            ->assertJsonPath('data.0.document_number', $payable->document_number)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_payable_store_creates_unpaid_payable(): void
    {
        $response = $this->postJson('/api/v1/payables', [
            'supplier_id' => $this->supplier->id,
            'total' => 150000,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payables', [
            'supplier_id' => $this->supplier->id,
            'total' => 150000,
            'status' => 'unpaid',
        ]);
    }

    public function test_payable_pay_marks_as_paid(): void
    {
        $payable = $this->makePayable();

        $response = $this->postJson("/api/v1/payables/{$payable->id}/pay", [
            'amount' => 300000,
            'paid_at' => now()->toDateTimeString(),
            'method' => 'transfer',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payables', [
            'id' => $payable->id,
            'paid' => 300000,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('payable_payments', [
            'payable_id' => $payable->id,
            'amount' => 300000,
        ]);
    }

    public function test_payable_pay_rejects_amount_over_remaining(): void
    {
        $payable = $this->makePayable();

        $response = $this->postJson("/api/v1/payables/{$payable->id}/pay", [
            'amount' => 300001,
            'paid_at' => now()->toDateTimeString(),
            'method' => 'cash',
        ]);

        $response->assertStatus(422);
    }

    public function test_payable_pay_requires_payables_pay_ability(): void
    {
        $payable = $this->makePayable();
        Sanctum::actingAs($this->user, ['payables-access']);

        $response = $this->postJson("/api/v1/payables/{$payable->id}/pay", [
            'amount' => 100000,
            'paid_at' => now()->toDateTimeString(),
            'method' => 'cash',
        ]);

        $response->assertForbidden();
    }

    public function test_payables_supplier_statement(): void
    {
        $this->makePayable();
        $this->makePayable(['document_number' => 'LATE-1', 'due_date' => now()->subDays(45)->toDateString()]);

        $response = $this->getJson("/api/v1/payables/supplier-statement?supplier_id={$this->supplier->id}");

        $response->assertOk()
            ->assertJsonPath('data.supplier.id', $this->supplier->id)
            ->assertJsonPath('data.total_outstanding', 600000);
    }
}

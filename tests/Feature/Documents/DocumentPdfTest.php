<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Customer;
use App\Models\Payable;
use App\Models\Receivable;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['transactions-access', 'receivables-access', 'payables-access'] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    public function test_public_invoice_document_can_be_streamed_without_authentication(): void
    {
        $transaction = $this->createTransaction();

        $this->get(route('transactions.public', $transaction->invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_authenticated_user_can_stream_transaction_documents(): void
    {
        $transaction = $this->createTransaction();
        $user = User::factory()->create();
        $user->givePermissionTo('transactions-access');

        foreach ([
            route('pdf.transactions.invoice', $transaction->invoice),
            route('pdf.transactions.receipt', ['invoice' => $transaction->invoice, 'size' => '58']),
            route('pdf.transactions.shipping', $transaction->invoice),
        ] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_authenticated_user_can_stream_financial_documents(): void
    {
        $transaction = $this->createTransaction();
        $customer = Customer::create([
            'name' => 'Document Customer',
            'no_telp' => '62811111111',
            'address' => 'Jl. Document',
        ]);
        $supplier = Supplier::create([
            'name' => 'Document Supplier',
            'phone' => '62822222222',
        ]);
        $receivable = Receivable::create([
            'customer_id' => $customer->id,
            'transaction_id' => $transaction->id,
            'invoice' => 'INV-RECEIVABLE',
            'total' => 100_000,
            'paid' => 25_000,
            'due_date' => now()->addWeek(),
            'status' => 'partial',
        ]);
        $payable = Payable::create([
            'supplier_id' => $supplier->id,
            'document_number' => 'INV-PAYABLE',
            'total' => 200_000,
            'paid' => 50_000,
            'due_date' => now()->addWeek(),
            'status' => 'partial',
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo(['receivables-access', 'payables-access']);

        foreach ([
            route('pdf.receivables.show', $receivable),
            route('pdf.payables.show', $payable),
        ] as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_internal_documents_require_authentication(): void
    {
        $transaction = $this->createTransaction();

        $this->get(route('pdf.transactions.invoice', $transaction->invoice))
            ->assertRedirect(route('login'));
    }

    private function createTransaction(): Transaction
    {
        return Transaction::create([
            'cashier_id' => User::factory()->create()->id,
            'invoice' => 'TRX-DOCUMENT',
            'cash' => 100_000,
            'change' => 0,
            'discount' => 0,
            'shipping_cost' => 0,
            'grand_total' => 100_000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
    }
}

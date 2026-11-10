<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Apps\BankAccountController as AppsBankAccountController;
use App\Http\Traits\ApiResponder;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BankAccountController extends AppsBankAccountController
{
    use ApiResponder;

    /**
     * GET /api/v1/settings/bank-accounts
     */
    public function listBankAccounts(Request $request): JsonResponse
    {
        $bankAccounts = BankAccount::forOutlet($this->outletAccessService->activeOutlet($request))
            ->ordered()
            ->get();

        return $this->ok($bankAccounts);
    }

    /**
     * POST /api/v1/settings/bank-accounts
     */
    public function storeBankAccount(Request $request): JsonResponse
    {
        if (! $request->hasFile('logo')) {
            $request->request->remove('logo');
        }

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:1024'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('bank-logos', 'public');
        }

        $outlet = $this->outletAccessService->activeOutlet($request);

        $bankAccount = BankAccount::create([
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_name' => $validated['account_name'],
            'logo' => $logoPath,
            'is_active' => $request->boolean('is_active'),
            'outlet_id' => $outlet?->id,
            'sort_order' => (int) BankAccount::forOutlet($outlet)->max('sort_order') + 1,
        ]);

        $this->auditLogService->log(
            event: 'bank_account.created',
            module: 'bank_accounts',
            auditable: $bankAccount,
            description: 'Rekening bank ditambahkan.',
            after: $this->bankAccountPayload($bankAccount)
        );

        return $this->created($bankAccount->fresh());
    }

    /**
     * PUT /api/v1/settings/bank-accounts/{bankAccount}
     */
    public function updateBankAccount(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $this->ensureVisible($bankAccount);

        $before = $this->bankAccountPayload($bankAccount);

        if (! $request->hasFile('logo')) {
            $request->request->remove('logo');
        }

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:1024'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $logoPath = $bankAccount->logo;
        if ($request->hasFile('logo')) {
            if ($bankAccount->logo) {
                Storage::disk('public')->delete($bankAccount->logo);
            }
            $logoPath = $request->file('logo')->store('bank-logos', 'public');
        }

        $bankAccount->update([
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_name' => $validated['account_name'],
            'logo' => $logoPath,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->auditLogService->log(
            event: 'bank_account.updated',
            module: 'bank_accounts',
            auditable: $bankAccount,
            description: 'Rekening bank diperbarui.',
            before: $before,
            after: $this->bankAccountPayload($bankAccount->fresh())
        );

        return $this->ok($bankAccount->fresh());
    }

    /**
     * DELETE /api/v1/settings/bank-accounts/{bankAccount}
     */
    public function destroyBankAccount(BankAccount $bankAccount): JsonResponse
    {
        $this->ensureVisible($bankAccount);

        if ($bankAccount->transactions()->exists()) {
            throw ValidationException::withMessages([
                'bank_account' => 'Rekening bank tidak bisa dihapus karena sudah digunakan di transaksi.',
            ]);
        }

        $before = $this->bankAccountPayload($bankAccount);

        if ($bankAccount->logo) {
            Storage::disk('public')->delete($bankAccount->logo);
        }
        $bankAccount->delete();

        $this->auditLogService->log(
            event: 'bank_account.deleted',
            module: 'bank_accounts',
            auditable: $bankAccount,
            description: 'Rekening bank dihapus.',
            before: $before
        );

        return $this->noContent();
    }

    /**
     * PATCH /api/v1/settings/bank-accounts/{bankAccount}/toggle-active
     */
    public function toggleActiveApi(BankAccount $bankAccount): JsonResponse
    {
        $this->ensureVisible($bankAccount);

        $bankAccount->update(['is_active' => ! $bankAccount->is_active]);

        $status = $bankAccount->is_active ? 'diaktifkan' : 'dinonaktifkan';
        $this->auditLogService->log(
            event: 'bank_account.toggled',
            module: 'bank_accounts',
            auditable: $bankAccount,
            description: "Status rekening bank {$status}.",
            before: $this->bankAccountPayload($bankAccount),
            after: $this->bankAccountPayload($bankAccount->fresh())
        );

        return $this->ok($bankAccount->fresh());
    }

    /**
     * POST /api/v1/settings/bank-accounts/order
     */
    public function updateOrder(Request $request): JsonResponse
    {
        $outlet = $this->outletAccessService->activeOutlet($request);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:bank_accounts,id'],
        ]);

        $beforeOrder = BankAccount::forOutlet($outlet)
            ->ordered()
            ->get(['id', 'bank_name', 'sort_order'])
            ->map(fn ($item) => ['id' => (int) $item->id, 'label' => $item->bank_name, 'sort_order' => (int) $item->sort_order])
            ->all();

        foreach ($validated['order'] as $index => $id) {
            BankAccount::forOutlet($outlet)->where('id', $id)->update(['sort_order' => $index]);
        }

        $afterOrder = BankAccount::forOutlet($outlet)
            ->ordered()
            ->get(['id', 'bank_name', 'sort_order'])
            ->map(fn ($item) => ['id' => (int) $item->id, 'label' => $item->bank_name, 'sort_order' => (int) $item->sort_order])
            ->all();

        $this->auditLogService->log(
            event: 'bank_account.reordered',
            module: 'bank_accounts',
            auditable: ['target_label' => 'Bank Accounts'],
            description: 'Urutan rekening bank diperbarui.',
            before: ['order' => $beforeOrder],
            after: ['order' => $afterOrder]
        );

        return $this->ok(['success' => true]);
    }
}

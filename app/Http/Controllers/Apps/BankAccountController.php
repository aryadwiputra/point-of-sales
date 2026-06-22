<?php

declare(strict_types=1);

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccount\StoreBankAccountRequest;
use App\Http\Requests\BankAccount\UpdateBankAccountOrderRequest;
use App\Http\Requests\BankAccount\UpdateBankAccountRequest;
use App\Models\BankAccount;
use App\Services\BankAccounts\BankAccountIndexQueryService;
use App\Services\BankAccounts\CreateBankAccountService;
use App\Services\BankAccounts\DeleteBankAccountService;
use App\Services\BankAccounts\ToggleBankAccountActiveService;
use App\Services\BankAccounts\UpdateBankAccountOrderService;
use App\Services\BankAccounts\UpdateBankAccountService;
use Inertia\Inertia;

class BankAccountController extends Controller
{
    public function index(BankAccountIndexQueryService $service)
    {
        return Inertia::render('Dashboard/Settings/BankAccounts', $service->execute());
    }

    public function create()
    {
        return Inertia::render('Dashboard/Settings/BankAccountForm', [
            'bankAccount' => null,
        ]);
    }

    public function edit(BankAccount $bankAccount)
    {
        return Inertia::render('Dashboard/Settings/BankAccountForm', [
            'bankAccount' => $bankAccount,
        ]);
    }

    public function store(StoreBankAccountRequest $request, CreateBankAccountService $service)
    {
        $service->execute($request->payload());

        return redirect()
            ->route('settings.bank-accounts.index')
            ->with('success', 'Rekening bank berhasil ditambahkan.');
    }

    public function update(
        UpdateBankAccountRequest $request,
        BankAccount $bankAccount,
        UpdateBankAccountService $service
    ) {
        $service->execute($bankAccount, $request->payload());

        return redirect()
            ->route('settings.bank-accounts.index')
            ->with('success', 'Rekening bank berhasil diupdate.');
    }

    public function destroy(BankAccount $bankAccount, DeleteBankAccountService $service)
    {
        if (! $service->execute($bankAccount)) {
            return redirect()
                ->route('settings.bank-accounts.index')
                ->with('error', 'Rekening bank tidak bisa dihapus karena sudah digunakan di transaksi.');
        }

        return redirect()
            ->route('settings.bank-accounts.index')
            ->with('success', 'Rekening bank berhasil dihapus.');
    }

    public function toggleActive(BankAccount $bankAccount, ToggleBankAccountActiveService $service)
    {
        $bankAccount = $service->execute($bankAccount);
        $status = $bankAccount->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('settings.bank-accounts.index')
            ->with('success', "Rekening {$bankAccount->bank_name} berhasil {$status}.");
    }

    public function updateOrder(UpdateBankAccountOrderRequest $request, UpdateBankAccountOrderService $service)
    {
        $service->execute($request->validated('order'));

        return response()->json(['success' => true]);
    }
}

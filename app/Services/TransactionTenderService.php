<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Outlet;
use App\Models\PaymentSetting;
use App\Models\TransactionTender;
use Illuminate\Validation\ValidationException;

class TransactionTenderService
{
    public const METHODS = [
        TransactionTender::METHOD_CASH,
        TransactionTender::METHOD_BANK_TRANSFER,
        TransactionTender::METHOD_MIDTRANS,
        TransactionTender::METHOD_XENDIT,
        TransactionTender::METHOD_QRIS,
    ];

    public const GATEWAY_METHODS = [
        TransactionTender::METHOD_MIDTRANS,
        TransactionTender::METHOD_XENDIT,
        TransactionTender::METHOD_QRIS,
    ];

    public function __construct(private readonly PaymentSetting $paymentSetting) {}

    /**
     * @param  array<int, array<string, mixed>>  $input
     * @param  array<int, array<string, mixed>>  $normalized
     */
    public function normalize(array $input, int $grandTotal, bool $allowEmpty = true, ?Outlet $outlet = null): array
    {
        if (empty($input)) {
            if ($allowEmpty) {
                return [];
            }

            throw ValidationException::withMessages([
                'tenders' => 'Metode pembayaran wajib diisi.',
            ]);
        }

        if (count($input) > 2) {
            throw ValidationException::withMessages([
                'tenders' => 'Maksimal dua metode pembayaran diperbolehkan.',
            ]);
        }

        $normalized = [];
        $gatewayCount = 0;
        $bankTransferCount = 0;

        foreach (array_values($input) as $index => $tender) {
            $method = strtolower(trim((string) ($tender['method'] ?? '')));

            if (! in_array($method, self::METHODS, true)) {
                throw ValidationException::withMessages([
                    "tenders.{$index}.method" => 'Metode pembayaran tidak valid.',
                ]);
            }

            if (in_array($method, self::GATEWAY_METHODS, true)) {
                $gatewayCount++;
            }

            if ($method === TransactionTender::METHOD_BANK_TRANSFER) {
                $bankTransferCount++;
            }

            $amount = (int) ($tender['amount'] ?? 0);

            if ($amount < 1) {
                throw ValidationException::withMessages([
                    "tenders.{$index}.amount" => 'Nominal pembayaran harus lebih besar dari nol.',
                ]);
            }

            $cashReceived = null;
            $change = 0;

            if ($method === TransactionTender::METHOD_CASH) {
                $cashReceived = isset($tender['cash_received'])
                    ? max(0, (int) $tender['cash_received'])
                    : $amount;

                if ($cashReceived < $amount) {
                    throw ValidationException::withMessages([
                        "tenders.{$index}.cash_received" => 'Uang tunai kurang dari nominal tender.',
                    ]);
                }

                $change = $cashReceived - $amount;
            } elseif (isset($tender['cash_received']) && (int) $tender['cash_received'] > 0) {
                throw ValidationException::withMessages([
                    "tenders.{$index}.cash_received" => 'Uang diterima hanya berlaku untuk pembayaran tunai.',
                ]);
            }

            $bankAccountId = null;

            if ($method === TransactionTender::METHOD_BANK_TRANSFER) {
                $bankAccountId = $tender['bank_account_id'] ?? null;

                $account = $bankAccountId
                    ? BankAccount::where('id', $bankAccountId)->active()->forOutlet($outlet)->first()
                    : null;

                if (! $account) {
                    throw ValidationException::withMessages([
                        "tenders.{$index}.bank_account_id" => 'Rekening tujuan transfer wajib dipilih.',
                    ]);
                }

                $bankAccountId = $account->id;
            }

            $normalized[] = [
                'method' => $method,
                'amount' => $amount,
                'cash_received' => $cashReceived,
                'change' => $change,
                'bank_account_id' => $bankAccountId,
                'payment_status' => $method === TransactionTender::METHOD_CASH
                || $method === TransactionTender::METHOD_BANK_TRANSFER
                    ? TransactionTender::STATUS_PAID
                    : TransactionTender::STATUS_PENDING,
            ];
        }

        if ($gatewayCount > 1) {
            throw ValidationException::withMessages([
                'tenders' => 'Hanya satu pembayaran gateway yang diperbolehkan per transaksi.',
            ]);
        }

        if ($bankTransferCount > 1) {
            throw ValidationException::withMessages([
                'tenders' => 'Hanya satu transfer bank yang diperbolehkan per transaksi.',
            ]);
        }

        $total = array_sum(array_column($normalized, 'amount'));

        if ($total !== $grandTotal) {
            throw ValidationException::withMessages([
                'tenders' => 'Jumlah pembayaran harus sama dengan total transaksi.',
            ]);
        }

        foreach ($normalized as $tender) {
            if (in_array($tender['method'], self::GATEWAY_METHODS, true)) {
                $gateway = $tender['method'] === TransactionTender::METHOD_QRIS
                     ? $this->resolveQrisGateway($outlet)
                    : $tender['method'];

                if (! $gateway || ! $this->setting($outlet)->isGatewayReady($gateway)) {
                    throw ValidationException::withMessages([
                        'tenders' => 'Gateway pembayaran belum dikonfigurasi.',
                    ]);
                }
            }
        }

        return $normalized;
    }

    /**
     * Aggregate tender statuses into a parent transaction status.
     *
     * @param  iterable<App\Models\TransactionTender>  $tenders
     */
    public function aggregateStatus(iterable $tenders): string
    {
        $statuses = [];

        foreach ($tenders as $tender) {
            $statuses[] = is_array($tender)
                ? ($tender['payment_status'] ?? null)
                : $tender->payment_status;
        }

        if (in_array(TransactionTender::STATUS_FAILED, $statuses, true)) {
            return 'failed';
        }

        if (in_array(TransactionTender::STATUS_PENDING, $statuses, true)) {
            return 'pending';
        }

        return 'paid';
    }

    public function resolveQrisGateway(?Outlet $outlet = null): ?string
    {
        if ($this->setting($outlet)->isGatewayReady(PaymentSetting::GATEWAY_MIDTRANS)) {
            return PaymentSetting::GATEWAY_MIDTRANS;
        }

        if ($this->setting($outlet)->isGatewayReady(PaymentSetting::GATEWAY_XENDIT)) {
            return PaymentSetting::GATEWAY_XENDIT;
        }

        return null;
    }

    private function setting(?Outlet $outlet = null): PaymentSetting
    {
        return PaymentSetting::forOutlet($outlet) ?? $this->paymentSetting;
    }
}

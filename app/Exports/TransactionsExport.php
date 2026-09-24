<?php

namespace App\Exports;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TransactionsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    protected Request $request;

    /**
     * Warehouse ids the operator is allowed to export. Null means unrestricted.
     *
     * @var array<int>|null
     */
    protected ?array $warehouseIds;

    protected bool $includeLegacy;

    /**
     * @param  array<int>|null  $warehouseIds
     */
    public function __construct(Request $request, ?array $warehouseIds = null, bool $includeLegacy = false)
    {
        $this->request = $request;
        $this->warehouseIds = $warehouseIds;
        $this->includeLegacy = $includeLegacy;
    }

    public function collection(): Collection
    {
        $requestedWarehouse = $this->request->warehouse_id;

        return Transaction::with(['customer:id,name', 'cashier:id,name', 'tenders:id,transaction_id,method,amount'])
            ->when($this->warehouseIds !== null, function ($query) {
                $query->where(function ($query) {
                    if ($this->warehouseIds === []) {
                        if ($this->includeLegacy) {
                            $query->whereNull('warehouse_id');
                        } else {
                            $query->whereRaw('1 = 0');
                        }

                        return;
                    }

                    $query->whereIn('warehouse_id', $this->warehouseIds);
                    if ($this->includeLegacy) {
                        $query->orWhereNull('warehouse_id');
                    }
                });
            })
            ->when($requestedWarehouse, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($this->request->start_date, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->request->end_date, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at')
            ->get();
    }

    public function headings(): array
    {
        return ['Invoice', 'Tanggal', 'Kasir', 'Pelanggan', 'Metode', 'Status', 'Subtotal', 'Diskon', 'Ongkir', 'PPN', 'Grand Total'];
    }

    public function map($transaction): array
    {
        return [
            $transaction->invoice,
            $transaction->created_at->format('Y-m-d H:i:s'),
            $transaction->cashier?->name ?? '',
            $transaction->customer?->name ?? 'Umum',
            $transaction->payment_method === 'split'
                ? 'Split: '.$transaction->tenders->map(fn ($tender) => $tender->method.' '.$tender->amount)->join(', ')
                : ($transaction->payment_method ?? ''),
            $transaction->payment_status ?? '',
            (int) ($transaction->grand_total - $transaction->discount + ($transaction->shipping_cost ?? 0) - ($transaction->tax_total ?? 0)),
            (int) ($transaction->discount ?? 0),
            (int) ($transaction->shipping_cost ?? 0),
            (int) ($transaction->tax_total ?? 0),
            (int) $transaction->grand_total,
        ];
    }
}

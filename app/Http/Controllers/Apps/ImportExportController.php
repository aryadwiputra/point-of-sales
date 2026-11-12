<?php

namespace App\Http\Controllers\Apps;

use App\Exports\CustomersExport;
use App\Exports\ProductsExport;
use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Imports\CustomersImport;
use App\Imports\ProductsImport;
use App\Models\Outlet;
use App\Models\Warehouse;
use App\Services\OutletAccessService;
use App\Services\QuotaService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class ImportExportController extends Controller
{
    public function __construct(
        private readonly OutletAccessService $outletAccessService,
        private readonly QuotaService $quotaService,
    ) {}

    public function exportProducts()
    {
        return Excel::download(new ProductsExport, 'produk.xlsx');
    }

    public function exportCustomers()
    {
        return Excel::download(new CustomersExport, 'customer.xlsx');
    }

    public function exportTransactions(Request $request)
    {
        $warehouseIds = $request->user()->isSuperAdmin()
            ? null
            : $this->outletAccessService->warehousesFor($request->user())->pluck('id')->all();
        $includeLegacy = Outlet::active()->count() <= 1;

        return Excel::download(new TransactionsExport($request, $warehouseIds, $includeLegacy), 'transaksi.xlsx');
    }

    public function importProducts(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
        ]);

        try {
            $activeOutlet = $this->outletAccessService->activeOutlet($request);
            $targetWarehouseId = $request->input('warehouse_id')
                ?? $activeOutlet?->warehouses()->first()?->id
                ?? $this->outletAccessService->salesWarehousesFor($request->user())->first()?->id
                ?? Warehouse::active()->where('type', 'main')->first()?->id
                ?? Warehouse::active()->first()?->id;

            $import = new ProductsImport($targetWarehouseId);
            $rowCount = $this->countImportRows($import, $request->file('file'));

            if (! $this->quotaService->canCreateProduct($rowCount)) {
                $max = $this->quotaService->getPlan()?->max_products;

                return back()->with('error', "Import dibatalkan: {$rowCount} produk melebihi kuota paket Anda (maksimal {$max} produk). Silakan upgrade paket.");
            }

            Excel::import($import, $request->file('file'));

            $successCount = $import->getRowCount();

            return back()->with('success', "Import selesai. {$successCount} produk diimport.");
        } catch (ValidationException $e) {
            $failures = $e->failures();
            $messages = [];
            foreach (array_slice($failures, 0, 5) as $failure) {
                $messages[] = "Baris {$failure->row()}: ".implode(', ', $failure->errors());
            }

            return back()->with('error', 'Gagal validasi: '.implode(' | ', $messages));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal import: '.$e->getMessage());
        }
    }

    public function importCustomers(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        try {
            $import = new CustomersImport;
            $rowCount = $this->countImportRows($import, $request->file('file'));

            if (! $this->quotaService->canAddCustomer($rowCount)) {
                $max = $this->quotaService->getPlan()?->max_customers;

                return back()->with('error', "Import dibatalkan: {$rowCount} pelanggan melebihi kuota paket Anda (maksimal {$max} pelanggan). Silakan upgrade paket.");
            }

            Excel::import($import, $request->file('file'));

            return back()->with('success', 'Import customer selesai.');
        } catch (ValidationException $e) {
            $failures = $e->failures();
            $messages = [];
            foreach (array_slice($failures, 0, 5) as $failure) {
                $messages[] = "Baris {$failure->row()}: ".implode(', ', $failure->errors());
            }

            return back()->with('error', 'Gagal validasi: '.implode(' | ', $messages));
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal import: '.$e->getMessage());
        }
    }

    public function downloadTemplate(string $type)
    {
        $headings = match ($type) {
            'products' => ['barcode', 'sku', 'nama', 'deskripsi', 'kategori', 'harga_beli', 'harga_jual', 'stok', 'min_stok', 'max_stok', 'tipe_pajak', 'tarif_pajak'],
            'customers' => ['nama', 'telepon', 'alamat'],
            default => abort(404),
        };

        return Excel::download(
            new class($headings) implements FromArray, WithHeadings
            {
                public function __construct(private array $headings) {}

                public function headings(): array
                {
                    return $this->headings;
                }

                public function array(): array
                {
                    return [];
                }
            },
            "template-{$type}.xlsx"
        );
    }

    private function countImportRows(object $import, $file): int
    {
        try {
            $rows = Excel::toCollection($import, $file)->first();

            // Subtract the heading row.
            return max(0, ($rows?->count() ?? 0) - 1);
        } catch (\Throwable) {
            // If the file cannot be parsed here, let the import surface the validation error.
            return 0;
        }
    }
}

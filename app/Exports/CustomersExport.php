<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CustomersExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function collection()
    {
        return Customer::orderBy('name')->get();
    }

    public function headings(): array
    {
        return ['Nama', 'Telepon', 'Alamat', 'Provinsi', 'Kota', 'Kecamatan', 'Desa', 'Member', 'Tier', 'Poin'];
    }

    public function map($customer): array
    {
        return [
            $customer->name,
            $customer->no_telp ?? '',
            $customer->address ?? '',
            $customer->province_name ?? '',
            $customer->regency_name ?? '',
            $customer->district_name ?? '',
            $customer->village_name ?? '',
            $customer->is_loyalty_member ? 'Ya' : 'Tidak',
            $customer->loyalty_tier ?? 'regular',
            (int) ($customer->loyalty_points ?? 0),
        ];
    }
}

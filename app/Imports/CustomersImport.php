<?php

namespace App\Imports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CustomersImport implements ToModel, WithBatchInserts, WithChunkReading, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        return new Customer([
            'name' => $row['nama'],
            'no_telp' => $row['telepon'] ?? null,
            'address' => $row['alamat'] ?? null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function customValidationMessages()
    {
        return [
            'nama.required' => 'Nama customer wajib diisi.',
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}

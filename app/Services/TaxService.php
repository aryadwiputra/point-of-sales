<?php

namespace App\Services;

use App\Models\Setting;

class TaxService
{
    private float $defaultRate;

    public function __construct()
    {
        $this->defaultRate = (float) (Setting::get('tax_default_rate', '11.00'));
    }

    public function getDefaultRate(): float
    {
        return $this->defaultRate;
    }

    public function calculateLineItem(int $lineTotal, string $taxType = 'exclusive', float $taxRate = 0): array
    {
        if ($taxRate <= 0) {
            return [
                'tax_amount' => 0,
                'tax_rate' => $taxRate,
                'line_total_before_tax' => $lineTotal,
                'line_total_after_tax' => $lineTotal,
            ];
        }

        if ($taxType === 'inclusive') {
            $taxAmount = (int) round($lineTotal - ($lineTotal / (1 + $taxRate / 100)));
            $beforeTax = $lineTotal - $taxAmount;
        } else {
            $taxAmount = (int) round($lineTotal * $taxRate / 100);
            $beforeTax = $lineTotal;
        }

        return [
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'line_total_before_tax' => $beforeTax,
            'line_total_after_tax' => $beforeTax + $taxAmount,
        ];
    }

    public function calculateTransactionTax(array $items, float $defaultRate): array
    {
        $taxTotal = 0;
        $result = [];
        $rate = $defaultRate;

        foreach ($items as $item) {
            $lineTotal = (int) ($item['line_total'] ?? $item['price'] ?? 0);
            $itemTaxType = $item['tax_type'] ?? 'exclusive';
            $itemTaxRate = (float) ($item['tax_rate'] ?? $defaultRate);

            $taxResult = $this->calculateLineItem($lineTotal, $itemTaxType, $itemTaxRate);

            $taxTotal += $taxResult['tax_amount'];
            $result[] = [
                ...$item,
                ...$taxResult,
            ];

            if ($itemTaxRate > 0) {
                $rate = $itemTaxRate;
            }
        }

        return [
            'tax_total' => $taxTotal,
            'tax_rate' => $rate,
            'items' => $result,
        ];
    }
}

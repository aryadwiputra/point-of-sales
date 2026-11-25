import React, { useState, useRef } from "react";
import { BarcodeLabelGrid } from "./BarcodeLabel";
import {
    IconX,
    IconPrinter,
    IconBarcode,
    IconTruck,
} from "@tabler/icons-react";

/**
 * BarcodePrintModal - Modal for printing barcode labels
 */
export default function BarcodePrintModal({
    isOpen,
    onClose,
    products = [],
    singleProduct = null,
}) {
    const [size, setSize] = useState("70x50");
    const [showPrice, setShowPrice] = useState(true);
    const [showOngkir, setShowOngkir] = useState(false);
    const [ongkirAmount, setOngkirAmount] = useState(0);
    const [copies, setCopies] = useState(1);
    const printRef = useRef(null);

    const productsToPrint = singleProduct ? [singleProduct] : products;

    const handlePrint = () => {
        const printContent = printRef.current;
        if (!printContent) return;

        const printWindow = window.open("", "_blank");
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print Barcode</title>
                <style>
                    @page {
                        size: A4;
                        margin: 5mm;
                    }
                    body {
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 0;
                    }
                    .barcode-grid {
                        display: grid;
                        gap: 2mm;
                    }
                    .barcode-label {
                        border: 1px solid #ccc;
                        padding: 2mm;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        page-break-inside: avoid;
                        background: white;
                    }
                </style>
            </head>
            <body>
                ${printContent.innerHTML}
                <script>
                    setTimeout(() => {
                        window.print();
                        window.close();
                    }, 500);
                </script>
            </body>
            </html>
        `);
        printWindow.document.close();
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Backdrop */}
            <div className="absolute inset-0 bg-black/50" onClick={onClose} />

            {/* Modal */}
            <div className="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                {/* Header */}
                <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800">
                    <div className="flex items-center gap-2">
                        <IconBarcode size={24} className="text-primary-500" />
                        <h2 className="text-lg font-bold text-slate-800 dark:text-white">
                            Cetak Barcode
                        </h2>
                        <span className="px-2 py-0.5 bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 text-xs font-medium rounded-full">
                            {productsToPrint.length} produk
                        </span>
                    </div>
                    <button
                        onClick={onClose}
                        className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    >
                        <IconX size={20} className="text-slate-500" />
                    </button>
                </div>

                {/* Options */}
                <div className="p-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        {/* Size */}
                        <div>
                            <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                                Ukuran Label
                            </label>
                            <select
                                value={size}
                                onChange={(e) => setSize(e.target.value)}
                                className="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                            >
                                <option value="50x30">50 x 30 mm</option>
                                <option value="70x50">70 x 50 mm</option>
                                <option value="100x50">100 x 50 mm</option>
                            </select>
                        </div>

                        {/* Copies */}
                        <div>
                            <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                                Jumlah per Produk
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="100"
                                value={copies}
                                onChange={(e) =>
                                    setCopies(Number(e.target.value))
                                }
                                className="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                            />
                        </div>

                        {/* Show Price */}
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="showPrice"
                                checked={showPrice}
                                onChange={(e) => setShowPrice(e.target.checked)}
                                className="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500"
                            />
                            <label
                                htmlFor="showPrice"
                                className="text-sm text-slate-700 dark:text-slate-300"
                            >
                                Tampilkan Harga
                            </label>
                        </div>

                        {/* Show Ongkir */}
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="showOngkir"
                                checked={showOngkir}
                                onChange={(e) =>
                                    setShowOngkir(e.target.checked)
                                }
                                className="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500"
                            />
                            <label
                                htmlFor="showOngkir"
                                className="text-sm text-slate-700 dark:text-slate-300"
                            >
                                Tampilkan Ongkir
                            </label>
                        </div>
                    </div>

                    {/* Ongkir Amount */}
                    {showOngkir && (
                        <div className="mt-3">
                            <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">
                                <IconTruck size={14} className="inline mr-1" />
                                Nominal Ongkir
                            </label>
                            <input
                                type="number"
                                min="0"
                                value={ongkirAmount}
                                onChange={(e) =>
                                    setOngkirAmount(Number(e.target.value))
                                }
                                placeholder="Contoh: 15000"
                                className="w-48 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm"
                            />
                        </div>
                    )}
                </div>

                {/* Preview */}
                <div
                    className="p-4 overflow-auto"
                    style={{ maxHeight: "400px" }}
                >
                    <p className="text-xs text-slate-500 mb-3">Preview:</p>
                    <div
                        ref={printRef}
                        className="bg-white p-4 border border-dashed border-slate-300 rounded-lg"
                    >
                        <BarcodeLabelGrid
                            products={productsToPrint}
                            size={size}
                            showPrice={showPrice}
                            showOngkir={showOngkir}
                            ongkirAmount={ongkirAmount}
                            copies={copies}
                        />
                    </div>
                </div>

                {/* Footer */}
                <div className="flex items-center justify-end gap-3 p-4 border-t border-slate-200 dark:border-slate-800">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 text-sm font-medium transition-colors"
                    >
                        Batal
                    </button>
                    <button
                        onClick={handlePrint}
                        className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium transition-colors"
                    >
                        <IconPrinter size={18} />
                        Cetak
                    </button>
                </div>
            </div>
        </div>
    );
}

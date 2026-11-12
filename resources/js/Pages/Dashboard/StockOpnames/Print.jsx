import React from "react";
import { Head, usePage } from "@inertiajs/react";
import { IconPrinter } from "@tabler/icons-react";

const formatDateTime = (value) =>
    value
        ? new Intl.DateTimeFormat("id-ID", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(value))
        : "-";

export default function Print({ stockOpname, printedAt }) {
    const { storeProfile } = usePage().props || {};
    const items = stockOpname?.items || [];

    return (
        <>
            <Head title={`Kartu Stock Opname - ${stockOpname.code}`} />

            <div className="min-h-screen bg-slate-100 p-4 print:bg-white print:p-0 text-slate-900">
                <div className="mx-auto max-w-5xl">
                    <div className="mb-4 flex items-center justify-between gap-3 print:hidden">
                        <div>
                            <h1 className="text-lg font-semibold">Kartu Stock Opname</h1>
                            <p className="text-sm text-slate-500">
                                Cetak atau simpan sebagai PDF, lalu isi stok fisik/selisih di lapangan.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-primary-500"
                        >
                            <IconPrinter size={18} />
                            Cetak / Simpan PDF
                        </button>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm print:rounded-none print:border-0 print:p-0 print:shadow-none">
                        <div className="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div className="text-xs uppercase tracking-wide text-slate-500">Kartu Stock Opname</div>
                                <div className="text-2xl font-bold">{stockOpname.code}</div>
                                <div className="mt-1 text-sm text-slate-600">
                                    {storeProfile?.name || "Bihaju POS"}
                                </div>
                                {storeProfile?.address && (
                                    <div className="text-xs text-slate-500">{storeProfile.address}</div>
                                )}
                            </div>
                            <div className="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:text-right">
                                <div className="text-slate-500">Gudang/Cabang</div>
                                <div className="font-medium text-slate-900">
                                    {stockOpname.warehouse ? `${stockOpname.warehouse.code} — ${stockOpname.warehouse.name}` : "-"}
                                </div>
                                <div className="text-slate-500">Status</div>
                                <div className="font-medium text-slate-900">{stockOpname.status === "finalized" ? "Finalized" : "Draft"}</div>
                                <div className="text-slate-500">Dibuat</div>
                                <div className="font-medium text-slate-900">{formatDateTime(stockOpname.created_at)}</div>
                                <div className="text-slate-500">Dicetak</div>
                                <div className="font-medium text-slate-900">{formatDateTime(printedAt)}</div>
                            </div>
                        </div>

                        <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <div className="text-xs text-slate-500">Dibuat oleh</div>
                                <div className="font-medium">{stockOpname.creator?.name || "-"}</div>
                            </div>
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <div className="text-xs text-slate-500">Total item</div>
                                <div className="font-medium">{items.length} produk</div>
                            </div>
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <div className="text-xs text-slate-500">Catatan</div>
                                <div className="font-medium">{stockOpname.notes || "-"}</div>
                            </div>
                        </div>

                        <div className="mt-5 overflow-x-auto">
                            <table className="w-full border-collapse text-sm">
                                <thead>
                                    <tr className="bg-slate-50 text-left">
                                        <th className="border border-slate-300 px-2 py-2 w-10 text-center">No</th>
                                        <th className="border border-slate-300 px-2 py-2 w-12 text-center">Cek</th>
                                        <th className="border border-slate-300 px-2 py-2">Produk</th>
                                        <th className="border border-slate-300 px-2 py-2">SKU/Barcode</th>
                                        <th className="border border-slate-300 px-2 py-2 w-24 text-center">Stok Sistem</th>
                                        <th className="border border-slate-300 px-2 py-2 w-28 text-center">Stok Fisik</th>
                                        <th className="border border-slate-300 px-2 py-2 w-24 text-center">Selisih</th>
                                        <th className="border border-slate-300 px-2 py-2">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="border border-slate-300 px-3 py-8 text-center text-slate-500">
                                                Belum ada produk pada sesi stock opname ini.
                                            </td>
                                        </tr>
                                    ) : (
                                        items.map((item, index) => (
                                            <tr key={item.id} className="align-top">
                                                <td className="border border-slate-300 px-2 py-2 text-center">{index + 1}</td>
                                                <td className="border border-slate-300 px-2 py-2 text-center">
                                                    <span className="inline-block h-4 w-4 border border-slate-400 rounded-sm align-middle" />
                                                </td>
                                                <td className="border border-slate-300 px-2 py-2">
                                                    <div className="font-medium text-slate-900">{item.product?.title || "-"}</div>
                                                    <div className="text-xs text-slate-500">{item.product?.category?.name || "-"}</div>
                                                </td>
                                                <td className="border border-slate-300 px-2 py-2 text-xs text-slate-700">
                                                    <div>{item.product?.sku || "-"}</div>
                                                    <div>{item.product?.barcode || ""}</div>
                                                </td>
                                                <td className="border border-slate-300 px-2 py-2 text-center font-semibold text-slate-900">
                                                    {item.system_stock ?? 0}
                                                </td>
                                                <td className="border border-slate-300 px-2 py-2 text-center text-slate-300">
                                                    {stockOpname.status === "finalized" && item.physical_stock !== null ? item.physical_stock : ""}
                                                </td>
                                                <td className="border border-slate-300 px-2 py-2 text-center text-slate-300">
                                                    {stockOpname.status === "finalized" && item.difference !== null
                                                        ? item.difference > 0
                                                            ? `+${item.difference}`
                                                            : item.difference
                                                        : ""}
                                                </td>
                                                <td className="border border-slate-300 px-2 py-2 text-xs text-slate-700">
                                                    {stockOpname.status === "finalized" ? item.adjustment_reason || "" : ""}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-6 grid gap-6 sm:grid-cols-2 print:mt-8">
                            <div className="rounded-xl border border-slate-200 p-4 text-sm text-slate-600">
                                <div className="font-medium text-slate-900 mb-2">Petunjuk</div>
                                <ul className="list-disc space-y-1 pl-5">
                                    <li>Centang kolom <strong>Cek</strong> saat barang sudah dihitung.</li>
                                    <li><strong>Stok Sistem</strong> adalah jumlah stok yang tercatat di sistem saat item ditambahkan.</li>
                                    <li>Isi <strong>Stok Fisik</strong> sesuai hasil hitung lapangan, lalu hitung <strong>Selisih</strong> (fisik - sistem).</li>
                                    <li>Tulis keterangan jika ada selisih (rusak, hilang, salah input, dsb.).</li>
                                </ul>
                            </div>
                            <div className="grid grid-cols-2 gap-4 text-center text-sm">
                                <div>
                                    <div className="text-slate-500">Petugas Hitung</div>
                                    <div className="mt-16 border-t border-slate-400 pt-1 text-slate-700">(............................)</div>
                                </div>
                                <div>
                                    <div className="text-slate-500">Mengetahui</div>
                                    <div className="mt-16 border-t border-slate-400 pt-1 text-slate-700">(............................)</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <style>{`
                @media print {
                    @page { size: A4 landscape; margin: 10mm; }
                    html, body { background: #fff !important; }
                }
            `}</style>
        </>
    );
}

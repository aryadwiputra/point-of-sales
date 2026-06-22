import React, { useState } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import { IconArrowLeft, IconArrowsLeftRight, IconPlus, IconTrash, IconPackage } from "@tabler/icons-react";
import toast from "react-hot-toast";

export default function Create({ warehouses, products }) {
    const { data, setData, post, processing, errors } = useForm({
        source_warehouse_id: "",
        destination_warehouse_id: "",
        document_number: "",
        notes: "",
        items: [],
    });

    const [searchProduct, setSearchProduct] = useState("");
    const filteredProducts = products.filter(
        (p) => p.title.toLowerCase().includes(searchProduct.toLowerCase()) || (p.sku && p.sku.toLowerCase().includes(searchProduct.toLowerCase()))
    );

    const addItem = (product) => {
        if (data.items.some((i) => i.product_id === product.id)) {
            toast.error("Produk sudah ada di daftar.");
            return;
        }
        setData("items", [...data.items, { product_id: product.id, product_title: product.title, product_sku: product.sku || "-", qty: 1 }]);
    };

    const removeItem = (index) => setData("items", data.items.filter((_, i) => i !== index));
    const updateItem = (index, value) => {
        const items = [...data.items];
        items[index] = { ...items[index], qty: Math.max(1, parseInt(value) || 1) };
        setData("items", items);
    };

    const submit = (e) => {
        e.preventDefault();
        if (data.items.length === 0) {
            toast.error("Tambahkan minimal satu item.");
            return;
        }
        if (data.source_warehouse_id === data.destination_warehouse_id) {
            toast.error("Gudang asal dan tujuan harus berbeda.");
            return;
        }
        post(route("stock-transfers.store"), {
            onError: () => toast.error("Gagal membuat transfer"),
        });
    };

    const warehousesExcept = (excludeId) => warehouses.filter((w) => String(w.id) !== String(excludeId));

    return (
        <>
            <Head title="Transfer Stok Baru" />
            <div className="mb-6">
                <Link href={route("stock-transfers.index")} className="mb-3 inline-flex items-center gap-2 text-sm text-slate-500 hover:text-primary-600">
                    <IconArrowLeft size={16} /> Kembali ke daftar transfer
                </Link>
                <h1 className="flex items-center gap-2 text-2xl font-bold text-slate-900 dark:text-white">
                    <IconArrowsLeftRight size={28} className="text-primary-500" />
                    Transfer Stok Baru
                </h1>
            </div>

            <form onSubmit={submit} className="max-w-5xl">
                <div className="space-y-6">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Informasi Transfer</h2>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label className="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Gudang Asal</label>
                                <select
                                    value={data.source_warehouse_id}
                                    onChange={(e) => setData({ ...data, source_warehouse_id: e.target.value })}
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    <option value="">Pilih Gudang</option>
                                    {warehouses.map((w) => (<option key={w.id} value={w.id}>{w.code} — {w.name}</option>))}
                                </select>
                                {errors.source_warehouse_id && <p className="mt-1 text-xs text-danger-500">{errors.source_warehouse_id}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Gudang Tujuan</label>
                                <select
                                    value={data.destination_warehouse_id}
                                    onChange={(e) => setData({ ...data, destination_warehouse_id: e.target.value })}
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    <option value="">Pilih Gudang</option>
                                    {warehousesExcept(data.source_warehouse_id).map((w) => (<option key={w.id} value={w.id}>{w.code} — {w.name}</option>))}
                                </select>
                                {errors.destination_warehouse_id && <p className="mt-1 text-xs text-danger-500">{errors.destination_warehouse_id}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Nomor Dokumen</label>
                                <input type="text" value={data.document_number} onChange={(e) => setData("document_number", e.target.value)} placeholder="Kosongkan auto-generate" className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-semibold text-slate-700 dark:text-slate-200">Catatan</label>
                                <input type="text" value={data.notes} onChange={(e) => setData("notes", e.target.value)} placeholder="Opsional" className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Item Transfer</h2>
                        <div className="mb-4">
                            <input type="text" value={searchProduct} onChange={(e) => setSearchProduct(e.target.value)} placeholder="Cari produk untuk ditambahkan..." className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" />
                        </div>
                        {searchProduct && filteredProducts.length > 0 && (
                            <div className="mb-4 max-h-48 space-y-2 overflow-y-auto rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                                {filteredProducts.map((product) => (
                                    <button key={product.id} type="button" onClick={() => addItem(product)} className="flex w-full items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-4 py-3 text-left text-sm transition hover:border-primary-200 hover:bg-primary-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-primary-700 dark:hover:bg-primary-950/20">
                                        <div>
                                            <p className="font-medium text-slate-800 dark:text-slate-200">{product.title}</p>
                                            <p className="text-xs text-slate-500">{product.sku || "-"} &bull; Stok: {product.stock}</p>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}
                        {data.items.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-slate-200 dark:border-slate-700">
                                            <th className="px-3 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Produk</th>
                                            <th className="px-3 py-2 text-right font-semibold text-slate-700 dark:text-slate-200">Qty</th>
                                            <th className="w-16 px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.items.map((item, index) => (
                                            <tr key={index} className="border-b border-slate-100 dark:border-slate-800">
                                                <td className="px-3 py-3">
                                                    <p className="font-medium text-slate-800 dark:text-slate-200">{item.product_title}</p>
                                                    <p className="text-xs text-slate-500">{item.product_sku}</p>
                                                </td>
                                                <td className="px-3 py-3 text-right">
                                                    <input type="number" min="1" value={item.qty} onChange={(e) => updateItem(index, e.target.value)} className="h-10 w-20 rounded-lg border border-slate-200 bg-slate-50 px-3 text-right text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" />
                                                </td>
                                                <td className="px-3 py-3 text-center">
                                                    <button type="button" onClick={() => removeItem(index)} className="rounded-lg p-1.5 text-slate-400 transition hover:bg-danger-50 hover:text-danger-500"><IconTrash size={16} /></button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed border-slate-200 p-8 text-center dark:border-slate-700">
                                <IconPackage size={40} className="mx-auto text-slate-300 dark:text-slate-600" />
                                <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">Cari produk di atas untuk ditambahkan ke transfer.</p>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-3">
                        <Link href={route("stock-transfers.index")} className="flex h-11 items-center rounded-xl border border-slate-200 bg-white px-6 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Batal</Link>
                        <Button type="submit" icon={<IconPlus size={18} />} className="bg-primary-500 hover:bg-primary-600 text-white shadow-lg shadow-primary-500/30" label={processing ? "Menyimpan..." : "Simpan Draft"} disabled={processing} />
                    </div>
                </div>
            </form>
        </>
    );
}

Create.layout = (page) => <DashboardLayout children={page} />;

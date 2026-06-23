import React, { useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { IconArrowLeft, IconTrash, IconPlus } from "@tabler/icons-react";
import toast from "react-hot-toast";

const formatPrice = (v = 0) => Number(v).toLocaleString("id-ID");

export default function PriceListItems({ priceList, products }) {
    const { flash } = usePage().props;
    const [search, setSearch] = useState("");
    if (flash?.success) toast.success(flash.success);

    const filtered = products.filter(p => p.title.toLowerCase().includes(search.toLowerCase()) || (p.sku || "").includes(search));
    const existingProductIds = priceList.items.map(i => i.product_id);

    const addPrice = (product) => {
        const price = prompt(`Harga untuk ${product.title}:`, String(product.sell_price));
        if (price === null) return;
        router.post(route("price-lists.items.update", priceList.id), { product_id: product.id, price: parseInt(price) });
    };

    const removeItem = (item) => {
        if (!confirm(`Hapus ${item.product?.title} dari price list?`)) return;
        router.delete(route("price-lists.items.destroy", [priceList.id, item.product_id]));
    };

    return (
        <>
            <Head title={`Price List: ${priceList.name}`} />
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link href={route("price-lists.index")} className="p-2 rounded-lg text-slate-500 hover:bg-slate-100"><IconArrowLeft size={20} /></Link>
                    <div>
                        <h1 className="text-2xl font-bold">{priceList.name}</h1>
                        <p className="text-sm text-slate-500">{priceList.items.length} produk</p>
                    </div>
                </div>

                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                    <h3 className="font-semibold mb-3">Tambah Harga</h3>
                    <input value={search} onChange={e => setSearch(e.target.value)} placeholder="Cari produk..." className="h-11 w-full rounded-xl border border-slate-200 px-4 text-sm mb-3" />
                    {search && filtered.filter(p => !existingProductIds.includes(p.id)).slice(0, 10).map(p => (
                        <button key={p.id} type="button" onClick={() => addPrice(p)} className="flex w-full items-center justify-between px-4 py-3 rounded-lg hover:bg-slate-50 text-sm transition">
                            <span>{p.title} <span className="text-slate-400">({p.sku || "-"})</span></span>
                            <span className="text-primary-500 font-medium">{formatPrice(p.sell_price)}</span>
                        </button>
                    ))}
                </div>

                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                    {priceList.items.length > 0 ? (
                        <div className="divide-y">
                            {priceList.items.map(item => (
                                <div key={item.id} className="p-4 flex items-center gap-4">
                                    <div className="flex-1">
                                        <p className="font-semibold">{item.product?.title || "-"}</p>
                                        <p className="text-sm text-slate-500">{item.product?.sku || "-"} • Harga normal: {formatPrice(item.product?.sell_price)}</p>
                                    </div>
                                    <span className="font-bold text-primary-600">{formatPrice(item.price)}</span>
                                    <button onClick={() => removeItem(item)} className="p-2 rounded-lg text-danger-500 hover:bg-danger-50"><IconTrash size={18} /></button>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="p-8 text-center text-slate-400">Belum ada item. Cari produk di atas untuk menambah.</div>
                    )}
                </div>
            </div>
        </>
    );
}

PriceListItems.layout = (page) => <DashboardLayout children={page} />;

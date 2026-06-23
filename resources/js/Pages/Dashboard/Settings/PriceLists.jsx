import React, { useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { IconListDetails, IconPlus, IconPencil, IconTrash, IconEye } from "@tabler/icons-react";
import toast from "react-hot-toast";

export default function PriceLists({ priceLists }) {
    const { flash } = usePage().props;
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ name: "", slug: "", customer_scope: "all", notes: "", priority: 0 });

    if (flash?.success) toast.success(flash.success);

    const resetForm = () => {
        setForm({ name: "", slug: "", customer_scope: "all", notes: "", priority: 0 });
        setEditing(null);
        setShowForm(false);
    };

    const openEdit = (pl) => {
        setEditing(pl);
        setForm({ name: pl.name, slug: pl.slug, customer_scope: pl.customer_scope, notes: pl.notes || "", priority: pl.priority });
        setShowForm(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editing) {
            router.put(route("price-lists.update", editing.id), form, { onSuccess: () => resetForm() });
        } else {
            router.post(route("price-lists.store"), form, { onSuccess: () => resetForm() });
        }
    };

    const handleDelete = (pl) => {
        if (!confirm(`Hapus price list ${pl.name}?`)) return;
        router.delete(route("price-lists.destroy", pl.id));
    };

    const scopeLabel = { all: "Semua", walk_in: "Walk-in", registered: "Terdaftar", member: "Member", segment: "Segmen" };

    return (
        <>
            <Head title="Price List" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold text-slate-900 dark:text-white">
                            <IconListDetails size={28} className="text-primary-500" />
                            Price List
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">Harga khusus per kelompok pelanggan</p>
                    </div>
                    <button onClick={() => { resetForm(); setShowForm(true); }} className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium transition-colors">
                        <IconPlus size={18} /> Baru
                    </button>
                </div>

                {showForm && (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                        <h3 className="font-semibold mb-4">{editing ? "Edit Price List" : "Price List Baru"}</h3>
                        <form onSubmit={handleSubmit} className="space-y-4 max-w-lg">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">Nama</label>
                                    <input value={form.name} onChange={e => setForm({...form, name: e.target.value})} className="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm" required />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Slug</label>
                                    <input value={form.slug} onChange={e => setForm({...form, slug: e.target.value})} className="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm" required disabled={!!editing} />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium mb-1">Kelompok</label>
                                    <select value={form.customer_scope} onChange={e => setForm({...form, customer_scope: e.target.value})} className="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm">
                                        <option value="all">Semua Pelanggan</option>
                                        <option value="walk_in">Walk-in</option>
                                        <option value="registered">Terdaftar</option>
                                        <option value="member">Member</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">Prioritas</label>
                                    <input type="number" value={form.priority} onChange={e => setForm({...form, priority: parseInt(e.target.value) || 0})} className="h-11 w-full rounded-xl border border-slate-200 px-3 text-sm" />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Catatan</label>
                                <textarea value={form.notes} onChange={e => setForm({...form, notes: e.target.value})} className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" rows={2} />
                            </div>
                            <div className="flex gap-3">
                                <button type="submit" className="px-4 py-2 rounded-xl bg-primary-500 text-white text-sm font-medium">{editing ? "Update" : "Simpan"}</button>
                                <button type="button" onClick={resetForm} className="px-4 py-2 rounded-xl border text-sm font-medium">Batal</button>
                            </div>
                        </form>
                    </div>
                )}

                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                    {priceLists.length > 0 ? (
                        <div className="divide-y">
                            {priceLists.map(pl => (
                                <div key={pl.id} className="p-4 flex items-center gap-4">
                                    <div className="flex-1">
                                        <p className="font-semibold">{pl.name} <span className="text-xs text-slate-400">({pl.slug})</span></p>
                                        <p className="text-sm text-slate-500">{scopeLabel[pl.customer_scope] || pl.customer_scope} • {pl.items_count} produk • Prioritas {pl.priority}</p>
                                    </div>
                                    <Link href={route("price-lists.show", pl.id)} className="p-2 rounded-lg text-slate-500 hover:bg-slate-100"><IconEye size={18} /></Link>
                                    <button onClick={() => openEdit(pl)} className="p-2 rounded-lg text-slate-500 hover:bg-slate-100"><IconPencil size={18} /></button>
                                    <button onClick={() => handleDelete(pl)} className="p-2 rounded-lg text-danger-500 hover:bg-danger-50"><IconTrash size={18} /></button>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="p-8 text-center text-slate-400">Belum ada price list.</div>
                    )}
                </div>
            </div>
        </>
    );
}

PriceLists.layout = (page) => <DashboardLayout children={page} />;

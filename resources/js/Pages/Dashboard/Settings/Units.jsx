import React, { useEffect, useState } from "react";
import { Head, usePage, router } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import {
    IconRulerMeasure,
    IconPlus,
    IconPencil,
    IconTrash,
} from "@tabler/icons-react";
import toast from "react-hot-toast";
import { useAuthorization } from "@/Utils/authorization";
import Input from "@/Components/Dashboard/Input";

export default function Units({ units = [] }) {
    const { flash } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can("units-create");
    const canUpdate = can("units-update");
    const canDelete = can("units-delete");

    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ code: "", name: "", symbol: "" });
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const resetForm = () => {
        setForm({ code: "", name: "", symbol: "" });
        setErrors({});
        setEditing(null);
        setShowForm(false);
    };

    const openEdit = (u) => {
        setEditing(u);
        setForm({ code: u.code, name: u.name, symbol: u.symbol });
        setErrors({});
        setShowForm(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setErrors({});

        if (editing) {
            router.put(route("settings.units.update", editing.id), form, {
                onError: (err) => setErrors(err),
                onSuccess: () => resetForm(),
            });
        } else {
            router.post(route("settings.units.store"), form, {
                onError: (err) => setErrors(err),
                onSuccess: () => resetForm(),
            });
        }
    };

    const handleDelete = (u) => {
        if (!confirm(`Hapus satuan ${u.name}?`)) return;
        router.delete(route("settings.units.destroy", u.id));
    };

    return (
        <>
            <Head title="Pengaturan Satuan" />

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <IconRulerMeasure size={28} className="text-primary-500" />
                    Satuan
                </h1>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Master satuan produk (pcs, box, karton, dll) untuk penjualan multi-satuan
                </p>
            </div>

            <div className="max-w-4xl space-y-6">
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <h3 className="font-semibold text-slate-800 dark:text-white">
                            Daftar Satuan ({units.length})
                        </h3>
                        {canCreate && (
                            <button
                                onClick={() => { resetForm(); setShowForm(true); }}
                                className="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium transition-colors"
                            >
                                <IconPlus size={18} />
                                Tambah Satuan
                            </button>
                        )}
                    </div>

                    {units.length > 0 ? (
                        <div className="divide-y divide-slate-200 dark:divide-slate-800">
                            {units.map((u) => (
                                <div key={u.id} className="p-4 flex items-center gap-4">
                                    <div className="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                                        <IconRulerMeasure size={22} className="text-slate-500" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="font-semibold text-slate-800 dark:text-white truncate">
                                                {u.name}
                                            </p>
                                            <span className="px-2 py-0.5 rounded-lg text-xs font-medium bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-400">
                                                {u.symbol}
                                            </span>
                                        </div>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">
                                            {u.code}
                                            {u.products_count > 0
                                                ? ` • dipakai ${u.products_count} produk`
                                                : ""}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        {canUpdate && (
                                            <button
                                                onClick={() => openEdit(u)}
                                                className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                            >
                                                <IconPencil size={18} />
                                            </button>
                                        )}
                                        {canDelete && u.products_count === 0 && (
                                            <button
                                                onClick={() => handleDelete(u)}
                                                className="p-2 rounded-lg text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-900/20 transition-colors"
                                            >
                                                <IconTrash size={18} />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="p-8 text-center">
                            <IconRulerMeasure size={48} className="mx-auto text-slate-300 dark:text-slate-600 mb-3" />
                            <p className="text-slate-500 dark:text-slate-400">Belum ada satuan</p>
                        </div>
                    )}
                </div>

                {showForm && (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 space-y-4">
                        <h3 className="font-semibold text-slate-800 dark:text-white">
                            {editing ? "Edit Satuan" : "Tambah Satuan Baru"}
                        </h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <Input
                                    label="Kode"
                                    placeholder="PCS"
                                    value={form.code}
                                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                                    errors={errors.code}
                                />
                                <Input
                                    label="Nama Satuan"
                                    placeholder="Pieces"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    errors={errors.name}
                                />
                                <Input
                                    label="Simbol"
                                    placeholder="pcs"
                                    value={form.symbol}
                                    onChange={(e) => setForm({ ...form, symbol: e.target.value })}
                                    errors={errors.symbol}
                                />
                            </div>
                            <div className="flex items-center gap-3">
                                <button
                                    type="submit"
                                    className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary-500 hover:bg-primary-600 text-white text-sm font-semibold transition-colors"
                                >
                                    {editing ? "Update" : "Simpan"}
                                </button>
                                <button
                                    type="button"
                                    onClick={resetForm}
                                    className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                >
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                )}
            </div>
        </>
    );
}

Units.layout = (page) => <DashboardLayout children={page} />;

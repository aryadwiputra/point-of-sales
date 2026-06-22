import React, { useEffect, useState } from "react";
import { Head, usePage, router } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import {
    IconBuildingWarehouse,
    IconPlus,
    IconPencil,
    IconTrash,
    IconDots,
} from "@tabler/icons-react";
import toast from "react-hot-toast";
import { useAuthorization } from "@/Utils/authorization";
import Input from "@/Components/Dashboard/Input";

export default function Warehouses({ warehouses = [] }) {
    const { flash } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can("warehouses-create");
    const canUpdate = can("warehouses-update");
    const canDelete = can("warehouses-delete");

    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({
        code: "",
        name: "",
        type: "branch",
        address: "",
        phone: "",
        is_active: true,
        sort_order: 0,
    });
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const resetForm = () => {
        setForm({ code: "", name: "", type: "branch", address: "", phone: "", is_active: true, sort_order: 0 });
        setErrors({});
        setEditing(null);
        setShowForm(false);
    };

    const openEdit = (w) => {
        setEditing(w);
        setForm({
            code: w.code,
            name: w.name,
            type: w.type,
            address: w.address || "",
            phone: w.phone || "",
            is_active: w.is_active,
            sort_order: w.sort_order,
        });
        setErrors({});
        setShowForm(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setErrors({});

        if (editing) {
            router.put(route("settings.warehouses.update", editing.id), form, {
                onError: (err) => setErrors(err),
                onSuccess: () => resetForm(),
            });
        } else {
            router.post(route("settings.warehouses.store"), form, {
                onError: (err) => setErrors(err),
                onSuccess: () => resetForm(),
            });
        }
    };

    const handleDelete = (w) => {
        if (!confirm(`Hapus gudang ${w.name}?`)) return;
        router.delete(route("settings.warehouses.destroy", w.id));
    };

    const typeLabel = (type) => {
        const labels = { main: "Utama", branch: "Cabang", warehouse: "Gudang" };
        return labels[type] || type;
    };

    const typeColor = (type) => {
        const colors = {
            main: "bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400",
            branch: "bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-400",
            warehouse: "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400",
        };
        return colors[type] || colors.warehouse;
    };

    return (
        <>
            <Head title="Pengaturan Gudang" />

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <IconBuildingWarehouse size={28} className="text-primary-500" />
                    Gudang / Cabang
                </h1>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Kelola gudang dan cabang untuk pemisahan stok per lokasi
                </p>
            </div>

            <div className="max-w-4xl space-y-6">
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <h3 className="font-semibold text-slate-800 dark:text-white">
                            Daftar Gudang ({warehouses.length})
                        </h3>
                        {canCreate && (
                            <button
                                onClick={() => { resetForm(); setShowForm(true); }}
                                className="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium transition-colors"
                            >
                                <IconPlus size={18} />
                                Tambah Gudang
                            </button>
                        )}
                    </div>

                    {warehouses.length > 0 ? (
                        <div className="divide-y divide-slate-200 dark:divide-slate-800">
                            {warehouses.map((w) => (
                                <div key={w.id} className={`p-4 flex items-center gap-4 ${!w.is_active ? "opacity-50" : ""}`}>
                                    <div className="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                                        <IconBuildingWarehouse size={22} className="text-slate-500" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="font-semibold text-slate-800 dark:text-white truncate">
                                                {w.name}
                                            </p>
                                            <span className={`px-2 py-0.5 rounded-lg text-xs font-medium ${typeColor(w.type)}`}>
                                                {typeLabel(w.type)}
                                            </span>
                                        </div>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">
                                            {w.code}
                                            {w.address ? ` • ${w.address}` : ""}
                                            {w.phone ? ` • ${w.phone}` : ""}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        {w.type !== "main" && (
                                            <span className="text-xs text-slate-400 dark:text-slate-500">
                                                Sort: {w.sort_order}
                                            </span>
                                        )}
                                        {canUpdate && (
                                            <button
                                                onClick={() => openEdit(w)}
                                                className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                            >
                                                <IconPencil size={18} />
                                            </button>
                                        )}
                                        {canDelete && w.type !== "main" && (
                                            <button
                                                onClick={() => handleDelete(w)}
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
                            <IconBuildingWarehouse size={48} className="mx-auto text-slate-300 dark:text-slate-600 mb-3" />
                            <p className="text-slate-500 dark:text-slate-400">Belum ada gudang</p>
                        </div>
                    )}
                </div>

                {showForm && (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 space-y-4">
                        <h3 className="font-semibold text-slate-800 dark:text-white">
                            {editing ? "Edit Gudang" : "Tambah Gudang Baru"}
                        </h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <Input
                                    label="Kode"
                                    placeholder="WH-002"
                                    value={form.code}
                                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                                    errors={errors.code}
                                    disabled={!!editing}
                                />
                                <Input
                                    label="Nama Gudang"
                                    placeholder="Gudang Cabang A"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    errors={errors.name}
                                />
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Tipe</label>
                                    <select
                                        value={form.type}
                                        onChange={(e) => setForm({ ...form, type: e.target.value })}
                                        className="w-full h-11 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                                    >
                                        <option value="branch">Cabang</option>
                                        <option value="warehouse">Gudang</option>
                                    </select>
                                    {errors.type && (
                                        <p className="text-xs text-danger-500 mt-1">{errors.type}</p>
                                    )}
                                </div>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                        Alamat
                                    </label>
                                    <textarea
                                        value={form.address}
                                        onChange={(e) => setForm({ ...form, address: e.target.value })}
                                        className="w-full h-20 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 resize-none"
                                    />
                                    {errors.address && (
                                        <p className="text-xs text-danger-500 mt-1">{errors.address}</p>
                                    )}
                                </div>
                                <div className="space-y-4">
                                    <Input
                                        label="Telepon"
                                        placeholder="021-12345678"
                                        value={form.phone}
                                        onChange={(e) => setForm({ ...form, phone: e.target.value })}
                                        errors={errors.phone}
                                    />
                                    <div className="grid grid-cols-2 gap-4">
                                        <Input
                                            label="Urutan"
                                            type="number"
                                            value={form.sort_order}
                                            onChange={(e) => setForm({ ...form, sort_order: parseInt(e.target.value) || 0 })}
                                            errors={errors.sort_order}
                                        />
                                        <div className="flex items-end pb-2">
                                            <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                                <input
                                                    type="checkbox"
                                                    checked={form.is_active}
                                                    onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                                                    className="rounded border-slate-300 dark:border-slate-600 text-primary-600 focus:ring-primary-500"
                                                />
                                                Aktif
                                            </label>
                                        </div>
                                    </div>
                                </div>
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

Warehouses.layout = (page) => <DashboardLayout children={page} />;

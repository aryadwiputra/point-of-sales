import React, { useEffect, useMemo, useState } from "react";
import { Head, router, useForm, usePage } from "@inertiajs/react";
import toast from "react-hot-toast";
import {
    IconAlertTriangle,
    IconDeviceFloppy,
    IconPencil,
    IconPlus,
    IconSearch,
    IconToolsKitchen2,
    IconTrash,
} from "@tabler/icons-react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import Button from "@/Components/Dashboard/Button";
import Input from "@/Components/Dashboard/Input";
import Modal from "@/Components/Dashboard/Modal";

function Checklist({ items, selected, onToggle, emptyLabel }) {
    if (items.length === 0) {
        return <p className="text-sm text-slate-500">{emptyLabel}</p>;
    }

    return (
        <div className="max-h-44 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
            {items.map((item) => (
                <label key={item.id} className="flex cursor-pointer items-center gap-2 rounded px-1 py-1 text-sm hover:bg-slate-50">
                    <input
                        type="checkbox"
                        checked={selected.includes(item.id)}
                        onChange={() => onToggle(item.id)}
                        className="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span className="text-slate-700">{item.label}</span>
                </label>
            ))}
        </div>
    );
}

const emptyForm = (outletId) => ({
    outlet_id: outletId ?? "",
    name: "",
    sort_order: 0,
    is_active: true,
    category_ids: [],
    user_ids: [],
    product_ids: [],
});

export default function Index({
    outlets = [],
    stations = [],
    categories = [],
    users = [],
    products = [],
    unmappedCategories = [],
    kitchenEnabled = true,
    filters = {},
}) {
    const { flash } = usePage().props;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [productSearch, setProductSearch] = useState("");
    const defaultOutletId = filters?.outlet_id ?? outlets[0]?.id ?? null;

    const form = useForm(emptyForm(defaultOutletId));

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    const categoryItems = useMemo(
        () => categories.map((category) => ({ id: category.id, label: category.name })),
        [categories],
    );

    const userItems = useMemo(
        () => users.map((user) => ({ id: user.id, label: `${user.name} (${user.email})` })),
        [users],
    );

    const productItems = useMemo(() => {
        const needle = productSearch.trim().toLowerCase();

        return products
            .filter((product) => !needle || product.title.toLowerCase().includes(needle))
            .slice(0, 100)
            .map((product) => ({ id: product.id, label: product.title }));
    }, [productSearch, products]);

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm(defaultOutletId));
        form.clearErrors();
        setProductSearch("");
        setModalOpen(true);
    };

    const openEdit = (station) => {
        setEditing(station);
        form.setData({
            outlet_id: station.outlet_id ?? "",
            name: station.name,
            sort_order: station.sort_order ?? 0,
            is_active: Boolean(station.is_active),
            category_ids: station.category_ids ?? [],
            user_ids: station.user_ids ?? [],
            product_ids: station.product_ids ?? [],
        });
        form.clearErrors();
        setProductSearch("");
        setModalOpen(true);
    };

    const closeModal = () => {
        if (form.processing) {
            return;
        }

        setModalOpen(false);
        setEditing(null);
        form.clearErrors();
    };

    const toggleArray = (field, id) => {
        const current = form.data[field] ?? [];
        form.setData(
            field,
            current.includes(id) ? current.filter((value) => value !== id) : [...current, id],
        );
    };

    const submit = (event) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                setEditing(null);
            },
        };

        if (editing) {
            form.patch(route("kitchen.stations.update", editing.id), options);
            return;
        }

        form.post(route("kitchen.stations.store"), options);
    };

    const destroy = (station) => {
        if (!window.confirm(`Hapus station "${station.name}"?`)) {
            return;
        }

        router.delete(route("kitchen.stations.destroy", station.id), { preserveScroll: true });
    };

    const changeOutlet = (event) => {
        router.get(
            route("kitchen.stations.index"),
            event.target.value ? { outlet_id: event.target.value } : {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const toggleKitchen = () => {
        router.post(
            route("kitchen.settings"),
            { enabled: !kitchenEnabled },
            { preserveScroll: true },
        );
    };

    return (
        <DashboardLayout>
            <Head title="Station Dapur" />

            <div className="space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Station Dapur</h1>
                        <p className="text-sm text-slate-600">
                            Petakan kategori dan produk ke station, lalu tugaskan pengguna dapur.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={toggleKitchen}
                            className={`rounded-lg border px-3 py-2 text-sm font-semibold ${
                                kitchenEnabled
                                    ? "border-success-300 bg-success-50 text-success-800"
                                    : "border-slate-300 bg-slate-100 text-slate-700"
                            }`}
                        >
                            Kitchen display: {kitchenEnabled ? "Aktif" : "Nonaktif"}
                        </button>
                        <Button
                            type="button"
                            onClick={openCreate}
                            icon={<IconPlus size={16} strokeWidth={2} />}
                            label="Tambah station"
                            className="inline-flex items-center gap-1.5"
                        />
                    </div>
                </div>

                <div className="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
                    <label className="text-sm font-medium text-slate-700">
                        Outlet
                        <select
                            value={defaultOutletId ?? ""}
                            onChange={changeOutlet}
                            className="mt-1 block w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">Tanpa outlet (legacy)</option>
                            {outlets.map((outlet) => (
                                <option key={outlet.id} value={outlet.id}>
                                    {outlet.name} ({outlet.code})
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                {unmappedCategories.length > 0 ? (
                    <div className="rounded-xl border border-warning-200 bg-warning-50 p-4">
                        <div className="flex items-start gap-2">
                            <IconAlertTriangle size={18} strokeWidth={1.8} className="mt-0.5 text-warning-700" />
                            <div>
                                <p className="text-sm font-semibold text-warning-900">
                                    Kategori belum dipetakan di outlet ini
                                </p>
                                <p className="mt-1 text-sm text-warning-800">
                                    {unmappedCategories.map((category) => category.name).join(", ")}
                                </p>
                                <p className="mt-1 text-xs text-warning-700">
                                    Produk aktif pada kategori ini tidak akan muncul di KDS sampai dipetakan.
                                </p>
                            </div>
                        </div>
                    </div>
                ) : null}

                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <tr>
                                <th className="px-4 py-3">Station</th>
                                <th className="px-4 py-3">Kategori</th>
                                <th className="px-4 py-3">Pengguna</th>
                                <th className="px-4 py-3">Override produk</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {stations.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                                        Belum ada station untuk outlet ini.
                                    </td>
                                </tr>
                            ) : (
                                stations.map((station) => (
                                    <tr key={station.id}>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2 font-semibold text-slate-900">
                                                <IconToolsKitchen2 size={16} strokeWidth={1.8} className="text-primary-600" />
                                                {station.name}
                                            </div>
                                            <p className="mt-0.5 text-xs text-slate-500">Urutan {station.sort_order}</p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">{station.category_ids?.length ?? 0}</td>
                                        <td className="px-4 py-3 text-slate-700">{station.user_ids?.length ?? 0}</td>
                                        <td className="px-4 py-3 text-slate-700">{station.product_ids?.length ?? 0}</td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`rounded-full px-2 py-1 text-xs font-semibold ${
                                                    station.is_active
                                                        ? "bg-success-50 text-success-700"
                                                        : "bg-slate-100 text-slate-600"
                                                }`}
                                            >
                                                {station.is_active ? "Aktif" : "Nonaktif"}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(station)}
                                                    className="rounded-lg border border-slate-300 p-2 text-slate-700 hover:bg-slate-50"
                                                    aria-label={`Ubah ${station.name}`}
                                                >
                                                    <IconPencil size={16} strokeWidth={1.8} />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => destroy(station)}
                                                    className="rounded-lg border border-danger-200 p-2 text-danger-600 hover:bg-danger-50"
                                                    aria-label={`Hapus ${station.name}`}
                                                >
                                                    <IconTrash size={16} strokeWidth={1.8} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal show={modalOpen} onClose={closeModal} title={editing ? "Ubah station" : "Tambah station"} maxWidth="2xl">
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="text-sm font-medium text-slate-700">
                                Outlet
                                <select
                                    value={form.data.outlet_id ?? ""}
                                    onChange={(event) => form.setData("outlet_id", event.target.value)}
                                    className="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                >
                                    <option value="">Tanpa outlet (legacy)</option>
                                    {outlets.map((outlet) => (
                                        <option key={outlet.id} value={outlet.id}>
                                            {outlet.name} ({outlet.code})
                                        </option>
                                    ))}
                                </select>
                            </label>
                            {form.errors.outlet_id ? (
                                <p className="mt-1 text-xs text-danger-600">{form.errors.outlet_id}</p>
                            ) : null}
                        </div>

                        <Input
                            type="text"
                            label="Nama station"
                            value={form.data.name}
                            onChange={(event) => form.setData("name", event.target.value)}
                            errors={form.errors.name}
                            placeholder="Hot Kitchen"
                        />

                        <Input
                            type="number"
                            label="Urutan"
                            value={form.data.sort_order}
                            onChange={(event) => form.setData("sort_order", event.target.value)}
                            errors={form.errors.sort_order}
                        />

                        <label className="flex items-center gap-2 pt-7 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                checked={Boolean(form.data.is_active)}
                                onChange={(event) => form.setData("is_active", event.target.checked)}
                                className="rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                            />
                            Station aktif
                        </label>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <p className="mb-1 text-sm font-medium text-slate-700">Mapping kategori</p>
                            <Checklist
                                items={categoryItems}
                                selected={form.data.category_ids ?? []}
                                onToggle={(id) => toggleArray("category_ids", id)}
                                emptyLabel="Belum ada kategori."
                            />
                            {form.errors.category_ids ? (
                                <p className="mt-1 text-xs text-danger-600">{form.errors.category_ids}</p>
                            ) : null}
                        </div>

                        <div>
                            <p className="mb-1 text-sm font-medium text-slate-700">Pengguna dapur</p>
                            <Checklist
                                items={userItems}
                                selected={form.data.user_ids ?? []}
                                onToggle={(id) => toggleArray("user_ids", id)}
                                emptyLabel="Belum ada pengguna dengan akses dapur."
                            />
                            {form.errors.user_ids ? (
                                <p className="mt-1 text-xs text-danger-600">{form.errors.user_ids}</p>
                            ) : null}
                        </div>
                    </div>

                    <div>
                        <div className="mb-1 flex items-center justify-between gap-2">
                            <p className="text-sm font-medium text-slate-700">Override produk</p>
                            <div className="relative">
                                <IconSearch
                                    size={15}
                                    strokeWidth={1.8}
                                    className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-slate-400"
                                />
                                <input
                                    type="search"
                                    value={productSearch}
                                    onChange={(event) => setProductSearch(event.target.value)}
                                    placeholder="Cari produk..."
                                    className="w-56 rounded-lg border border-slate-300 py-1.5 pl-7 pr-2 text-sm"
                                />
                            </div>
                        </div>
                        <Checklist
                            items={productItems}
                            selected={form.data.product_ids ?? []}
                            onToggle={(id) => toggleArray("product_ids", id)}
                            emptyLabel="Tidak ada produk yang cocok."
                        />
                        {form.errors.product_ids ? (
                            <p className="mt-1 text-xs text-danger-600">{form.errors.product_ids}</p>
                        ) : null}
                        <p className="mt-1 text-xs text-slate-500">
                            Override produk menang atas mapping kategori. Maksimal 100 produk ditampilkan; gunakan pencarian.
                        </p>
                    </div>

                    <div className="flex justify-end gap-2 border-t border-slate-200 pt-4">
                        <button
                            type="button"
                            onClick={closeModal}
                            className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700"
                        >
                            Batal
                        </button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            icon={<IconDeviceFloppy size={16} strokeWidth={1.8} />}
                            label="Simpan"
                            className="inline-flex items-center gap-1.5"
                        />
                    </div>
                </form>
            </Modal>
        </DashboardLayout>
    );
}
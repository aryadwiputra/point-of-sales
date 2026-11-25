import React, { useState } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, router, useForm } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import Input from "@/Components/Dashboard/Input";
import Modal from "@/Components/Dashboard/Modal";
import { useAuthorization } from "@/Utils/authorization";
import {
    IconAdjustments,
    IconCirclePlus,
    IconDatabaseOff,
    IconPencil,
    IconTrash,
} from "@tabler/icons-react";
import toast from "react-hot-toast";

const formatDelta = (value) => {
    const amount = new Intl.NumberFormat("id-ID").format(Math.abs(value));
    return `${value >= 0 ? "+" : "-"}Rp${amount}`;
};

export default function Index({ groups }) {
    const { can } = useAuthorization();
    const canCreate = can("modifiers-create");
    const canEdit = can("modifiers-edit");
    const canDelete = can("modifiers-delete");

    const [groupModal, setGroupModal] = useState(false);
    const [editingGroup, setEditingGroup] = useState(null);
    const [optionModal, setOptionModal] = useState(false);
    const [editingOption, setEditingOption] = useState(null);
    const [optionGroupId, setOptionGroupId] = useState(null);

    const groupForm = useForm({
        name: "",
        selection_type: "single",
        min_select: 0,
        max_select: 1,
        sort_order: 0,
        is_active: true,
    });

    const optionForm = useForm({
        modifier_group_id: null,
        name: "",
        price_delta: 0,
        is_default: false,
        sort_order: 0,
        is_active: true,
    });

    const openCreateGroup = () => {
        setEditingGroup(null);
        groupForm.reset();
        groupForm.setData("sort_order", groups.length);
        setGroupModal(true);
    };

    const openEditGroup = (group) => {
        setEditingGroup(group);
        groupForm.setData({
            name: group.name,
            selection_type: group.selection_type,
            min_select: group.min_select,
            max_select: group.max_select ?? 0,
            sort_order: group.sort_order,
            is_active: group.is_active,
        });
        setGroupModal(true);
    };

    const submitGroup = (event) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(editingGroup ? "Grup modifier diperbarui." : "Grup modifier ditambahkan.");
                setGroupModal(false);
            },
            onError: () => toast.error("Gagal menyimpan grup modifier."),
        };

        if (editingGroup) {
            groupForm.patch(route("modifiers.groups.update", editingGroup.id), options);
        } else {
            groupForm.post(route("modifiers.groups.store"), options);
        }
    };

    const openCreateOption = (group) => {
        setEditingOption(null);
        setOptionGroupId(group.id);
        optionForm.reset();
        optionForm.setData("modifier_group_id", group.id);
        setOptionModal(true);
    };

    const openEditOption = (group, option) => {
        setEditingOption(option);
        setOptionGroupId(group.id);
        optionForm.setData({
            modifier_group_id: group.id,
            name: option.name,
            price_delta: option.price_delta,
            is_default: option.is_default,
            sort_order: option.sort_order,
            is_active: option.is_active,
        });
        setOptionModal(true);
    };

    const submitOption = (event) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(editingOption ? "Opsi modifier diperbarui." : "Opsi modifier ditambahkan.");
                setOptionModal(false);
            },
            onError: () => toast.error("Gagal menyimpan opsi modifier."),
        };

        if (editingOption) {
            optionForm.patch(route("modifiers.options.update", editingOption.id), options);
        } else {
            optionForm.post(route("modifiers.options.store"), options);
        }
    };

    const deleteGroup = (group) => {
        if (!confirm(`Hapus grup "${group.name}" beserta semua opsinya?`)) return;
        router.delete(route("modifiers.groups.destroy", group.id), {
            preserveScroll: true,
            onSuccess: () => toast.success("Grup modifier dihapus."),
            onError: () => toast.error("Gagal menghapus grup modifier."),
        });
    };

    const deleteOption = (option) => {
        if (!confirm(`Hapus opsi "${option.name}"?`)) return;
        router.delete(route("modifiers.options.destroy", option.id), {
            preserveScroll: true,
            onSuccess: () => toast.success("Opsi modifier dihapus."),
            onError: () => toast.error("Gagal menghapus opsi modifier."),
        });
    };

    return (
        <>
            <Head title="Modifier Menu" />

            <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                        Modifier Menu
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                        Grup opsi seperti ukuran, tingkat gula, dan topping untuk menu F&B.
                    </p>
                </div>
                {canCreate && (
                    <Button
                        type={"link"}
                        icon={<IconCirclePlus size={18} strokeWidth={1.5} />}
                        className={"bg-primary-500 hover:bg-primary-600 text-white shadow-lg shadow-primary-500/30"}
                        label={"Tambah Grup"}
                        onClick={openCreateGroup}
                    />
                )}
            </div>

            {groups.length > 0 ? (
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    {groups.map((group) => (
                        <div
                            key={group.id}
                            className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <div className="flex items-center gap-2">
                                        <IconAdjustments size={18} className="text-primary-500" strokeWidth={1.5} />
                                        <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                                            {group.name}
                                        </h2>
                                        {!group.is_active && (
                                            <span className="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                                Nonaktif
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        {group.selection_type === "single" ? "Pilih satu" : "Pilih banyak"}
                                        {" · "}min {group.min_select}
                                        {group.max_select ? ` · maks ${group.max_select}` : ""}
                                        {" · "}dipakai {group.products_count} produk
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    {canEdit && (
                                        <button
                                            type="button"
                                            onClick={() => openEditGroup(group)}
                                            className="p-2 rounded-xl border bg-warning-100 border-warning-200 text-warning-600 hover:bg-warning-200 dark:bg-warning-900/50 dark:border-warning-800 dark:text-warning-400"
                                        >
                                            <IconPencil size={16} strokeWidth={1.5} />
                                        </button>
                                    )}
                                    {canDelete && (
                                        <button
                                            type="button"
                                            onClick={() => deleteGroup(group)}
                                            className="p-2 rounded-xl border bg-danger-100 border-danger-200 text-danger-600 hover:bg-danger-200 dark:bg-danger-900/50 dark:border-danger-800 dark:text-danger-400"
                                        >
                                            <IconTrash size={16} strokeWidth={1.5} />
                                        </button>
                                    )}
                                </div>
                            </div>

                            <div className="mt-4 space-y-2">
                                {group.modifiers.length > 0 ? (
                                    group.modifiers.map((option) => (
                                        <div
                                            key={option.id}
                                            className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 dark:border-slate-800 px-3 py-2"
                                        >
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm text-slate-800 dark:text-slate-200">
                                                    {option.name}
                                                </span>
                                                <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                                    {formatDelta(option.price_delta)}
                                                </span>
                                                {option.is_default && (
                                                    <span className="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                                                        Default
                                                    </span>
                                                )}
                                                {!option.is_active && (
                                                    <span className="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                                        Nonaktif
                                                    </span>
                                                )}
                                            </div>
                                            <div className="flex gap-1">
                                                {canEdit && (
                                                    <button
                                                        type="button"
                                                        onClick={() => openEditOption(group, option)}
                                                        className="p-1.5 rounded-lg border bg-warning-100 border-warning-200 text-warning-600 hover:bg-warning-200 dark:bg-warning-900/50 dark:border-warning-800 dark:text-warning-400"
                                                    >
                                                        <IconPencil size={14} strokeWidth={1.5} />
                                                    </button>
                                                )}
                                                {canDelete && (
                                                    <button
                                                        type="button"
                                                        onClick={() => deleteOption(option)}
                                                        className="p-1.5 rounded-lg border bg-danger-100 border-danger-200 text-danger-600 hover:bg-danger-200 dark:bg-danger-900/50 dark:border-danger-800 dark:text-danger-400"
                                                    >
                                                        <IconTrash size={14} strokeWidth={1.5} />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-slate-400 dark:text-slate-500">
                                        Belum ada opsi.
                                    </p>
                                )}
                            </div>

                            {canCreate && (
                                <button
                                    type="button"
                                    onClick={() => openCreateOption(group)}
                                    className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                >
                                    <IconCirclePlus size={16} strokeWidth={1.5} />
                                    Tambah Opsi
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            ) : (
                <div className="flex flex-col items-center justify-center py-16 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    <div className="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                        <IconDatabaseOff size={32} className="text-slate-400" strokeWidth={1.5} />
                    </div>
                    <h3 className="text-lg font-medium text-slate-800 dark:text-slate-200 mb-1">
                        Belum Ada Grup Modifier
                    </h3>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
                        Buat grup seperti Ukuran atau Topping untuk dipasang ke menu.
                    </p>
                    {canCreate && (
                        <Button
                            type={"link"}
                            icon={<IconCirclePlus size={18} />}
                            className={"bg-primary-500 hover:bg-primary-600 text-white"}
                            label={"Tambah Grup"}
                            onClick={openCreateGroup}
                        />
                    )}
                </div>
            )}

            <Modal
                show={groupModal}
                onClose={() => setGroupModal(false)}
                title={editingGroup ? "Edit Grup Modifier" : "Tambah Grup Modifier"}
            >
                <form onSubmit={submitGroup} className="space-y-4">
                    <Input
                        label="Nama Grup"
                        value={groupForm.data.name}
                        onChange={(event) => groupForm.setData("name", event.target.value)}
                        errors={groupForm.errors.name}
                        placeholder="Contoh: Ukuran, Level Gula, Topping"
                        required
                    />
                    <div>
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Tipe Pilihan
                        </label>
                        <select
                            value={groupForm.data.selection_type}
                            onChange={(event) => groupForm.setData("selection_type", event.target.value)}
                            className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-slate-100"
                        >
                            <option value="single">Pilih satu</option>
                            <option value="multiple">Pilih banyak</option>
                        </select>
                        {groupForm.errors.selection_type && (
                            <p className="mt-1 text-sm text-danger-500">{groupForm.errors.selection_type}</p>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Input
                            label="Minimal Dipilih"
                            type="number"
                            min="0"
                            value={groupForm.data.min_select}
                            onChange={(event) => groupForm.setData("min_select", parseInt(event.target.value) || 0)}
                            errors={groupForm.errors.min_select}
                        />
                        <Input
                            label="Maksimal Dipilih"
                            type="number"
                            min="1"
                            disabled={groupForm.data.selection_type === "single"}
                            value={groupForm.data.max_select}
                            onChange={(event) => groupForm.setData("max_select", parseInt(event.target.value) || 1)}
                            errors={groupForm.errors.max_select}
                        />
                    </div>
                    <Input
                        label="Urutan"
                        type="number"
                        min="0"
                        value={groupForm.data.sort_order}
                        onChange={(event) => groupForm.setData("sort_order", parseInt(event.target.value) || 0)}
                        errors={groupForm.errors.sort_order}
                    />
                    <label className="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={groupForm.data.is_active}
                            onChange={(event) => groupForm.setData("is_active", event.target.checked)}
                            className="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span className="text-sm text-slate-700 dark:text-slate-300">
                            Grup aktif
                        </span>
                    </label>
                    <div className="flex justify-end gap-3 pt-2">
                        <Button type={"button"} label={"Batal"} onClick={() => setGroupModal(false)} />
                        <Button
                            type={"submit"}
                            label={editingGroup ? "Perbarui" : "Simpan"}
                            disabled={groupForm.processing}
                            className={"bg-primary-500 hover:bg-primary-600 text-white"}
                        />
                    </div>
                </form>
            </Modal>

            <Modal
                show={optionModal}
                onClose={() => setOptionModal(false)}
                title={editingOption ? "Edit Opsi Modifier" : "Tambah Opsi Modifier"}
            >
                <form onSubmit={submitOption} className="space-y-4">
                    <Input
                        label="Nama Opsi"
                        value={optionForm.data.name}
                        onChange={(event) => optionForm.setData("name", event.target.value)}
                        errors={optionForm.errors.name}
                        placeholder="Contoh: Large, Less Sugar, Boba"
                        required
                    />
                    <Input
                        label="Selisih Harga (Rp)"
                        type="number"
                        value={optionForm.data.price_delta}
                        onChange={(event) => optionForm.setData("price_delta", parseInt(event.target.value) || 0)}
                        errors={optionForm.errors.price_delta}
                        placeholder="Contoh: 5000 atau -2000"
                    />
                    <Input
                        label="Urutan"
                        type="number"
                        min="0"
                        value={optionForm.data.sort_order}
                        onChange={(event) => optionForm.setData("sort_order", parseInt(event.target.value) || 0)}
                        errors={optionForm.errors.sort_order}
                    />
                    <label className="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={optionForm.data.is_default}
                            onChange={(event) => optionForm.setData("is_default", event.target.checked)}
                            className="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span className="text-sm text-slate-700 dark:text-slate-300">
                            Terpilih otomatis
                        </span>
                    </label>
                    <label className="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={optionForm.data.is_active}
                            onChange={(event) => optionForm.setData("is_active", event.target.checked)}
                            className="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span className="text-sm text-slate-700 dark:text-slate-300">
                            Opsi aktif
                        </span>
                    </label>
                    <div className="flex justify-end gap-3 pt-2">
                        <Button type={"button"} label={"Batal"} onClick={() => setOptionModal(false)} />
                        <Button
                            type={"submit"}
                            label={editingOption ? "Perbarui" : "Simpan"}
                            disabled={optionForm.processing}
                            className={"bg-primary-500 hover:bg-primary-600 text-white"}
                        />
                    </div>
                </form>
            </Modal>
        </>
    );
}

Index.layout = (page) => <DashboardLayout children={page} />;
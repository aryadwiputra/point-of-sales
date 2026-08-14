import React, { useState, useRef, useCallback } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, router, useForm } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import {
    IconCirclePlus,
    IconDatabaseOff,
    IconPencilCog,
    IconTrash,
    IconDownload,
    IconPlus,
} from "@tabler/icons-react";
import Table from "@/Components/Dashboard/Table";
import Modal from "@/Components/Dashboard/Modal";
import Input from "@/Components/Dashboard/Input";
import Select from "@/Components/Dashboard/Select";
import { useAuthorization } from "@/Utils/authorization";
import toast from "react-hot-toast";

const GRID_SIZE = 40;

function TableShape({ table, onDragStart, isSelected, onClick }) {
    const { shape, name, capacity, is_active } = table;
    const colorClass = is_active
        ? isSelected
            ? "fill-primary-500"
            : "fill-primary-200 dark:fill-primary-800"
        : "fill-slate-200 dark:fill-slate-700";
    const textClass = is_active
        ? "text-slate-700 dark:text-slate-200"
        : "text-slate-400 dark:text-slate-500";

    const dragProps = onDragStart
        ? { draggable: true, onDragStart: (e) => onDragStart(e, table) }
        : {};

    return (
        <g
            {...dragProps}
            onClick={() => onClick && onClick(table)}
            className="cursor-pointer"
        >
            {shape === "circle" ? (
                <circle
                    cx={table.pos_x * GRID_SIZE + GRID_SIZE / 2}
                    cy={table.pos_y * GRID_SIZE + GRID_SIZE / 2}
                    r={GRID_SIZE / 2 - 2}
                    className={`${colorClass} ${isSelected ? "stroke-primary-600 dark:stroke-primary-400" : "stroke-slate-300 dark:stroke-slate-600"} stroke-2 transition-colors`}
                />
            ) : (
                <rect
                    x={table.pos_x * GRID_SIZE + 1}
                    y={table.pos_y * GRID_SIZE + 1}
                    width={GRID_SIZE - 2}
                    height={GRID_SIZE - 2}
                    rx={4}
                    className={`${colorClass} ${isSelected ? "stroke-primary-600 dark:stroke-primary-400" : "stroke-slate-300 dark:stroke-slate-600"} stroke-2 transition-colors`}
                />
            )}
            <text
                x={table.pos_x * GRID_SIZE + GRID_SIZE / 2}
                y={table.pos_y * GRID_SIZE + GRID_SIZE / 2}
                textAnchor="middle"
                dominantBaseline="central"
                className={`${textClass} text-xs font-semibold pointer-events-none select-none`}
                fontSize={10}
            >
                {name}
            </text>
        </g>
    );
}

export default function Index({ tables, areas, filters }) {
    const { can } = useAuthorization();
    const canCreate = can("dine-tables-create");
    const canUpdate = can("dine-tables-update");
    const canDelete = can("dine-tables-delete");
    const canAccess = can("dine-tables-access");

    const [selectedTable, setSelectedTable] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [editingTable, setEditingTable] = useState(null);
    const [filterArea, setFilterArea] = useState(filters?.area_id || "");
    const [activeView, setActiveView] = useState("grid");
    const dragItem = useRef(null);
    const svgRef = useRef(null);

    const { data, setData, post, patch, processing, errors, reset } = useForm({
        dine_area_id: "",
        name: "",
        capacity: 4,
        shape: "square",
        pos_x: 0,
        pos_y: 0,
        sort_order: 0,
        is_active: true,
    });

    const gridWidth = 25;
    const gridHeight = 15;

    const handleDragStart = (e, table) => {
        dragItem.current = table;
    };

    const handleDrop = useCallback(
        (e) => {
            e.preventDefault();
            if (!dragItem.current || !canUpdate) return;
            const rect = svgRef.current.getBoundingClientRect();
            const x = Math.round((e.clientX - rect.left) / (rect.width / gridWidth));
            const y = Math.round((e.clientY - rect.top) / (rect.height / gridHeight));
            const clampedX = Math.max(0, Math.min(gridWidth - 1, x));
            const clampedY = Math.max(0, Math.min(gridHeight - 1, y));

            patch(route("dine-tables.update", dragItem.current.id), {
                data: { ...dragItem.current, pos_x: clampedX, pos_y: clampedY },
                onSuccess: () => toast.success("Posisi meja diperbarui."),
                preserveScroll: true,
            });
            dragItem.current = null;
        },
        [canUpdate]
    );

    const handleDragOver = (e) => {
        e.preventDefault();
    };

    const openCreate = () => {
        setEditingTable(null);
        reset({
            dine_area_id: filterArea || (areas[0]?.id ?? ""),
            name: "",
            capacity: 4,
            shape: "square",
            pos_x: 0,
            pos_y: 0,
            sort_order: tables.length,
            is_active: true,
        });
        setModalOpen(true);
    };

    const openEdit = (table) => {
        setEditingTable(table);
        setData({
            dine_area_id: table.dine_area_id ?? "",
            name: table.name,
            capacity: table.capacity,
            shape: table.shape,
            pos_x: table.pos_x,
            pos_y: table.pos_y,
            sort_order: table.sort_order,
            is_active: table.is_active,
        });
        setModalOpen(true);
    };

    const submit = (e) => {
        e.preventDefault();
        const onSuccess = () => {
            toast.success(editingTable ? "Meja berhasil diperbarui." : "Meja berhasil ditambahkan.");
            setModalOpen(false);
        };
        const onError = () => toast.error("Gagal menyimpan meja.");

        if (editingTable) {
            patch(route("dine-tables.update", editingTable.id), { onSuccess, onError });
        } else {
            post(route("dine-tables.store"), { onSuccess, onError });
        }
    };

    const handleDelete = (table) => {
        if (!confirm(`Hapus meja "${table.name}"?`)) return;
        router.delete(route("dine-tables.destroy", table.id), {
            onSuccess: () => toast.success("Meja berhasil dihapus."),
            onError: () => toast.error("Gagal menghapus meja."),
        });
    };

    const handleFilterArea = (areaId) => {
        setFilterArea(areaId);
        router.get(
            route("dine-tables.index"),
            areaId ? { area_id: areaId } : {},
            { preserveScroll: true }
        );
    };

    const downloadQr = (table) => {
        window.open(route("dine-tables.qr", table.id), "_blank");
    };

    const tablesByArea = {};
    tables.forEach((t) => {
        const key = t.dine_area_id || "unassigned";
        if (!tablesByArea[key]) tablesByArea[key] = [];
        tablesByArea[key].push(t);
    });

    return (
        <>
            <Head title="Meja Dine-In" />

            <div className="mb-6">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                            Meja Dine-In
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            {tables.length} meja terdaftar
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            type={"button"}
                            label={activeView === "grid" ? "Daftar" : "Peta"}
                            onClick={() => setActiveView(activeView === "grid" ? "list" : "grid")}
                            className={
                                "border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                            }
                        />
                        {canCreate && (
                            <Button
                                type={"link"}
                                icon={<IconCirclePlus size={18} strokeWidth={1.5} />}
                                className={
                                    "bg-primary-500 hover:bg-primary-600 text-white shadow-lg shadow-primary-500/30"
                                }
                                label={"Tambah Meja"}
                                onClick={openCreate}
                            />
                        )}
                    </div>
                </div>
            </div>

            {canAccess && (
                <div className="mb-4 flex gap-2 flex-wrap">
                    <button
                        onClick={() => handleFilterArea("")}
                        className={`px-3 py-1.5 rounded-lg text-sm transition-colors ${
                            !filterArea
                                ? "bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-400"
                                : "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700"
                        }`}
                    >
                        Semua
                    </button>
                    {areas.map((area) => (
                        <button
                            key={area.id}
                            onClick={() => handleFilterArea(area.id)}
                            className={`px-3 py-1.5 rounded-lg text-sm transition-colors ${
                                filterArea == area.id
                                    ? "bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-400"
                                    : "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700"
                            }`}
                        >
                            {area.name}
                        </button>
                    ))}
                </div>
            )}

            {activeView === "grid" ? (
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 overflow-x-auto">
                    <svg
                        ref={svgRef}
                        viewBox={`0 0 ${gridWidth * GRID_SIZE} ${gridHeight * GRID_SIZE}`}
                        className="w-full min-h-[500px] bg-slate-50 dark:bg-slate-800 rounded-xl"
                        onDrop={handleDrop}
                        onDragOver={handleDragOver}
                    >
                        {Array.from({ length: gridWidth }, (_, col) =>
                            Array.from({ length: gridHeight }, (_, row) => (
                                <rect
                                    key={`${col}-${row}`}
                                    x={col * GRID_SIZE}
                                    y={row * GRID_SIZE}
                                    width={GRID_SIZE}
                                    height={GRID_SIZE}
                                    className="fill-transparent stroke-slate-200 dark:stroke-slate-700"
                                    strokeWidth={0.5}
                                />
                            ))
                        )}
                        {tables.map((table) => (
                            <TableShape
                                key={table.id}
                                table={table}
                                onDragStart={canUpdate ? handleDragStart : null}
                                isSelected={selectedTable?.id === table.id}
                                onClick={(t) => {
                                    setSelectedTable(t);
                                    openEdit(t);
                                }}
                            />
                        ))}
                    </svg>
                </div>
            ) : tables.length > 0 ? (
                <Table.Card title={"Daftar Meja"}>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Meja</Table.Th>
                                <Table.Th>Area</Table.Th>
                                <Table.Th>Kapasitas</Table.Th>
                                <Table.Th>Status</Table.Th>
                                <Table.Th></Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {tables.map((table) => (
                                <tr
                                    className="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    key={table.id}
                                >
                                    <Table.Td>
                                        <div className="flex items-center gap-2">
                                            <span className={`inline-block w-3 h-3 rounded-sm ${table.shape === "circle" ? "rounded-full" : ""} ${table.is_active ? "bg-primary-500" : "bg-slate-300 dark:bg-slate-600"}`} />
                                            <span className="text-sm font-medium text-slate-800 dark:text-slate-200">
                                                {table.name}
                                            </span>
                                        </div>
                                    </Table.Td>
                                    <Table.Td>
                                        <span className="text-sm text-slate-500 dark:text-slate-400">
                                            {table.area?.name ?? "-"}
                                        </span>
                                    </Table.Td>
                                    <Table.Td>
                                        <span className="text-sm text-slate-500 dark:text-slate-400">
                                            {table.capacity} org
                                        </span>
                                    </Table.Td>
                                    <Table.Td>
                                        <span
                                            className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                table.is_active
                                                    ? "bg-success-100 text-success-700 dark:bg-success-900/50 dark:text-success-400"
                                                    : "bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                            }`}
                                        >
                                            {table.is_active ? "Aktif" : "Nonaktif"}
                                        </span>
                                    </Table.Td>
                                    <Table.Td>
                                        <div className="flex gap-2 justify-end">
                                            {canAccess && (
                                                <Button
                                                    type={"button"}
                                                    icon={<IconDownload size={16} strokeWidth={1.5} />}
                                                    onClick={() => downloadQr(table)}
                                                    className={
                                                        "border bg-slate-100 border-slate-200 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400"
                                                    }
                                                />
                                            )}
                                            {canUpdate && (
                                                <Button
                                                    type={"edit"}
                                                    icon={<IconPencilCog size={16} strokeWidth={1.5} />}
                                                    className={
                                                        "border bg-warning-100 border-warning-200 text-warning-600 hover:bg-warning-200 dark:bg-warning-900/50 dark:border-warning-800 dark:text-warning-400"
                                                    }
                                                    onClick={() => openEdit(table)}
                                                />
                                            )}
                                            {canDelete && (
                                                <Button
                                                    type={"delete"}
                                                    icon={<IconTrash size={16} strokeWidth={1.5} />}
                                                    className={
                                                        "border bg-danger-100 border-danger-200 text-danger-600 hover:bg-danger-200 dark:bg-danger-900/50 dark:border-danger-800 dark:text-danger-400"
                                                    }
                                                    onClick={() => handleDelete(table)}
                                                />
                                            )}
                                        </div>
                                    </Table.Td>
                                </tr>
                            ))}
                        </Table.Tbody>
                    </Table>
                </Table.Card>
            ) : (
                <div className="flex flex-col items-center justify-center py-16 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    <div className="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                        <IconDatabaseOff size={32} className="text-slate-400" strokeWidth={1.5} />
                    </div>
                    <h3 className="text-lg font-medium text-slate-800 dark:text-slate-200 mb-1">
                        Belum Ada Meja
                    </h3>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
                        Tambahkan meja dine-in pertama Anda.
                    </p>
                    {canCreate && (
                        <Button
                            type={"link"}
                            icon={<IconCirclePlus size={18} />}
                            className={"bg-primary-500 hover:bg-primary-600 text-white"}
                            label={"Tambah Meja"}
                            onClick={openCreate}
                        />
                    )}
                </div>
            )}

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editingTable ? `Edit Meja: ${editingTable.name}` : "Tambah Meja"}
            >
                <form onSubmit={submit} className="space-y-4">
                    <Input
                        label="Nama Meja"
                        value={data.name}
                        onChange={(e) => setData("name", e.target.value)}
                        error={errors.name}
                        placeholder="Contoh: M1, Outdoor-1"
                        required
                    />
                    <Select
                        label="Area"
                        value={data.dine_area_id}
                        onChange={(e) => setData("dine_area_id", e.target.value)}
                        error={errors.dine_area_id}
                    >
                        <option value="">Tanpa Area</option>
                        {areas.map((area) => (
                            <option key={area.id} value={area.id}>
                                {area.name}
                            </option>
                        ))}
                    </Select>
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Kapasitas (org)"
                            type="number"
                            min="1"
                            value={data.capacity}
                            onChange={(e) => setData("capacity", parseInt(e.target.value) || 1)}
                            error={errors.capacity}
                        />
                        <Select
                            label="Bentuk"
                            value={data.shape}
                            onChange={(e) => setData("shape", e.target.value)}
                        >
                            <option value="square">Kotak</option>
                            <option value="circle">Bulat</option>
                        </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Posisi X"
                            type="number"
                            min="0"
                            max={gridWidth - 1}
                            value={data.pos_x}
                            onChange={(e) => setData("pos_x", parseInt(e.target.value) || 0)}
                            error={errors.pos_x}
                        />
                        <Input
                            label="Posisi Y"
                            type="number"
                            min="0"
                            max={gridHeight - 1}
                            value={data.pos_y}
                            onChange={(e) => setData("pos_y", parseInt(e.target.value) || 0)}
                            error={errors.pos_y}
                        />
                    </div>
                    <label className="flex items-center gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData("is_active", e.target.checked)}
                            className="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500"
                        />
                        <span className="text-sm text-slate-700 dark:text-slate-300">
                            Meja aktif
                        </span>
                    </label>
                    <div className="flex justify-end gap-3 pt-2">
                        <Button type={"button"} label={"Batal"} onClick={() => setModalOpen(false)} />
                        <Button
                            type={"submit"}
                            label={editingTable ? "Perbarui" : "Simpan"}
                            processing={processing}
                            className={"bg-primary-500 hover:bg-primary-600 text-white"}
                        />
                    </div>
                </form>
            </Modal>
        </>
    );
}

Index.layout = (page) => <DashboardLayout children={page} />;

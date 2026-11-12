import React, { useEffect, useMemo, useRef, useState } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, Link, router, useForm } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import Modal from "@/Components/Dashboard/Modal";
import Table from "@/Components/Dashboard/Table";
import {
    IconArrowLeft,
    IconCheck,
    IconClipboardCheck,
    IconDeviceFloppy,
    IconPackage,
    IconPlus,
    IconPrinter,
    IconSearch,
    IconTrash,
} from "@tabler/icons-react";
import toast from "react-hot-toast";
import { useAuthorization } from "@/Utils/authorization";

const formatDateTime = (value) =>
    value
        ? new Intl.DateTimeFormat("id-ID", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(value))
        : "-";

function SummaryCard({ label, value, tone = "default" }) {
    const toneClasses = {
        default:
            "border-slate-200 bg-white text-slate-900 dark:border-slate-800 dark:bg-slate-900 dark:text-white",
        success:
            "border-success-200 bg-success-50 text-success-700 dark:border-success-900 dark:bg-success-950/30 dark:text-success-400",
        warning:
            "border-warning-200 bg-warning-50 text-warning-700 dark:border-warning-900 dark:bg-warning-950/30 dark:text-warning-400",
    };

    return (
        <div className={`rounded-2xl border p-4 ${toneClasses[tone]}`}>
            <p className="text-xs font-medium uppercase tracking-wide opacity-80">
                {label}
            </p>
            <p className="mt-2 text-2xl font-bold">{value}</p>
        </div>
    );
}

export default function Show({
    stockOpname,
    availableProducts,
    productFilters,
}) {
    const { can } = useAuthorization();
    const canEditStockOpname = can("stock-opnames-create");
    const canFinalizeStockOpname = can("stock-opnames-finalize");
    const isDraft = stockOpname.status === "draft";
    const canManageDraft = isDraft && canEditStockOpname;
    const [localItems, setLocalItems] = useState(stockOpname.items);
    const [isFinalizing, setIsFinalizing] = useState(false);
    const [showProductModal, setShowProductModal] = useState(false);
    const [productSearchInput, setProductSearchInput] = useState(
        productFilters.search || ""
    );
    const localItemsRef = useRef(stockOpname.items);

    const notesForm = useForm({
        notes: stockOpname.notes || "",
    });

    useEffect(() => {
        setLocalItems(stockOpname.items);
        localItemsRef.current = stockOpname.items;
        notesForm.setData("notes", stockOpname.notes || "");
    }, [stockOpname.items, stockOpname.notes]);

    useEffect(() => {
        localItemsRef.current = localItems;
    }, [localItems]);

    useEffect(() => {
        setProductSearchInput(productFilters.search || "");
    }, [productFilters.search]);

    const filters = useMemo(
        () => ({
            product_search: productFilters.search || "",
        }),
        [productFilters]
    );
    const isWaitingSearch =
        showProductModal &&
        productSearchInput.trim() !== (filters.product_search || "").trim();

    const summary = useMemo(() => {
        const totalItems = localItems.length;
        const countedItems = localItems.filter(
            (item) => item.physical_stock !== null && item.physical_stock !== ""
        );
        const matchedItems = countedItems.filter(
            (item) => Number(item.difference || 0) === 0
        );
        const differentItems = countedItems.filter(
            (item) => Number(item.difference || 0) !== 0
        );
        const totalAdjustment = countedItems.reduce(
            (carry, item) => carry + Number(item.difference || 0),
            0
        );

        return {
            totalItems,
            matchedItems: matchedItems.length,
            differentItems: differentItems.length,
            totalAdjustment,
            hasMissingReasons: differentItems.some(
                (item) => !item.adjustment_reason
            ),
        };
    }, [localItems]);

    useEffect(() => {
        if (!showProductModal) {
            return;
        }

        const timeoutId = setTimeout(() => {
            if (productSearchInput === (filters.product_search || "")) {
                return;
            }

            updateFilter("product_search", productSearchInput);
        }, 1200);

        return () => clearTimeout(timeoutId);
    }, [productSearchInput, showProductModal, filters.product_search]);

    const updateFilter = (key, value) => {
        router.get(
            route("stock-opnames.show", stockOpname.id),
            {
                ...filters,
                [key]: value,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const saveNotes = (event) => {
        event.preventDefault();

        notesForm.patch(route("stock-opnames.update", stockOpname.id), {
            preserveScroll: true,
            onSuccess: () => toast.success("Catatan sesi diperbarui"),
            onError: () => toast.error("Gagal memperbarui catatan sesi"),
        });
    };

    const addProduct = (productId) => {
        router.post(
            route("stock-opnames.items.store", stockOpname.id),
            { product_id: productId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowProductModal(false);
                    toast.success("Produk ditambahkan ke sesi");
                },
                onError: () => toast.error("Gagal menambahkan produk"),
            }
        );
    };

    const setItemField = (itemId, key, value) => {
        setLocalItems((currentItems) =>
            currentItems.map((item) => {
                if (item.id !== itemId) {
                    return item;
                }

                const nextPhysicalStock =
                    key === "physical_stock"
                        ? value === ""
                            ? null
                            : Number(value)
                        : item.physical_stock;

                const nextDifference =
                    nextPhysicalStock === null
                        ? null
                        : nextPhysicalStock - Number(item.system_stock);

                return {
                    ...item,
                    [key]: value,
                    physical_stock: nextPhysicalStock,
                    difference: nextDifference,
                    adjustment_reason:
                        nextDifference === 0
                            ? ""
                            : key === "adjustment_reason"
                              ? value
                              : item.adjustment_reason,
                };
            })
        );
    };

    const saveItem = (item, { silent = true } = {}) =>
        new Promise((resolve) => {
            if (!canManageDraft || !item) {
                resolve(true);
                return;
            }

            router.patch(
                route("stock-opnames.items.update", [stockOpname.id, item.id]),
                {
                    physical_stock:
                        item.physical_stock === "" || item.physical_stock === null
                            ? null
                            : Number(item.physical_stock),
                    adjustment_reason: item.adjustment_reason || "",
                },
                {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => resolve(true),
                    onError: () => {
                        if (!silent) {
                            toast.error("Gagal menyimpan item opname");
                        }
                        resolve(false);
                    },
                }
            );
        });

    const persistItem = (itemId) => {
        const item = localItemsRef.current.find((i) => i.id === itemId);
        if (item) {
            saveItem(item, { silent: false });
        }
    };

    const deleteItem = (item) => {
        if (!canManageDraft) return;
        if (!confirm(`Hapus ${item.product?.title || "item"} dari sesi stock opname ini?`)) return;

        router.delete(route("stock-opnames.items.destroy", [stockOpname.id, item.id]), {
            preserveScroll: true,
            onSuccess: () => toast.success("Item dihapus dari sesi"),
            onError: () => toast.error("Gagal menghapus item"),
        });
    };

    const finalize = async () => {
        if (!canManageDraft || isFinalizing) return;

        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }

        setIsFinalizing(true);

        const results = await Promise.all(
            localItemsRef.current.map((item) => saveItem(item, { silent: true }))
        );

        if (results.some((ok) => !ok)) {
            setIsFinalizing(false);
            toast.error("Gagal menyimpan beberapa item. Periksa input lalu coba finalize lagi.");
            return;
        }

        router.post(
            route("stock-opnames.finalize", stockOpname.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Stock opname difinalisasi"),
                onError: () =>
                    toast.error("Gagal finalize. Periksa item yang belum valid."),
                onFinish: () => setIsFinalizing(false),
            }
        );
    };

    return (
        <>
            <Head title={stockOpname.code} />

            <div className="mb-6">
                <Link
                    href={route("stock-opnames.index")}
                    className="mb-3 inline-flex items-center gap-2 text-sm text-slate-500 hover:text-primary-600"
                >
                    <IconArrowLeft size={16} />
                    Kembali ke daftar stock opname
                </Link>

                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="mb-2 flex items-center gap-2">
                            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                                {stockOpname.code}
                            </h1>
                            <span
                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                                    isDraft
                                        ? "bg-warning-100 text-warning-700 dark:bg-warning-950/30 dark:text-warning-400"
                                        : "bg-success-100 text-success-700 dark:bg-success-950/30 dark:text-success-400"
                                }`}
                            >
                                {isDraft ? "Draft" : "Finalized"}
                            </span>
                        </div>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Dibuat oleh {stockOpname.creator?.name || "-"} •{" "}
                            {formatDateTime(stockOpname.created_at)}
                        </p>
                        {!isDraft && (
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Difinalisasi oleh {stockOpname.finalizer?.name || "-"} •{" "}
                                {formatDateTime(stockOpname.finalized_at)}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={route("stock-opnames.print", stockOpname.id)}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-primary-300 hover:text-primary-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-primary-700 dark:hover:text-primary-400"
                        >
                            <IconPrinter size={18} />
                            Cetak / Export Kartu
                        </a>
                        {isDraft && canFinalizeStockOpname && (
                            <Button
                                type="button"
                                icon={<IconCheck size={18} />}
                                className="bg-success-500 hover:bg-success-600 text-white shadow-lg shadow-success-500/20 disabled:opacity-50"
                                label={isFinalizing ? "Menyimpan..." : "Finalize Stock Opname"}
                                onClick={finalize}
                                disabled={
                                    isFinalizing ||
                                    localItems.length === 0 ||
                                    summary.hasMissingReasons
                                }
                            />
                        )}
                    </div>
                </div>
            </div>

            <div className="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <SummaryCard label="Total Item" value={summary.totalItems} />
                <SummaryCard
                    label="Item Sesuai"
                    value={summary.matchedItems}
                    tone="success"
                />
                <SummaryCard
                    label="Item Selisih"
                    value={summary.differentItems}
                    tone="warning"
                />
                <SummaryCard
                    label="Total Adjustment"
                    value={
                        summary.totalAdjustment > 0
                            ? `+${summary.totalAdjustment}`
                            : summary.totalAdjustment
                    }
                    tone={summary.totalAdjustment === 0 ? "default" : "warning"}
                />
            </div>

            <div className="grid gap-6 xl:grid-cols-[1.7fr_1fr]">
                <div className="space-y-6">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                                Item Stock Opname
                            </h2>
                            {canManageDraft && (
                                <Button
                                    type="button"
                                    icon={<IconPlus size={18} />}
                                    className="bg-primary-500 hover:bg-primary-600 text-white"
                                    label="Tambah Produk"
                                    onClick={() => setShowProductModal(true)}
                                />
                            )}
                        </div>

                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th>Produk</Table.Th>
                                    <Table.Th>Stok Sistem</Table.Th>
                                    <Table.Th>Stok Fisik</Table.Th>
                                    <Table.Th>Selisih</Table.Th>
                                    <Table.Th>Alasan</Table.Th>
                                    <Table.Th className="w-20 text-center">Aksi</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {localItems.length > 0 ? (
                                    localItems.map((item) => {
                                        const difference = Number(item.difference || 0);
                                        const isDifferent =
                                            item.physical_stock !== null && difference !== 0;

                                        return (
                                            <tr
                                                key={item.id}
                                                className="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                            >
                                                <Table.Td>
                                                    <div>
                                                        <p className="font-medium text-slate-800 dark:text-slate-200">
                                                            {item.product.title}
                                                        </p>
                                                        <p className="text-xs text-slate-500 dark:text-slate-400">
                                                            {item.product.category?.name || "-"} •{" "}
                                                            {item.product.barcode ||
                                                                item.product.sku ||
                                                                "-"}
                                                        </p>
                                                    </div>
                                                </Table.Td>
                                                <Table.Td>{item.system_stock}</Table.Td>
                                                <Table.Td>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        value={item.physical_stock ?? ""}
                                                        disabled={!canManageDraft}
                                                        onChange={(event) =>
                                                            setItemField(
                                                                item.id,
                                                                "physical_stock",
                                                                event.target.value
                                                            )
                                                        }
                                                        onBlur={() => persistItem(item.id)}
                                                        className="h-10 w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                                    />
                                                </Table.Td>
                                                <Table.Td>
                                                    <span
                                                        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                            item.physical_stock === null
                                                                ? "bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                                                : difference === 0
                                                                  ? "bg-success-100 text-success-700 dark:bg-success-950/30 dark:text-success-400"
                                                                  : "bg-warning-100 text-warning-700 dark:bg-warning-950/30 dark:text-warning-400"
                                                        }`}
                                                    >
                                                        {item.physical_stock === null
                                                            ? "Belum dihitung"
                                                            : difference > 0
                                                              ? `+${difference}`
                                                              : difference}
                                                    </span>
                                                </Table.Td>
                                                <Table.Td>
                                                    <input
                                                        type="text"
                                                        value={item.adjustment_reason || ""}
                                                        disabled={!canManageDraft}
                                                        onChange={(event) =>
                                                            setItemField(
                                                                item.id,
                                                                "adjustment_reason",
                                                                event.target.value
                                                            )
                                                        }
                                                        onBlur={() => persistItem(item.id)}
                                                        placeholder={
                                                            isDifferent
                                                                ? "Wajib isi alasan"
                                                                : "Tidak perlu"
                                                        }
                                                        className="h-10 w-full min-w-48 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                                    />
                                                </Table.Td>
                                                <Table.Td className="text-center">
                                                    {canManageDraft ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => deleteItem(item)}
                                                            title="Hapus item"
                                                            className="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-2 text-slate-600 transition hover:border-rose-300 hover:text-rose-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-rose-700 dark:hover:text-rose-400"
                                                        >
                                                            <IconTrash size={18} />
                                                        </button>
                                                    ) : (
                                                        "-"
                                                    )}
                                                </Table.Td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <Table.Empty
                                        colSpan={6}
                                        message={
                                            <div className="text-slate-500 dark:text-slate-400">
                                                Belum ada produk pada sesi ini.
                                            </div>
                                        }
                                    >
                                        <div className="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                                            <IconPackage size={28} className="text-slate-400" />
                                        </div>
                                    </Table.Empty>
                                )}
                            </Table.Tbody>
                        </Table>
                    </div>
                </div>

                <div className="space-y-6">
                    <form
                        onSubmit={saveNotes}
                        className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
                            Catatan Sesi
                        </h2>
                        <textarea
                            value={notesForm.data.notes}
                            disabled={!canManageDraft}
                            onChange={(event) =>
                                notesForm.setData("notes", event.target.value)
                            }
                            rows={4}
                            className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            placeholder="Catatan sesi stock opname"
                        />
                        {canManageDraft && (
                            <div className="mt-4 flex justify-end">
                                <Button
                                    type="submit"
                                    icon={<IconDeviceFloppy size={18} />}
                                    className="bg-primary-500 hover:bg-primary-600 text-white"
                                    label="Simpan Catatan"
                                />
                            </div>
                        )}
                    </form>

                    <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
                            Informasi Sesi
                        </h2>
                        <div className="space-y-3 text-sm text-slate-500 dark:text-slate-400">
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                <p className="font-medium text-slate-700 dark:text-slate-200">
                                    Cara penggunaan
                                </p>
                                <ul className="mt-2 space-y-2">
                                    <li>1. Tambahkan produk ke sesi stock opname.</li>
                                    <li>2. Input stok fisik hasil hitung lapangan.</li>
                                    <li>3. Isi alasan jika terdapat selisih stok.</li>
                                    <li>4. Finalize setelah semua item valid.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Modal
                show={showProductModal && canManageDraft}
                onClose={() => setShowProductModal(false)}
                title={
                    <div className="flex items-center gap-2">
                        <IconClipboardCheck size={18} />
                        Cari Produk untuk Stock Opname
                    </div>
                }
                maxWidth="2xl"
            >
                <div className="space-y-4">
                    <div className="relative">
                        <input
                            type="text"
                            autoFocus
                            value={productSearchInput}
                            onChange={(event) =>
                                setProductSearchInput(event.target.value)
                            }
                            placeholder="Cari nama produk, barcode, atau SKU..."
                            className="h-12 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 pr-11 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        />
                        <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400">
                            <IconSearch size={18} />
                        </div>
                    </div>

                    {isWaitingSearch ? (
                        <div className="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            Menunggu input selesai, pencarian akan dijalankan dalam 1-2 detik.
                        </div>
                    ) : availableProducts.length > 0 ? (
                        <div className="max-h-[420px] space-y-3 overflow-y-auto pr-1 dashboard-scrollbar">
                            {availableProducts.map((product) => (
                                <button
                                    key={product.id}
                                    type="button"
                                    onClick={() => addProduct(product.id)}
                                    className="flex w-full items-start justify-between gap-3 rounded-xl border border-slate-200 p-4 text-left transition hover:border-primary-300 hover:bg-primary-50/50 dark:border-slate-700 dark:hover:border-primary-700 dark:hover:bg-primary-950/20"
                                >
                                    <div>
                                        <p className="font-medium text-slate-800 dark:text-slate-200">
                                            {product.title}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            {product.category?.name || "-"} • {product.barcode || product.sku || "-"}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            Stok sistem: {product.warehouse_stock ?? product.stock ?? 0}
                                        </p>
                                    </div>
                                    <span className="inline-flex rounded-lg bg-primary-500 px-3 py-2 text-xs font-semibold text-white">
                                        Tambah
                                    </span>
                                </button>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            {filters.product_search
                                ? "Tidak ada produk yang cocok dengan kata kunci pencarian."
                                : "Semua produk sudah ditambahkan atau belum ada produk tersedia."}
                        </div>
                    )}
                </div>
            </Modal>
        </>
    );
}

Show.layout = (page) => <DashboardLayout children={page} />;

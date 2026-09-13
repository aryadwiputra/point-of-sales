import React, { useEffect, useMemo } from "react";
import { Head, Link, router, useForm } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import Button from "@/Components/Dashboard/Button";
import Table from "@/Components/Dashboard/Table";
import { IconArrowLeft, IconCheck, IconDeviceFloppy } from "@tabler/icons-react";
import toast from "react-hot-toast";

const formatCurrency = (value = 0) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(value);

const formatDateTime = (value) =>
    value
        ? new Intl.DateTimeFormat("id-ID", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(value))
        : "-";

export default function SalesReturnForm({
    title,
    transaction,
    salesReturn = null,
    submitRoute,
    submitMethod = "post",
    canEdit = true,
    canComplete = false,
    completeRoute = null,
}) {
    const itemDefaults = useMemo(
        () =>
            transaction.details.map((detail) => ({
                transaction_detail_id: detail.id,
                qty_return: detail.draft_item?.qty_return ?? 0,
                return_reason: detail.draft_item?.return_reason ?? "",
                restock_to_inventory:
                    detail.draft_item?.restock_to_inventory ?? true,
            })),
        [transaction.details]
    );

    const form = useForm({
        return_type:
            salesReturn?.return_type && transaction.customer
                ? salesReturn.return_type
                : "refund_cash",
        notes: salesReturn?.notes ?? "",
        items: itemDefaults,
    });

    useEffect(() => {
        form.setData({
            return_type:
                salesReturn?.return_type && transaction.customer
                    ? salesReturn.return_type
                    : "refund_cash",
            notes: salesReturn?.notes ?? "",
            items: itemDefaults,
        });
    }, [salesReturn, itemDefaults]);

    const itemStates = useMemo(() => {
        const itemMap = new Map(
            form.data.items.map((item) => [item.transaction_detail_id, item])
        );

        return transaction.details.map((detail) => {
            const current = itemMap.get(detail.id) ?? {
                qty_return: 0,
                return_reason: "",
                restock_to_inventory: true,
            };
            const qtyReturn = Number(current.qty_return || 0);
            const subtotal = qtyReturn * Number(detail.price || 0);

            return {
                ...detail,
                qty_return: qtyReturn,
                return_reason: current.return_reason || "",
                restock_to_inventory: Boolean(current.restock_to_inventory),
                subtotal,
            };
        });
    }, [form.data.items, transaction.details]);

    const summary = useMemo(() => {
        const selectedItems = itemStates.filter((item) => item.qty_return > 0);
        const totalItems = selectedItems.reduce(
            (carry, item) => carry + item.qty_return,
            0
        );
        const totalAmount = selectedItems.reduce(
            (carry, item) => carry + item.subtotal,
            0
        );
        const restockQty = selectedItems.reduce(
            (carry, item) =>
                carry + (item.restock_to_inventory ? item.qty_return : 0),
            0
        );

        let receivableAfter = null;
        let settlementAmount = 0;

        if (
            transaction.payment_method === "pay_later" &&
            transaction.receivable
        ) {
            receivableAfter = Math.max(
                0,
                Number(transaction.receivable.total || 0) - totalAmount
            );
            settlementAmount = Math.max(
                0,
                Number(transaction.receivable.paid || 0) - receivableAfter
            );
        } else if (transaction.payment_status === "paid") {
            settlementAmount = totalAmount;
        }

        const effectiveReturnType =
            !transaction.customer && form.data.return_type === "store_credit"
                ? "refund_cash"
                : form.data.return_type;

        return {
            selectedItemsCount: selectedItems.length,
            totalItems,
            totalAmount,
            restockQty,
            receivableAfter,
            refundAmount:
                effectiveReturnType === "refund_cash" ? settlementAmount : 0,
            creditedAmount:
                effectiveReturnType === "store_credit" ? settlementAmount : 0,
            hasSelectedItems: selectedItems.length > 0,
        };
    }, [
        itemStates,
        form.data.return_type,
        transaction.customer,
        transaction.payment_method,
        transaction.payment_status,
        transaction.receivable,
    ]);

    const updateItem = (transactionDetailId, key, value) => {
        form.setData(
            "items",
            form.data.items.map((item) =>
                item.transaction_detail_id === transactionDetailId
                    ? { ...item, [key]: value }
                    : item
            )
        );
    };

    const submit = (event) => {
        event.preventDefault();

        form[submitMethod](submitRoute, {
            preserveScroll: true,
            onSuccess: () =>
                toast.success(
                    salesReturn ? "Draft retur diperbarui" : "Draft retur dibuat"
                ),
            onError: () => toast.error("Gagal menyimpan draft retur"),
        });
    };

    const complete = () => {
        router.post(
            completeRoute,
            {},
            {
                preserveScroll: true,
                onSuccess: () => toast.success("Retur penjualan diselesaikan"),
                onError: () => toast.error("Gagal menyelesaikan retur"),
            }
        );
    };

    return (
        <>
            <Head title={title} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <Link
                            href={
                                salesReturn
                                    ? route("sales-returns.index")
                                    : route("transactions.history")
                            }
                            className="mb-3 inline-flex items-center gap-2 text-sm text-slate-500 hover:text-primary-600"
                        >
                            <IconArrowLeft size={16} />
                            {salesReturn
                                ? "Kembali ke daftar retur"
                                : "Kembali ke riwayat transaksi"}
                        </Link>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                            {title}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Invoice {transaction.invoice} •{" "}
                            {formatDateTime(transaction.created_at)}
                        </p>
                    </div>

                    {salesReturn && (
                        <div className="flex items-center gap-2">
                            <span
                                className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
                                    salesReturn.status === "completed"
                                        ? "bg-success-100 text-success-700 dark:bg-success-950/30 dark:text-success-400"
                                        : "bg-warning-100 text-warning-700 dark:bg-warning-950/30 dark:text-warning-400"
                                }`}
                            >
                                {salesReturn.status === "completed"
                                    ? "Completed"
                                    : "Draft"}
                            </span>
                            {salesReturn.completed_at && (
                                <span className="text-xs text-slate-500 dark:text-slate-400">
                                    {formatDateTime(salesReturn.completed_at)}
                                </span>
                            )}
                        </div>
                    )}
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <InfoCard
                        label="Pelanggan"
                        value={transaction.customer?.name || "Umum"}
                    />
                    <InfoCard
                        label="Metode Bayar"
                        value={transaction.payment_method
                            ?.replaceAll("_", " ")
                            .toUpperCase()}
                    />
                    <InfoCard
                        label="Total Transaksi"
                        value={formatCurrency(transaction.grand_total)}
                    />
                    <InfoCard
                        label="Nominal Retur"
                        value={formatCurrency(summary.totalAmount)}
                    />
                </div>

                <form
                    onSubmit={submit}
                    className="grid gap-6 xl:grid-cols-[1.7fr_1fr]"
                >
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                                Item Retur
                            </h2>
                            {canEdit && (
                                <Button
                                    type="submit"
                                    icon={<IconDeviceFloppy size={18} />}
                                    className="bg-primary-500 text-white hover:bg-primary-600"
                                    label={salesReturn ? "Simpan Draft" : "Buat Draft"}
                                    disabled={form.processing}
                                />
                            )}
                        </div>

                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th>Produk</Table.Th>
                                    <Table.Th>Qty Beli</Table.Th>
                                    <Table.Th>Sudah Retur</Table.Th>
                                    <Table.Th>Sisa</Table.Th>
                                    <Table.Th>Qty Retur</Table.Th>
                                    <Table.Th>Alasan</Table.Th>
                                    <Table.Th>Restock</Table.Th>
                                    <Table.Th>Subtotal</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {itemStates.map((item) => (
                                    <tr key={item.id}>
                                        <Table.Td>
                                            <div>
                                                <p className="font-medium text-slate-800 dark:text-slate-100">
                                                    {item.product?.title || "-"}
                                                </p>
                                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                                    {item.product?.barcode ||
                                                        item.product?.sku ||
                                                        "-"}
                                                </p>
                                            </div>
                                        </Table.Td>
                                        <Table.Td>{item.qty}</Table.Td>
                                        <Table.Td>
                                            {item.returned_completed_qty}
                                        </Table.Td>
                                        <Table.Td>{item.remaining_returnable_qty}</Table.Td>
                                        <Table.Td>
                                            <input
                                                type="number"
                                                min="0"
                                                max={item.remaining_returnable_qty}
                                                value={item.qty_return}
                                                disabled={!canEdit}
                                                onChange={(event) =>
                                                    updateItem(
                                                        item.id,
                                                        "qty_return",
                                                        event.target.value
                                                    )
                                                }
                                                className="h-10 w-24 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                            />
                                        </Table.Td>
                                        <Table.Td>
                                            <input
                                                type="text"
                                                value={item.return_reason}
                                                disabled={!canEdit}
                                                onChange={(event) =>
                                                    updateItem(
                                                        item.id,
                                                        "return_reason",
                                                        event.target.value
                                                    )
                                                }
                                                placeholder="Alasan retur"
                                                className="h-10 min-w-48 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                            />
                                        </Table.Td>
                                        <Table.Td>
                                            <input
                                                type="checkbox"
                                                checked={item.restock_to_inventory}
                                                disabled={!canEdit}
                                                onChange={(event) =>
                                                    updateItem(
                                                        item.id,
                                                        "restock_to_inventory",
                                                        event.target.checked
                                                    )
                                                }
                                                className="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                            />
                                        </Table.Td>
                                        <Table.Td>
                                            {formatCurrency(item.subtotal)}
                                        </Table.Td>
                                    </tr>
                                ))}
                            </Table.Tbody>
                        </Table>

                        {form.errors.items && (
                            <p className="mt-3 text-sm text-danger-600">
                                {form.errors.items}
                            </p>
                        )}
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
                                Penyelesaian Retur
                            </h2>

                            <div className="space-y-4">
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Metode Penyelesaian
                                    </label>
                                    <select
                                        value={form.data.return_type}
                                        disabled={!canEdit || !transaction.customer}
                                        onChange={(event) =>
                                            form.setData(
                                                "return_type",
                                                event.target.value
                                            )
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        <option value="refund_cash">
                                            Refund Tunai
                                        </option>
                                        {transaction.customer && (
                                            <option value="store_credit">
                                                Saldo Toko / Credit
                                            </option>
                                        )}
                                    </select>
                                    {!transaction.customer && (
                                        <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                            Transaksi tanpa pelanggan hanya
                                            dapat memakai refund tunai.
                                        </p>
                                    )}
                                    {transaction.payment_method === "split" && (
                                        <p className="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                            Transaksi split hanya dapat direfund
                                            sebagai refund tunai pada versi ini.
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Catatan
                                    </label>
                                    <textarea
                                        rows={4}
                                        value={form.data.notes}
                                        disabled={!canEdit}
                                        onChange={(event) =>
                                            form.setData(
                                                "notes",
                                                event.target.value
                                            )
                                        }
                                        className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                        placeholder="Catatan retur"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">
                                Preview Dampak
                            </h2>

                            <div className="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                                <PreviewRow
                                    label="Item dipilih"
                                    value={`${summary.selectedItemsCount} produk`}
                                />
                                <PreviewRow
                                    label="Total qty retur"
                                    value={`${summary.totalItems} item`}
                                />
                                <PreviewRow
                                    label="Stok kembali"
                                    value={`${summary.restockQty} item`}
                                />
                                <PreviewRow
                                    label="Refund"
                                    value={formatCurrency(summary.refundAmount)}
                                />
                                <PreviewRow
                                    label="Saldo toko"
                                    value={formatCurrency(
                                        summary.creditedAmount
                                    )}
                                />
                                {transaction.receivable && (
                                    <>
                                        <PreviewRow
                                            label="Piutang saat ini"
                                            value={formatCurrency(
                                                transaction.receivable.total
                                            )}
                                        />
                                        <PreviewRow
                                            label="Piutang setelah retur"
                                            value={formatCurrency(
                                                summary.receivableAfter ?? 0
                                            )}
                                        />
                                    </>
                                )}
                                <PreviewRow
                                    label="Nominal retur"
                                    value={formatCurrency(summary.totalAmount)}
                                    strong
                                />
                            </div>

                            {canComplete && (
                                <div className="mt-5">
                                    <Button
                                        type="button"
                                        icon={<IconCheck size={18} />}
                                        className="w-full bg-success-500 text-white hover:bg-success-600 disabled:opacity-50"
                                        label="Selesaikan Retur"
                                        onClick={complete}
                                        disabled={
                                            !summary.hasSelectedItems ||
                                            form.processing ||
                                            form.isDirty
                                        }
                                    />
                                    {form.isDirty && (
                                        <p className="mt-2 text-xs text-warning-600">
                                            Simpan draft terlebih dulu sebelum
                                            menyelesaikan retur.
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

function InfoCard({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p className="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </p>
            <p className="mt-2 text-lg font-semibold text-slate-900 dark:text-white">
                {value}
            </p>
        </div>
    );
}

function PreviewRow({ label, value, strong = false }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-slate-500 dark:text-slate-400">{label}</span>
            <span
                className={
                    strong
                        ? "font-semibold text-slate-900 dark:text-white"
                        : "font-medium text-slate-800 dark:text-slate-200"
                }
            >
                {value}
            </span>
        </div>
    );
}

SalesReturnForm.layout = (page) => <DashboardLayout children={page} />;

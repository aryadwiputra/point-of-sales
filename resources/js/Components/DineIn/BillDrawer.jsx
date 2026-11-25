import React, { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import {
    IconX,
    IconTrash,
    IconArrowsSplit,
    IconCash,
    IconPlus,
} from "@tabler/icons-react";
import SplitBillModal from "@/Components/DineIn/SplitBillModal";
import MergeTableModal from "@/Components/DineIn/MergeTableModal";
import MoveTableModal from "@/Components/DineIn/MoveTableModal";
import SettleBillModal from "@/Components/DineIn/SettleBillModal";
import toast from "react-hot-toast";

const formatPrice = (value = 0) =>
    Number(value || 0).toLocaleString("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    });

const durationFor = (openedAt) => {
    if (!openedAt) return "-";
    const minutes = Math.max(0, Math.floor((Date.now() - new Date(openedAt).getTime()) / 60000));
    return `${Math.floor(minutes / 60)}j ${minutes % 60}m`;
};

export default function BillDrawer({
    session,
    tables = [],
    sessions = [],
    customers = [],
    paymentGateways = [],
    defaultPaymentGateway = "cash",
    bankAccounts = [],
    selectedBillId,
    onSelectBill,
    onClose,
}) {
    const [splitBill, setSplitBill] = useState(null);
    const [mergeOpen, setMergeOpen] = useState(false);
    const [moveOpen, setMoveOpen] = useState(false);
    const [settleOpen, setSettleOpen] = useState(false);
    const bills = session?.bills ?? [];
    const activeBills = bills.filter((bill) => ["open", "pending_approval"].includes(bill.status));
    const selectedBill = bills.find((bill) => bill.id === selectedBillId) ?? activeBills[0] ?? bills[0];

    useEffect(() => {
        if (!selectedBill && activeBills.length > 0) {
            onSelectBill(activeBills[0].id);
        }
    }, [selectedBill, activeBills, onSelectBill]);

    if (!session) return null;

    const voidItem = (item) => {
        const reason = prompt(`Alasan void ${item.product?.title ?? "item"}?`);
        if (reason === null) return;

        router.delete(route("dine-bills.void-item", [selectedBill.id, item.id]), {
            data: { reason },
            preserveScroll: true,
            onSuccess: () => toast.success("Item di-void."),
            onError: () => toast.error("Gagal void item."),
        });
    };

    return (
        <div className="fixed inset-0 z-40 flex justify-end">
            <div className="absolute inset-0 bg-slate-900/50" onClick={onClose} />
            <aside className="relative flex h-full w-full max-w-xl flex-col bg-white shadow-2xl dark:bg-slate-900">
                <header className="flex items-start justify-between border-b border-slate-200 p-4 dark:border-slate-800">
                    <div>
                        <p className="text-xs uppercase tracking-wide text-slate-400">Meja</p>
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                            {session.table?.name ?? "Meja"}
                        </h2>
                        <p className="text-sm text-slate-500">
                            {session.guest_count ?? "-"} tamu · {durationFor(session.opened_at)}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                        aria-label="Tutup"
                    >
                        <IconX size={20} />
                    </button>
                </header>

                <div className="border-b border-slate-200 p-4 dark:border-slate-800">
                    <p className="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Bill</p>
                    <div className="space-y-2">
                        {bills.map((bill) => (
                            <button
                                key={bill.id}
                                type="button"
                                onClick={() => onSelectBill(bill.id)}
                                className={`flex w-full items-center justify-between rounded-xl border p-3 text-left ${
                                    selectedBill?.id === bill.id
                                        ? "border-primary-500 bg-primary-50 dark:bg-primary-950/30"
                                        : "border-slate-200 dark:border-slate-700"
                                }`}
                            >
                                <span className="text-sm font-medium text-slate-800 dark:text-slate-200">
                                    Bill {bill.sequence} · {bill.status} · {(bill.items ?? []).length} item
                                </span>
                                <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                    {formatPrice(bill.grand_total)}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto p-4">
                    {!selectedBill ? (
                        <p className="text-sm text-slate-500">Belum ada bill.</p>
                    ) : (
                        <div className="space-y-2">
                            {(selectedBill.items ?? []).map((item) => (
                                <div
                                    key={item.id}
                                    className={`rounded-xl border p-3 ${
                                        item.voided_at
                                            ? "border-slate-200 opacity-60 dark:border-slate-700"
                                            : "border-slate-200 dark:border-slate-700"
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-medium text-slate-800 dark:text-slate-200">
                                                {item.product?.title ?? "Produk"}
                                                {item.voided_at && (
                                                    <span className="ml-2 text-xs font-normal text-danger-500">VOID</span>
                                                )}
                                            </p>
                                            {(item.modifiers ?? []).map((modifier, index) => (
                                                <p
                                                    key={`${modifier.id ?? modifier.name}-${index}`}
                                                    className="text-[11px] text-slate-500"
                                                >
                                                    + {modifier.group_name ? `${modifier.group_name}: ` : ""}
                                                    {modifier.name}
                                                </p>
                                            ))}
                                            {item.note && (
                                                <p className="text-[11px] italic text-slate-500">Catatan: {item.note}</p>
                                            )}
                                            <p className="text-xs text-slate-500">
                                                {item.qty} × {formatPrice(item.price)}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                                {formatPrice(Number(item.price) * Number(item.qty))}
                                            </p>
                                            {!item.voided_at && selectedBill.status === "open" && (
                                                <button
                                                    type="button"
                                                    onClick={() => voidItem(item)}
                                                    className="mt-2 inline-flex items-center gap-1 text-xs font-medium text-danger-600"
                                                >
                                                    <IconTrash size={14} /> Void
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {selectedBill && (
                    <footer className="grid grid-cols-2 gap-2 border-t border-slate-200 p-4 dark:border-slate-800 sm:grid-cols-5">
                        <button
                            type="button"
                            onClick={() => router.visit(route("transactions.index"))}
                            className="flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-100 text-sm font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <IconPlus size={16} /> Tambah Item
                        </button>
                        <button
                            type="button"
                            onClick={() => setMoveOpen(true)}
                            className="flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-100 text-sm font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            Pindah
                        </button>
                        <button
                            type="button"
                            onClick={() => setSplitBill(selectedBill)}
                            disabled={selectedBill.status !== "open"}
                            className="flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-100 text-sm font-medium text-slate-700 disabled:opacity-50 dark:bg-slate-800 dark:text-slate-200"
                        >
                            <IconArrowsSplit size={16} /> Split
                        </button>
                        <button
                            type="button"
                            onClick={() => setMergeOpen(true)}
                            className="flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-100 text-sm font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            Gabung
                        </button>
                        <button
                            type="button"
                            onClick={() => setSettleOpen(true)}
                            className="flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary-500 text-sm font-medium text-white"
                        >
                            <IconCash size={16} /> Bayar
                        </button>
                    </footer>
                )}
            </aside>

            <SplitBillModal
                show={Boolean(splitBill)}
                bill={splitBill}
                onClose={() => setSplitBill(null)}
            />

            <MergeTableModal
                show={mergeOpen}
                session={session}
                sessions={sessions}
                onClose={() => setMergeOpen(false)}
            />
            <MoveTableModal
                show={moveOpen}
                session={session}
                tables={tables}
                onClose={() => setMoveOpen(false)}
            />
            <SettleBillModal
                show={settleOpen}
                bill={selectedBill}
                customers={customers}
                paymentGateways={paymentGateways}
                bankAccounts={bankAccounts}
                defaultPaymentGateway={defaultPaymentGateway}
                onClose={() => setSettleOpen(false)}
            />
        </div>
    );
}
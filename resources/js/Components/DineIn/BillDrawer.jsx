import React from "react";
import { IconX } from "@tabler/icons-react";

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

export default function BillDrawer({ session, selectedBillId, onSelectBill, onClose }) {
    if (!session) return null;

    const bills = session.bills ?? [];
    const activeBills = bills.filter((bill) => ["open", "pending_approval"].includes(bill.status));
    const selectedBill = bills.find((bill) => bill.id === selectedBillId) ?? activeBills[0] ?? bills[0];

    return (
        <div className="fixed inset-0 z-40 flex justify-end">
            <div className="absolute inset-0 bg-slate-900/50" onClick={onClose} />
            <aside className="relative flex h-full w-full max-w-xl flex-col bg-white shadow-2xl dark:bg-slate-900 sm:h-full">
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
                    <p className="text-sm text-slate-500">Pilih bill untuk melihat item.</p>
                </div>
            </aside>
        </div>
    );
}
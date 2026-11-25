import React, { useMemo } from "react";
import { IconArmchair, IconX } from "@tabler/icons-react";

const formatPrice = (value = 0) =>
    Number(value || 0).toLocaleString("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    });

export default function TableSelector({
    tables = [],
    sessions = [],
    selectedTable,
    selectedBillId,
    onSelectTable,
    onSelectBill,
    onClear,
}) {
    const openSessions = useMemo(
        () => Object.fromEntries((sessions ?? []).map((session) => [session.dine_table_id, session])),
        [sessions]
    );
    const selectedSession = selectedTable ? openSessions[selectedTable.id] : null;
    const openBills = (selectedSession?.bills ?? []).filter((bill) => bill.status === "open");

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-2">
                <label className="text-sm font-medium text-slate-700 dark:text-slate-300">
                    Mode Meja (Dine-In)
                </label>
                {selectedTable && (
                    <button
                        type="button"
                        onClick={onClear}
                        className="inline-flex items-center gap-1 text-xs font-medium text-slate-500"
                    >
                        <IconX size={14} /> Lepas meja
                    </button>
                )}
            </div>

            <select
                value={selectedTable?.id ?? ""}
                onChange={(event) => {
                    const table = tables.find((candidate) => String(candidate.id) === event.target.value);
                    onSelectTable(table ?? null);
                }}
                className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
            >
                <option value="">Transaksi biasa (tanpa meja)</option>
                {tables.map((table) => {
                    const session = openSessions[table.id];
                    return (
                        <option key={table.id} value={table.id}>
                            {table.name} · {session ? "Terisi" : "Tersedia"}
                        </option>
                    );
                })}
            </select>

            {selectedTable && (
                <div className="flex items-center gap-2 rounded-xl bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:bg-primary-950/30 dark:text-primary-300">
                    <IconArmchair size={16} />
                    <span className="font-medium">{selectedTable.name}</span>
                    {selectedSession && (
                        <span className="text-xs text-primary-600 dark:text-primary-400">
                            {selectedSession.bills?.length ?? 0} bill
                        </span>
                    )}
                </div>
            )}

            {selectedTable && (
                <div>
                    <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                        Kirim ke Bill
                    </label>
                    <select
                        value={selectedBillId ?? ""}
                        onChange={(event) => onSelectBill(Number(event.target.value) || null)}
                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="">Pilih bill</option>
                        {openBills.map((bill) => (
                            <option key={bill.id} value={bill.id}>
                                Bill {bill.sequence} · {formatPrice(bill.grand_total)}
                            </option>
                        ))}
                    </select>
                    {openBills.length === 0 && (
                        <p className="mt-1 text-xs text-warning-600">Meja belum memiliki bill terbuka.</p>
                    )}
                </div>
            )}
        </div>
    );
}
import React from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { IconCheck, IconX, IconAlertCircle, IconEye } from "@tabler/icons-react";
import toast from "react-hot-toast";

const formatCurrency = (v = 0) => Number(v || 0).toLocaleString("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 });

export default function DiscountApprovals({ pendingTransactions }) {
    const confirm = (action, tx) => {
        if (!window.confirm(`${action === "approve" ? "Setujui" : "Tolak"} diskon transaksi ${tx.invoice}?`)) return;
        router.post(route(`discount-approvals.${action}`, tx.id), {}, {
            onSuccess: () => toast.success(action === "approve" ? "Diskon disetujui" : "Diskon ditolak"),
            onError: () => toast.error("Gagal"),
        });
    };

    return (
        <>
            <Head title="Approval Diskon" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <IconAlertCircle size={28} className="text-amber-500" />
                        Approval Diskon
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        {pendingTransactions.length} transaksi menunggu persetujuan diskon
                    </p>
                </div>

                {pendingTransactions.length > 0 ? (
                    <div className="space-y-4">
                        {pendingTransactions.map((tx) => (
                            <div key={tx.id} className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="font-semibold text-slate-800 dark:text-white">{tx.invoice}</p>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">Kasir: {tx.cashier}</p>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">Pelanggan: {tx.customer}</p>
                                    </div>
                                    <Link href={route("transactions.print", tx.invoice)} className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800">
                                        <IconEye size={18} />
                                    </Link>
                                </div>
                                <div className="mt-3 flex items-center gap-4 text-sm">
                                    <span className="text-slate-500">Diskon: <strong className="text-danger-500">{formatCurrency(tx.discount)}</strong></span>
                                    <span className="text-slate-500">Total: <strong>{formatCurrency(tx.grand_total)}</strong></span>
                                </div>
                                <div className="mt-4 flex gap-3">
                                    <button onClick={() => confirm("approve", tx)} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-success-500 hover:bg-success-600 text-white text-sm font-medium transition-colors">
                                        <IconCheck size={18} /> Setujui
                                    </button>
                                    <button onClick={() => confirm("deny", tx)} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-danger-200 text-danger-600 hover:bg-danger-50 text-sm font-medium transition-colors dark:border-danger-800 dark:text-danger-400">
                                        <IconX size={18} /> Tolak
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-8 text-center">
                        <IconCheck size={48} className="mx-auto text-success-400 mb-3" />
                        <p className="text-slate-500 dark:text-slate-400">Semua transaksi sudah diverifikasi.</p>
                    </div>
                )}
            </div>
        </>
    );
}

DiscountApprovals.layout = (page) => <DashboardLayout children={page} />;

import React from "react";
import { Head, Link, router } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { IconArrowLeft, IconArrowsLeftRight, IconSend, IconCheck, IconX } from "@tabler/icons-react";
import { useAuthorization } from "@/Utils/authorization";

const formatDateTime = (value) => {
    if (!value) return "-";
    return new Intl.DateTimeFormat("id-ID", { dateStyle: "full", timeStyle: "short" }).format(new Date(value));
};

const statusBadge = (status) => {
    const styles = {
        draft: "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300",
        in_transit: "bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400",
        completed: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400",
        cancelled: "bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400",
    };
    const labels = { draft: "Draft", in_transit: "In Transit", completed: "Selesai", cancelled: "Batal" };
    return <span className={`inline-flex rounded-full px-3 py-1.5 text-sm font-semibold ${styles[status] || styles.draft}`}>{labels[status] || status}</span>;
};

export default function Show({ transfer }) {
    const { can } = useAuthorization();

    const confirmAction = (action, label) => {
        if (!confirm(`Yakin ingin ${label} transfer ini?`)) return;
        router.post(route(`stock-transfers.${action}`, transfer.id));
    };

    return (
        <>
            <Head title={`Transfer ${transfer.document_number}`} />

            <div className="space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <Link href={route("stock-transfers.index")} className="mb-3 inline-flex items-center gap-2 text-sm text-slate-500 hover:text-primary-600">
                            <IconArrowLeft size={16} /> Kembali ke daftar transfer
                        </Link>
                        <h1 className="flex items-center gap-2 text-2xl font-bold text-slate-900 dark:text-white">
                            <IconArrowsLeftRight size={28} className="text-primary-500" />
                            {transfer.document_number}
                        </h1>
                    </div>
                    {statusBadge(transfer.status)}
                </div>

                <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Detail Transfer</h2>
                        <div className="mt-5 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Gudang Asal</p>
                                <p className="mt-1 text-sm text-slate-900 dark:text-white">{transfer.source_warehouse?.name || "-"}</p>
                            </div>
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Gudang Tujuan</p>
                                <p className="mt-1 text-sm text-slate-900 dark:text-white">{transfer.destination_warehouse?.name || "-"}</p>
                            </div>
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Dibuat Oleh</p>
                                <p className="mt-1 text-sm text-slate-900 dark:text-white">{transfer.creator?.name || "-"}</p>
                            </div>
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Dibuat Pada</p>
                                <p className="mt-1 text-sm text-slate-900 dark:text-white">{formatDateTime(transfer.created_at)}</p>
                            </div>
                            {transfer.completed_at && (
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Selesai</p>
                                    <p className="mt-1 text-sm text-slate-900 dark:text-white">{formatDateTime(transfer.completed_at)}</p>
                                </div>
                            )}
                            <div className="sm:col-span-2">
                                <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Catatan</p>
                                <p className="mt-1 text-sm text-slate-700 dark:text-slate-300">{transfer.notes || "-"}</p>
                            </div>
                        </div>

                        <h3 className="mt-6 text-base font-semibold text-slate-900 dark:text-white">Item Transfer</h3>
                        <div className="mt-3 overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 dark:border-slate-700">
                                        <th className="px-3 py-2 text-left font-semibold text-slate-700 dark:text-slate-200">Produk</th>
                                        <th className="px-3 py-2 text-right font-semibold text-slate-700 dark:text-slate-200">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transfer.items.map((item) => (
                                        <tr key={item.id} className="border-b border-slate-100 dark:border-slate-800">
                                            <td className="px-3 py-3">
                                                <p className="font-medium text-slate-800 dark:text-slate-200">{item.product?.title || "-"}</p>
                                                <p className="text-xs text-slate-500">{item.product?.sku || "-"}</p>
                                            </td>
                                            <td className="px-3 py-3 text-right font-medium text-slate-800 dark:text-slate-200">{item.qty}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="space-y-4">
                        {transfer.status === "draft" && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                                <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Aksi</h2>
                                <div className="mt-4 space-y-3">
                                    {can("stock-transfers-send") && (
                                        <button onClick={() => confirmAction("send", "mengirim")} className="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-500 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-600">
                                            <IconSend size={18} /> Kirim Barang
                                        </button>
                                    )}
                                    {can("stock-transfers-cancel") && (
                                        <button onClick={() => confirmAction("cancel", "membatalkan")} className="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-medium text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900 dark:text-rose-400 dark:hover:bg-rose-950/20">
                                            <IconX size={18} /> Batalkan
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}

                        {transfer.status === "in_transit" && (
                            <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                                <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Aksi</h2>
                                <div className="mt-4 space-y-3">
                                    {can("stock-transfers-receive") && (
                                        <button onClick={() => confirmAction("receive", "menerima")} className="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-600">
                                            <IconCheck size={18} /> Terima Barang
                                        </button>
                                    )}
                                    {can("stock-transfers-cancel") && (
                                        <button onClick={() => confirmAction("cancel", "membatalkan")} className="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-medium text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900 dark:text-rose-400 dark:hover:bg-rose-950/20">
                                            <IconX size={18} /> Batalkan & Kembalikan
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Show.layout = (page) => <DashboardLayout children={page} />;

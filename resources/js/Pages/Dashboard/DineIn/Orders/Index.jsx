import React from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, usePage, router } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import {
    IconClock,
    IconCheck,
    IconX,
    IconEye,
    IconDatabaseOff,
    IconRefresh,
} from "@tabler/icons-react";
import Table from "@/Components/Dashboard/Table";
import { useAuthorization } from "@/Utils/authorization";
import toast from "react-hot-toast";

const STATUS_CONFIG = {
    submitted: { label: "Menunggu", color: "bg-warning-100 text-warning-700 dark:bg-warning-900/50 dark:text-warning-400", icon: IconClock },
    accepted: { label: "Diterima", color: "bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-400", icon: IconCheck },
    completed: { label: "Selesai", color: "bg-success-100 text-success-700 dark:bg-success-900/50 dark:text-success-400", icon: IconCheck },
    rejected: { label: "Ditolak", color: "bg-danger-100 text-danger-700 dark:bg-danger-900/50 dark:text-danger-400", icon: IconX },
    cancelled: { label: "Dibatalkan", color: "bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400", icon: IconX },
};

const PAY_CONFIG = {
    pay_at_counter: { label: "Bayar di Kasir", color: "text-slate-600 dark:text-slate-400" },
    pay_online: { label: "Bayar Online", color: "text-primary-600 dark:text-primary-400" },
};

export default function Index({ orders }) {
    const { auth } = usePage().props;
    const { can } = useAuthorization();
    const canProcess = can("dine-orders-process");

    const handleAccept = (order) => {
        if (!confirm("Terima pesanan ini dan lanjutkan ke kasir?")) return;
        router.post(
            route("dine-orders.accept", order.id),
            {},
            {
                onSuccess: () => toast.success("Pesanan diterima."),
                onError: () => toast.error("Gagal menerima pesanan."),
            }
        );
    };

    const handleReject = (order) => {
        const reason = prompt("Alasan penolakan (opsional):");
        if (reason === null) return;
        router.post(
            route("dine-orders.reject", order.id),
            { reason },
            {
                onSuccess: () => toast.success("Pesanan ditolak."),
                onError: () => toast.error("Gagal menolak pesanan."),
            }
        );
    };

    return (
        <>
            <Head title="Pesanan Dine-In" />

            <div className="mb-6">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                            Pesanan Dine-In
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            {orders.length} pesanan
                        </p>
                    </div>
                </div>
            </div>

            {orders.length > 0 ? (
                <div className="space-y-4">
                    {orders.map((order) => {
                        const status = STATUS_CONFIG[order.status] ?? { label: order.status, color: "bg-slate-100 text-slate-500", icon: IconClock };
                        const StatusIcon = status.icon;
                        const pay = PAY_CONFIG[order.payment_option] ?? { label: order.payment_option, color: "text-slate-500" };

                        return (
                            <div
                                key={order.id}
                                className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden"
                            >
                                <div className="p-4 border-b border-slate-100 dark:border-slate-800">
                                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                        <div>
                                            <div className="flex items-center gap-2 mb-1">
                                                <span className="font-semibold text-slate-800 dark:text-slate-200">
                                                    {order.table?.name ?? "Meja"}
                                                </span>
                                                <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium ${status.color}`}>
                                                    <StatusIcon size={12} />
                                                    {status.label}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                                                <span>{order.item_count} item</span>
                                                <span>Subtotal: Rp {Number(order.subtotal ?? 0).toLocaleString("id-ID")}</span>
                                                <span className={pay.color}>{pay.label}</span>
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            {canProcess && order.status === "submitted" && (
                                                <>
                                                    <Button
                                                        type={"button"}
                                                        label={"Terima"}
                                                        icon={<IconCheck size={16} strokeWidth={1.5} />}
                                                        className={
                                                            "bg-success-500 hover:bg-success-600 text-white"
                                                        }
                                                        onClick={() => handleAccept(order)}
                                                    />
                                                    <Button
                                                        type={"button"}
                                                        label={"Tolak"}
                                                        icon={<IconX size={16} strokeWidth={1.5} />}
                                                        className={
                                                            "border border-danger-200 text-danger-600 hover:bg-danger-50 dark:border-danger-800 dark:text-danger-400"
                                                        }
                                                        onClick={() => handleReject(order)}
                                                    />
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="p-4">
                                    <div className="space-y-2">
                                        {(order.items ?? []).map((item) => (
                                            <div key={item.id} className="flex justify-between items-center py-2 border-b border-slate-50 dark:border-slate-800 last:border-0">
                                                <div>
                                                    <p className="text-sm font-medium text-slate-700 dark:text-slate-300">
                                                        {item.product?.title ?? "Produk"}
                                                    </p>
                                                    {item.note && (
                                                        <p className="text-xs text-slate-400 mt-0.5">Catatan: {item.note}</p>
                                                    )}
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm text-slate-700 dark:text-slate-300">
                                                        {item.qty}x Rp {Number(item.price).toLocaleString("id-ID")}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    {order.notes && (
                                        <div className="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                                <span className="font-medium">Catatan:</span> {order.notes}
                                            </p>
                                        </div>
                                    )}

                                    <div className="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center">
                                        <span className="text-xs text-slate-400">
                                            {order.created_at}
                                        </span>
                                        <span className="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                            Total: Rp {Number(order.subtotal ?? 0).toLocaleString("id-ID")}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <div className="flex flex-col items-center justify-center py-16 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    <div className="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                        <IconDatabaseOff size={32} className="text-slate-400" strokeWidth={1.5} />
                    </div>
                    <h3 className="text-lg font-medium text-slate-800 dark:text-slate-200 mb-1">
                        Tidak Ada Pesanan
                    </h3>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                        Pesanan dari pelanggan akan muncul di sini.
                    </p>
                </div>
            )}
        </>
    );
}

Index.layout = (page) => <DashboardLayout children={page} />;

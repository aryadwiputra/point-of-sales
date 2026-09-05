import React, { useEffect, useState } from "react";
import { Head } from "@inertiajs/react";
import { IconCheck, IconClock, IconX, IconRefresh } from "@tabler/icons-react";

const fmt = (v) => Number(v || 0).toLocaleString("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 });

const STATUS_CONFIG = {
    submitted: { label: "Menunggu Konfirmasi", color: "bg-amber-100 text-amber-700", icon: IconClock, desc: "Pesanan Anda sedang menunggu konfirmasi dari staff." },
    accepted: { label: "Diterima", color: "bg-primary-100 text-primary-700", icon: IconCheck, desc: "Pesanan diterima. Silakan menuju kasir untuk pembayaran." },
    completed: { label: "Selesai", color: "bg-emerald-100 text-emerald-700", icon: IconCheck, desc: "Pesanan sudah selesai." },
    rejected: { label: "Ditolak", color: "bg-rose-100 text-rose-700", icon: IconX, desc: "Pesanan ditolak oleh staff." },
    cancelled: { label: "Dibatalkan", color: "bg-slate-100 text-slate-500", icon: IconX, desc: "Pesanan dibatalkan." },
};

export default function DineOrderStatus({ order, table, storeName }) {
    const [currentOrder, setCurrentOrder] = useState(order);
    const [refreshing, setRefreshing] = useState(false);

    useEffect(() => {
        if (currentOrder.status === "submitted") {
            const interval = setInterval(async () => {
                setRefreshing(true);
                try {
                    const res = await fetch(route("dine-order.status-check", currentOrder.access_token), {
                        headers: { Accept: "application/json" },
                    });
                    const data = await res.json();
                    if (data.order) {
                        // status-check returns minimal scalar fields — merge so items/product rows from the initial render are kept
                        setCurrentOrder((prev) => ({ ...prev, ...data.order }));
                        if (data.order.status !== "submitted") {
                            clearInterval(interval);
                        }
                    }
                } catch (_) {}
                setRefreshing(false);
            }, 5000);
            return () => clearInterval(interval);
        }
    }, [currentOrder.status, currentOrder.access_token]);

    const status = STATUS_CONFIG[currentOrder.status] ?? { label: currentOrder.status, color: "bg-slate-100 text-slate-600", icon: IconClock, desc: "" };
    const StatusIcon = status.icon;

    return (
        <>
            <Head title={`Status Pesanan — ${storeName}`} />
            <div className="min-h-screen bg-slate-50 flex flex-col items-center justify-center px-4 py-8">
                <div className="w-full max-w-md bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div className="bg-gradient-to-r from-primary-500 to-primary-700 px-6 py-5 text-white text-center">
                        <p className="text-sm opacity-80">PESANAN DINE-IN</p>
                        <p className="text-lg font-bold mt-1">Meja {table.name}</p>
                        <p className="text-xs opacity-80 mt-1">{storeName}</p>
                    </div>

                    <div className="p-6">
                        <div className={`flex items-center gap-3 p-4 rounded-xl ${status.color} mb-6`}>
                            <StatusIcon size={24} />
                            <div>
                                <p className="font-semibold">{status.label}</p>
                                <p className="text-sm opacity-80">{status.desc}</p>
                            </div>
                        </div>

                        <div className="space-y-2 mb-4">
                            {(currentOrder.items ?? []).map((item) => (
                                <div key={item.id} className="flex justify-between items-center py-2 border-b border-slate-100 last:border-0">
                                    <div className="flex items-center gap-2">
                                        <span className="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-xs font-medium text-slate-600">
                                            {item.qty}
                                        </span>
                                        <span className="text-sm text-slate-700">{item.product?.title ?? "Produk"}</span>
                                    </div>
                                    <span className="text-sm text-slate-600">
                                        {fmt(item.price)}
                                    </span>
                                </div>
                            ))}
                        </div>

                        <div className="flex justify-between items-center pt-3 border-t border-slate-200">
                            <span className="font-semibold text-slate-700">Total</span>
                            <span className="font-bold text-lg text-primary-600">{fmt(currentOrder.subtotal)}</span>
                        </div>

                        {currentOrder.notes && (
                            <div className="mt-4 p-3 bg-slate-50 rounded-lg">
                                <p className="text-xs text-slate-500 mb-1">Catatan:</p>
                                <p className="text-sm text-slate-700">{currentOrder.notes}</p>
                            </div>
                        )}

                        <div className="mt-4 flex flex-col gap-2">
                            <div className="flex items-center justify-between text-xs text-slate-400">
                                <span>No. Pesanan: {currentOrder.id}</span>
                                <button
                                    onClick={() => window.location.reload()}
                                    className="flex items-center gap-1 hover:text-slate-600 transition-colors"
                                >
                                    <IconRefresh size={12} className={refreshing ? "animate-spin" : ""} />
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

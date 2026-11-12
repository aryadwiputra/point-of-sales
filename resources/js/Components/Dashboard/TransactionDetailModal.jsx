import React, { useState } from "react";
import { Link } from "@inertiajs/react";
import {
    IconX,
    IconReceipt,
    IconPrinter,
    IconBrandWhatsapp,
    IconTruckDelivery,
    IconCopy,
    IconCheck,
    IconPackage,
    IconUser,
    IconClock,
    IconBuildingBank,
    IconMapPin,
    IconExternalLink,
} from "@tabler/icons-react";
import { getProductImageUrl } from "@/Utils/imageUrl";
import toast from "react-hot-toast";

const formatCurrency = (value = 0) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(value);

const formatDate = (dateString) => {
    if (!dateString) return "-";
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(date);
};

export default function TransactionDetailModal({
    isOpen,
    onClose,
    transaction,
}) {
    const [copied, setCopied] = useState(false);

    React.useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === "Escape") {
                onClose();
            }
        };
        if (isOpen) {
            window.addEventListener("keydown", handleKeyDown);
        }
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [isOpen, onClose]);

    if (!isOpen || !transaction) return null;

    const copyInvoice = () => {
        navigator.clipboard?.writeText(transaction.invoice);
        setCopied(true);
        toast.success("Nomor invoice disalin");
        setTimeout(() => setCopied(false), 2000);
    };

    const details = transaction.details || [];
    const customer = transaction.customer;
    const warehouse = transaction.warehouse;
    const cashier = transaction.cashier;
    const receivable = transaction.receivable;

    const orderTypeLabel = {
        in_store: "Di Tempat",
        takeaway: "Bawa Pulang",
        delivery: "Diantar",
    }[transaction.order_type] || "Di Tempat";

    const paymentMethodLabel = {
        cash: "Tunai",
        transfer: "Transfer Bank",
        qris: "QRIS",
        midtrans: "Payment Gateway",
        xendit: "Xendit",
        pay_later: "Bayar Belakangan (Piutang)",
    }[transaction.payment_method] || transaction.payment_method?.replace("_", " ") || "Tunai";

    const itemsSubtotal = details.reduce((acc, item) => acc + (Number(item.price) || 0), 0);

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto animate-in fade-in duration-200"
            onClick={onClose}
        >
            <div
                className="relative w-full max-w-2xl bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden my-6 flex flex-col max-h-[90vh]"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/70 dark:bg-slate-800/40">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl bg-primary-100 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 flex items-center justify-center flex-shrink-0">
                            <IconReceipt size={22} />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                    Detail Pesanan
                                </h3>
                                <button
                                    onClick={copyInvoice}
                                    className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-slate-200/70 dark:bg-slate-700 text-xs font-mono font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors"
                                    title="Salin nomor invoice"
                                >
                                    {transaction.invoice}
                                    {copied ? (
                                        <IconCheck size={12} className="text-emerald-500" />
                                    ) : (
                                        <IconCopy size={12} />
                                    )}
                                </button>
                            </div>
                            <p className="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1 mt-0.5">
                                <IconClock size={13} />
                                {formatDate(transaction.created_at)}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {transaction.payment_method === "pay_later" &&
                        transaction.payment_status !== "paid" ? (
                            <span className="px-2.5 py-1 text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 rounded-full">
                                Piutang
                            </span>
                        ) : transaction.payment_status === "paid" ? (
                            <span className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 rounded-full">
                                <IconCheck size={13} />
                                Lunas
                            </span>
                        ) : (
                            <span className="px-2.5 py-1 text-xs font-semibold bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-400 rounded-full">
                                {transaction.payment_status}
                            </span>
                        )}
                        <button
                            onClick={onClose}
                            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            <IconX size={20} />
                        </button>
                    </div>
                </div>

                {/* Body Content */}
                <div className="p-5 space-y-5 overflow-y-auto flex-1">
                    {/* Info Card */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-xl text-xs">
                        <div>
                            <span className="text-slate-400 block font-medium mb-0.5">Kasir</span>
                            <span className="font-semibold text-slate-700 dark:text-slate-300">
                                {cashier?.name || "-"}
                            </span>
                        </div>
                        <div>
                            <span className="text-slate-400 block font-medium mb-0.5">Outlet / Gudang</span>
                            <span className="font-semibold text-slate-700 dark:text-slate-300">
                                {warehouse?.name || "-"}
                            </span>
                        </div>
                        <div>
                            <span className="text-slate-400 block font-medium mb-0.5">Tipe Pesanan</span>
                            <span className="font-semibold text-slate-700 dark:text-slate-300">
                                {orderTypeLabel}
                            </span>
                        </div>
                        <div>
                            <span className="text-slate-400 block font-medium mb-0.5">Metode Bayar</span>
                            <span className="font-semibold text-slate-700 dark:text-slate-300 capitalize">
                                {paymentMethodLabel}
                            </span>
                        </div>
                    </div>

                    {/* Pelanggan */}
                    <div className="border border-slate-100 dark:border-slate-800 rounded-xl p-3.5 flex items-start justify-between gap-3 text-xs">
                        <div className="space-y-1">
                            <span className="text-slate-400 font-medium block">Data Pelanggan</span>
                            <p className="font-semibold text-sm text-slate-800 dark:text-slate-200">
                                {customer?.name || "Pelanggan Umum"}
                            </p>
                            {customer?.no_telp && (
                                <p className="text-slate-500 dark:text-slate-400">
                                    No. HP: {customer.no_telp}
                                </p>
                            )}
                            {customer?.address && (
                                <p className="text-slate-500 dark:text-slate-400 flex items-start gap-1">
                                    <IconMapPin size={14} className="mt-0.5 flex-shrink-0" />
                                    <span>
                                        {customer.address}
                                        {customer.district_name ? `, ${customer.district_name}` : ""}
                                        {customer.regency_name ? `, ${customer.regency_name}` : ""}
                                    </span>
                                </p>
                            )}
                        </div>
                        {transaction.note && (
                            <div className="text-right max-w-[200px]">
                                <span className="text-slate-400 font-medium block">Catatan</span>
                                <p className="text-slate-600 dark:text-slate-300 italic">
                                    "{transaction.note}"
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Daftar Item Produk */}
                    <div>
                        <div className="flex items-center justify-between mb-2">
                            <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                                Produk Dipesan ({details.length} item)
                            </h4>
                        </div>
                        <div className="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden divide-y divide-slate-100 dark:divide-slate-800">
                            {details.map((item, idx) => (
                                <div
                                    key={item.id || idx}
                                    className="p-3 flex items-center justify-between gap-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                >
                                    <div className="flex items-center gap-3 min-w-0 flex-1">
                                        <div className="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            {item.product?.image ? (
                                                <img
                                                    src={getProductImageUrl(item.product.image)}
                                                    alt={item.product?.title}
                                                    className="w-full h-full object-cover"
                                                />
                                            ) : (
                                                <IconPackage size={20} className="text-slate-400" />
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold text-slate-800 dark:text-slate-200 truncate">
                                                {item.product?.title || "Produk"}
                                            </p>
                                            <div className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                <span>
                                                    {formatCurrency(item.unit_price)} × {item.qty} {item.unit?.symbol || item.unit?.name || "pcs"}
                                                </span>
                                                {item.discount_total > 0 && (
                                                    <span className="text-amber-600 font-medium">
                                                        (Disc -{formatCurrency(item.discount_total)})
                                                    </span>
                                                )}
                                                {item.product?.barcode && (
                                                    <span className="text-[11px] text-slate-400">
                                                        [{item.product.barcode}]
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-right flex-shrink-0">
                                        <span className="text-sm font-bold text-slate-900 dark:text-white">
                                            {formatCurrency(item.price)}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Ringkasan Pembayaran */}
                    <div className="border border-slate-200 dark:border-slate-800 rounded-xl p-4 space-y-2 bg-slate-50/50 dark:bg-slate-800/30 text-xs">
                        <div className="flex justify-between text-slate-600 dark:text-slate-400">
                            <span>Subtotal Produk</span>
                            <span className="font-medium text-slate-800 dark:text-slate-200">
                                {formatCurrency(itemsSubtotal || transaction.grand_total)}
                            </span>
                        </div>

                        {transaction.discount > 0 && (
                            <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                <span>Diskon Transaksi</span>
                                <span className="font-medium text-rose-600">
                                    -{formatCurrency(transaction.discount)}
                                </span>
                            </div>
                        )}

                        {transaction.customer_voucher_discount > 0 && (
                            <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                <span>Voucher ({transaction.customer_voucher_code || "Kupon"})</span>
                                <span className="font-medium text-rose-600">
                                    -{formatCurrency(transaction.customer_voucher_discount)}
                                </span>
                            </div>
                        )}

                        {transaction.loyalty_discount_total > 0 && (
                            <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                <span>Diskon Loyalty / Poin</span>
                                <span className="font-medium text-rose-600">
                                    -{formatCurrency(transaction.loyalty_discount_total)}
                                </span>
                            </div>
                        )}

                        {transaction.tax_total > 0 && (
                            <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                <span>PPN ({transaction.tax_rate || 11}%)</span>
                                <span className="font-medium text-slate-800 dark:text-slate-200">
                                    {formatCurrency(transaction.tax_total)}
                                </span>
                            </div>
                        )}

                        {transaction.shipping_cost > 0 && (
                            <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                <span>Biaya Pengiriman</span>
                                <span className="font-medium text-slate-800 dark:text-slate-200">
                                    {formatCurrency(transaction.shipping_cost)}
                                </span>
                            </div>
                        )}

                        <div className="pt-2 border-t border-slate-200 dark:border-slate-700 flex justify-between items-center text-sm">
                            <span className="font-bold text-slate-900 dark:text-white">
                                Total Pembayaran
                            </span>
                            <span className="font-extrabold text-base text-primary-600 dark:text-primary-400">
                                {formatCurrency(transaction.grand_total)}
                            </span>
                        </div>

                        {/* Cash Details */}
                        {transaction.payment_method === "cash" && (
                            <div className="pt-2 border-t border-dashed border-slate-200 dark:border-slate-700 space-y-1">
                                <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                    <span>Uang Diterima</span>
                                    <span className="font-medium text-slate-800 dark:text-slate-200">
                                        {formatCurrency(transaction.cash)}
                                    </span>
                                </div>
                                <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                    <span>Kembalian</span>
                                    <span className="font-medium text-emerald-600">
                                        {formatCurrency(transaction.change)}
                                    </span>
                                </div>
                            </div>
                        )}

                        {/* Bank Details */}
                        {transaction.bank_account && (
                            <div className="pt-2 border-t border-dashed border-slate-200 dark:border-slate-700 flex justify-between text-slate-600 dark:text-slate-400">
                                <span>Rekening Tujuan</span>
                                <span className="font-medium text-slate-800 dark:text-slate-200">
                                    {transaction.bank_account.bank_name} ({transaction.bank_account.account_number})
                                </span>
                            </div>
                        )}

                        {/* Receivable / Pay Later Details */}
                        {receivable && receivable.status !== "paid" && (
                            <div className="mt-2 p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50 rounded-lg space-y-1 text-xs">
                                <div className="flex justify-between text-amber-800 dark:text-amber-300 font-semibold">
                                    <span>Sisa Piutang</span>
                                    <span>{formatCurrency(receivable.remaining ?? (receivable.total - receivable.paid))}</span>
                                </div>
                                {receivable.due_date && (
                                    <div className="flex justify-between text-amber-700 dark:text-amber-400">
                                        <span>Jatuh Tempo</span>
                                        <span>{formatDate(receivable.due_date)}</span>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* Footer Buttons */}
                <div className="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Link
                            href={route("transactions.print", transaction.invoice)}
                            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-primary-600 hover:bg-primary-700 text-white transition-colors"
                        >
                            <IconPrinter size={16} />
                            <span>Cetak Struk</span>
                        </Link>
                        <a
                            href={`https://wa.me/?text=${encodeURIComponent(
                                `Invoice ${transaction.invoice}: ${route(
                                    "transactions.public",
                                    transaction.invoice,
                                    { token: transaction.access_token }
                                )}`
                            )}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 transition-colors"
                        >
                            <IconBrandWhatsapp size={16} />
                            <span>WhatsApp</span>
                        </a>
                        <a
                            href={route("pdf.transactions.shipping", transaction.invoice)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300 transition-colors"
                        >
                            <IconTruckDelivery size={16} />
                            <span>Resi</span>
                        </a>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 rounded-xl text-xs font-medium border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    );
}

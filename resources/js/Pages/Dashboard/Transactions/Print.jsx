import React, { useMemo, useState } from "react";
import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    IconArrowLeft,
    IconPrinter,
    IconExternalLink,
    IconReceipt,
    IconFileInvoice,
    IconTruck,
    IconBuildingBank,
    IconCheck,
    IconAlertCircle,
} from "@tabler/icons-react";
import ThermalReceipt, {
    ThermalReceipt58mm,
} from "@/Components/Receipt/ThermalReceipt";
import ShippingLabel from "@/Components/Receipt/ShippingLabel";
import { useAuthorization } from "@/Utils/authorization";

export default function Print({ transaction }) {
    const { storeProfile } = usePage().props;
    const { can } = useAuthorization();
    const [printMode, setPrintMode] = useState("invoice"); // 'invoice' | 'thermal80' | 'thermal58'
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [isConfirming, setIsConfirming] = useState(false);
    const canConfirmPayment = can("transactions-confirm-payment");

    const formatPrice = (price = 0) =>
        Number(price || 0).toLocaleString("id-ID", {
            style: "currency",
            currency: "IDR",
            minimumFractionDigits: 0,
        });

    const formatDateTime = (value) =>
        new Date(value).toLocaleString("id-ID", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });

    const items = transaction?.details ?? [];
    const promoDiscountTotal = useMemo(
        () =>
            items.reduce(
                (sum, item) => sum + Number(item.discount_total || 0),
                0
            ),
        [items]
    );
    const loyaltyDiscountTotal = Number(
        transaction?.loyalty_discount_total || 0
    );
    const voucherDiscountTotal = Number(
        transaction?.customer_voucher_discount || 0
    );
    const baseSubtotal =
        (transaction?.grand_total || 0) +
        (transaction?.discount || 0) -
        (transaction?.shipping_cost || 0) -
        (transaction?.tax_total || 0) +
        promoDiscountTotal +
        loyaltyDiscountTotal +
        voucherDiscountTotal;

    const store = useMemo(
        () => ({
            name: storeProfile?.name || "Toko Anda",
            logo: storeProfile?.logo || null,
            address: storeProfile?.address || "",
            phone: storeProfile?.phone || "",
            email: storeProfile?.email || "",
            website: storeProfile?.website || "",
        }),
        [storeProfile]
    );

    const paymentLabels = {
        cash: "Tunai",
        bank_transfer: "Transfer Bank",
        midtrans: "Midtrans",
        xendit: "Xendit",
        pay_later: "Piutang",
    };
    const paymentMethodKey = (
        transaction?.payment_method || "cash"
    ).toLowerCase();
    const paymentMethodLabel = paymentLabels[paymentMethodKey] ?? "Tunai";

    const paymentStatuses = {
        paid: "Lunas",
        pending: transaction?.payment_method === "pay_later" ? "Belum Lunas" : "Menunggu",
        failed: "Gagal",
        expired: "Kedaluwarsa",
        unpaid: "Belum Lunas",
        partial: "Parsial",
    };
    const paymentStatusKey = (transaction?.payment_status || "").toLowerCase();
    const paymentStatusLabel =
        paymentStatuses[paymentStatusKey] ??
        (paymentMethodKey === "cash" ? "Lunas" : "Menunggu");

    const statusColors = {
        paid: "bg-success-100 text-success-700 dark:bg-success-900/50 dark:text-success-400",
        pending:
            "bg-warning-100 text-warning-700 dark:bg-warning-900/50 dark:text-warning-400",
        unpaid:
            "bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400",
        partial:
            "bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-400",
        failed: "bg-danger-100 text-danger-700 dark:bg-danger-900/50 dark:text-danger-400",
        expired:
            "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400",
    };
    const paymentStatusColor =
        statusColors[paymentStatusKey] ?? statusColors.paid;

    const isNonCash = paymentMethodKey !== "cash";
    const showPaymentLink = isNonCash && transaction.payment_url;

    const handlePrint = () => {
        window.print();
    };

    const SimpleBarcode = ({ value }) => {
        const bars = useMemo(() => {
            const data = value || "";
            return data.split("").map((char, idx) => {
                const weight = (char.charCodeAt(0) + idx * 17) % 5;
                return 2 + weight; // 2-6px width
            });
        }, [value]);
        const totalWidth = bars.reduce((acc, w) => acc + w, 0);
        const targetWidth = 180; // px target
        const scale = totalWidth ? Math.min(2.2, targetWidth / totalWidth) : 1;

        return (
            <div className="flex items-end gap-[2px] mt-4">
                {bars.map((w, i) => (
                    <span
                        key={i}
                        style={{ width: `${w * scale}px` }}
                        className="h-10 sm:h-14 bg-slate-800 dark:bg-slate-100 block"
                    />
                ))}
            </div>
        );
    };

    return (
        <>
            <Head title="Invoice Penjualan" />

            <div className="min-h-screen bg-slate-100 dark:bg-slate-950 py-8 px-4 print:bg-white print:p-0">
                <div className="max-w-4xl mx-auto space-y-6">
                    {/* Action Bar */}
                    <div className="flex flex-wrap items-start justify-between gap-3 print:hidden">
                        <Link
                            href={route("transactions.index")}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                        >
                            <IconArrowLeft size={18} />
                            Kembali ke kasir
                        </Link>

                        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                            {/* Print Mode Selector */}
                            <div className="flex bg-slate-200 dark:bg-slate-800 rounded-xl p-1 w-full sm:w-auto">
                                <button
                                    onClick={() => setPrintMode("invoice")}
                                    className={`px-3 py-2 rounded-lg text-xs font-medium transition-all ${
                                        printMode === "invoice"
                                            ? "bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow"
                                            : "text-slate-500 dark:text-slate-400 hover:text-slate-700"
                                    }`}
                                >
                                    <IconFileInvoice
                                        size={16}
                                        className="inline mr-1"
                                    />
                                    Invoice
                                </button>
                                <button
                                    onClick={() => setPrintMode("thermal80")}
                                    className={`px-3 py-2 rounded-lg text-xs font-medium transition-all ${
                                        printMode === "thermal80"
                                            ? "bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow"
                                            : "text-slate-500 dark:text-slate-400 hover:text-slate-700"
                                    }`}
                                >
                                    <IconReceipt
                                        size={16}
                                        className="inline mr-1"
                                    />
                                    Struk 80mm
                                </button>
                                <button
                                    onClick={() => setPrintMode("thermal58")}
                                    className={`px-3 py-2 rounded-lg text-xs font-medium transition-all ${
                                        printMode === "thermal58"
                                            ? "bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow"
                                            : "text-slate-500 dark:text-slate-400 hover:text-slate-700"
                                    }`}
                                >
                                    <IconReceipt
                                        size={16}
                                        className="inline mr-1"
                                    />
                                    Struk 58mm
                                </button>
                                <button
                                    onClick={() => setPrintMode("shipping")}
                                    className={`px-3 py-2 rounded-lg text-xs font-medium transition-all ${
                                        printMode === "shipping"
                                            ? "bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow"
                                            : "text-slate-500 dark:text-slate-400 hover:text-slate-700"
                                    }`}
                                >
                                    <IconTruck
                                        size={16}
                                        className="inline mr-1"
                                    />
                                    Resi
                                </button>
                            </div>

                            {showPaymentLink && (
                                <a
                                    href={transaction.payment_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-primary-200 dark:border-primary-800 text-sm font-semibold text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/50 transition-colors w-full sm:w-auto"
                                >
                                    <IconExternalLink size={18} />
                                    Pembayaran
                                </a>
                            )}

                            {/* Confirm Payment Button - Only for pending bank_transfer */}
                            {paymentMethodKey === "bank_transfer" &&
                                paymentStatusKey === "pending" &&
                                canConfirmPayment && (
                                    <button
                                        onClick={() =>
                                            setShowConfirmModal(true)
                                        }
                                        className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-success-500 hover:bg-success-600 text-sm font-semibold text-white transition-colors w-full sm:w-auto"
                                    >
                                        <IconCheck size={18} />
                                        Konfirmasi Bayar
                                    </button>
                                )}

                            {printMode === "invoice" && (
                                <a
                                    href={route("pdf.transactions.invoice", transaction.invoice)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary-500 hover:bg-primary-600 text-sm font-semibold text-white shadow-lg shadow-primary-500/30 transition-colors w-full sm:w-auto"
                                >
                                    <IconPrinter size={18} />
                                    PDF Invoice
                                </a>
                            )}

                            {(printMode === "thermal80" || printMode === "thermal58") && (
                                <a
                                    href={route("pdf.transactions.receipt", {
                                        invoice: transaction.invoice,
                                        size: printMode === "thermal58" ? "58" : "80",
                                    })}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-sm font-semibold text-white transition-colors w-full sm:w-auto"
                                >
                                    <IconPrinter size={18} />
                                    PDF Struk {printMode === "thermal58" ? "58mm" : "80mm"}
                                </a>
                            )}

                            {printMode === "shipping" && (
                                <a
                                    href={route("pdf.transactions.shipping", transaction.invoice)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-sm font-semibold text-white transition-colors w-full sm:w-auto"
                                >
                                    <IconPrinter size={18} />
                                    PDF Resi
                                </a>
                            )}
                        </div>
                    </div>

                    {/* Thermal Receipt Preview */}
                    {(printMode === "thermal80" || printMode === "thermal58") && (
                        <div className="flex justify-center print:block">
                            <div className="bg-white rounded-2xl border border-slate-200 dark:border-slate-700 shadow-xl p-4 print:shadow-none print:border-0 print:p-0 print:rounded-none">
                                {printMode === "thermal80" ? (
                                    <ThermalReceipt
                                        transaction={transaction}
                                        storeName={store.name}
                                        storeAddress={store.address}
                                        storePhone={store.phone}
                                        storeEmail={store.email}
                                        storeWebsite={store.website}
                                    />
                                ) : (
                                    <ThermalReceipt58mm
                                        transaction={transaction}
                                        storeName={store.name}
                                        storePhone={store.phone}
                                        storeEmail={store.email}
                                        storeWebsite={store.website}
                                    />
                                )}
                            </div>
                        </div>
                    )}

                    {/* Shipping Label Preview */}
{printMode === "shipping" && (
    <div className="flex justify-center items-center py-10 print:py-0 print:block">
        <div className="w-full max-w-[150mm] mx-auto transition-all duration-300 transform scale-100 md:scale-110 lg:scale-125 print:scale-100">
            <ShippingLabel
                transaction={transaction}
                store={store}
            />
        </div>
    </div>
)}

                    {/* Invoice View */}
                    {printMode === "invoice" && (
                        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xl print:shadow-none print:border-slate-300">
                            {/* Header */}
                            <div className="bg-gradient-to-r from-primary-500 to-primary-700 px-4 sm:px-6 py-5 sm:py-6 text-white print:bg-slate-100 print:text-slate-900">
                                <div className="flex flex-col items-center text-center gap-4 sm:gap-5 sm:grid sm:grid-cols-[1.4fr,1fr] sm:text-left sm:items-start">
                                    <div className="flex flex-col sm:flex-row items-center sm:items-start gap-2 sm:gap-3 min-w-0">
                                        <div className="w-12 h-12 sm:w-14 sm:h-14 flex items-center justify-center p-1 flex-shrink-0">
                                            {store.logo ? (
                                                <img
                                                    src={store.logo}
                                                    alt={store.name}
                                                    className="max-w-full max-h-full object-contain"
                                                />
                                            ) : (
                                                <span className="text-lg font-bold text-white print:text-slate-800">
                                                    {store.name.charAt(0)}
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-white print:text-slate-800 space-y-1 min-w-0 text-center sm:text-left">
                                            <p className="text-base sm:text-lg font-bold leading-tight">
                                                {store.name}
                                            </p>
                                            {store.address && (
                                                <p className="text-[11px] sm:text-xs opacity-90 leading-snug break-words">
                                                    {store.address}
                                                </p>
                                            )}
                                            {(store.phone ||
                                                store.email ||
                                                store.website) && (
                                                <p className="text-[11px] sm:text-xs opacity-90 space-x-2 leading-snug flex flex-wrap justify-center sm:justify-start gap-x-2 gap-y-1">
                                                    {store.phone && (
                                                        <span>
                                                            Telp: {store.phone}
                                                        </span>
                                                    )}
                                                    {store.email && (
                                                        <span>
                                                            Email: {store.email}
                                                        </span>
                                                    )}
                                                    {store.website && (
                                                        <span>{store.website}</span>
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="text-center sm:text-right">
                                        <div className="inline-flex flex-col items-center sm:items-end bg-white/5 print:bg-transparent rounded-xl px-3 py-2 sm:px-4 sm:py-3 min-w-[180px] sm:min-w-[200px]">
                                            <div className="flex items-center gap-2 mb-1 justify-center sm:justify-end">
                                                <IconReceipt size={20} className="sm:w-6 sm:h-6" />
                                                <span className="text-xs sm:text-sm font-medium opacity-90 print:opacity-100">
                                                    INVOICE
                                                </span>
                                            </div>
                                            <p className="text-lg sm:text-2xl font-bold leading-tight">
                                                {transaction.invoice}
                                            </p>
                                            <p className="text-xs sm:text-sm opacity-80 print:opacity-100 mt-1">
                                                {formatDateTime(
                                                    transaction.created_at
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Info Grid */}
                            <div className="grid md:grid-cols-2 gap-4 sm:gap-6 px-4 sm:px-6 py-4 sm:py-6 border-b border-slate-100 dark:border-slate-800">
                                <div className="bg-slate-50/60 dark:bg-slate-800/40 rounded-xl p-3 sm:p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
                                        Pelanggan
                                    </p>
                                    <p className="text-base font-semibold text-slate-900 dark:text-white">
                                        {transaction.customer?.name ?? "Umum"}
                                    </p>
                                    {transaction.customer?.address && (
                                        <p className="text-sm text-slate-600 dark:text-slate-400">
                                            {transaction.customer.address}
                                        </p>
                                    )}
                                    {transaction.customer?.phone && (
                                        <p className="text-sm text-slate-600 dark:text-slate-400">
                                            {transaction.customer.phone}
                                        </p>
                                    )}
                                </div>
                                <div className="bg-slate-50/60 dark:bg-slate-800/40 rounded-xl p-3 sm:p-4">
                                    <p className="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">
                                        Kasir
                                    </p>
                                    <div className="flex items-start justify-between gap-3">
                                        <p className="text-base font-semibold text-slate-900 dark:text-white">
                                            {transaction.cashier?.name ?? "-"}
                                        </p>
                                        <div className="flex flex-wrap gap-2 justify-end">
                                            <span
                                                className={`inline-block px-3 py-1 text-xs font-semibold rounded-full ${paymentStatusColor}`}
                                            >
                                                {paymentStatusLabel}
                                            </span>
                                            <span className="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                                {paymentMethodLabel}
                                            </span>
                                            {transaction.payment_method ===
                                                "pay_later" &&
                                                transaction.receivable && (
                                                    <span className="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                                        Jatuh tempo:{" "}
                                                        {transaction.receivable
                                                            ?.due_date || "-"}
                                                    </span>
                                                )}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Bank Transfer Info */}
                            {paymentMethodKey === "bank_transfer" &&
                                transaction.bank_account && (
                                    <div className="mx-6 mb-6 p-4 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">
                                            Silakan Transfer ke Rekening
                                        </p>
                                        <p className="text-lg font-bold text-slate-900 dark:text-white">
                                            {transaction.bank_account.bank_name}
                                        </p>
                                        <p className="text-base font-semibold text-primary-600 dark:text-primary-400">
                                            {
                                                transaction.bank_account
                                                    .account_number
                                            }
                                        </p>
                                        <p className="text-sm text-slate-600 dark:text-slate-400">
                                            a.n.{" "}
                                            {
                                                transaction.bank_account
                                                    .account_name
                                            }
                                        </p>
                                    </div>
                                )}

                            {/* Items Table */}
                            <div className="px-4 sm:px-6 py-6">
                                <div className="w-full overflow-x-auto">
                                    <table className="w-full min-w-[620px] text-sm">
                                        <thead>
                                            <tr className="border-b border-slate-100 dark:border-slate-800">
                                                <th className="pb-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                                    Produk
                                                </th>
                                                <th className="pb-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                                    Harga
                                                </th>
                                                <th className="pb-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                                    Qty
                                                </th>
                                                <th className="pb-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                                    Subtotal
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                            {items.map((item, index) => {
                                                const quantity =
                                                    Number(item.qty) || 1;
                                                const subtotal =
                                                    Number(item.price) || 0;
                                                const unitPrice =
                                                    Number(
                                                        item.unit_price || 0
                                                    ) || subtotal / quantity;
                                                const baseUnitPrice =
                                                    Number(
                                                        item.base_unit_price || 0
                                                    ) || unitPrice;
                                                const hasPromo =
                                                    Number(
                                                        item.discount_total || 0
                                                    ) > 0 &&
                                                    baseUnitPrice > unitPrice;

                                                return (
                                                    <tr
                                                        key={item.id ?? index}
                                                        className={
                                                            index % 2 === 0
                                                                ? "bg-slate-50/60 dark:bg-slate-800/30"
                                                                : ""
                                                        }
                                                    >
                                                        <td className="py-3">
                                                            <p className="font-medium text-slate-900 dark:text-white">
                                                                {
                                                                    item.product
                                                                        ?.title
                                                                }
                                                            </p>
                                                            {hasPromo && (
                                                                <p className="text-xs font-medium text-rose-500 dark:text-rose-400">
                                                                    {item.pricing_group_label ||
                                                                        item.pricing_rule_name ||
                                                                        "Promo aktif"}
                                                                </p>
                                                            )}
                                                            {item.product
                                                                ?.barcode && (
                                                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                                                    {
                                                                        item.product
                                                                            .barcode
                                                                    }
                                                                </p>
                                                            )}
                                                        </td>
                                                        <td className="py-3 text-right text-slate-600 dark:text-slate-400">
                                                            <div>
                                                                {hasPromo && (
                                                                    <p className="text-xs text-slate-400 line-through">
                                                                        {formatPrice(
                                                                            baseUnitPrice
                                                                        )}
                                                                    </p>
                                                                )}
                                                                <p>
                                                                    {formatPrice(
                                                                        unitPrice
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td className="py-3 text-center text-slate-600 dark:text-slate-400">
                                                            {quantity}
                                                        </td>
                                                        <td className="py-3 text-right font-semibold text-slate-900 dark:text-white">
                                                            {formatPrice(
                                                                subtotal
                                                            )}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Summary */}
                            <div className="bg-slate-50 dark:bg-slate-800/50 px-6 py-6">
                                <div className="max-w-xs ml-auto space-y-2 text-sm">
                                    <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                        <span>Subtotal</span>
                                        <span>{formatPrice(baseSubtotal)}</span>
                                    </div>
                                    {promoDiscountTotal > 0 && (
                                        <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                            <span>Promo Otomatis</span>
                                            <span>
                                                -{" "}
                                                {formatPrice(
                                                    promoDiscountTotal
                                                )}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                        <span>Diskon Manual</span>
                                        <span>
                                            -{" "}
                                            {formatPrice(transaction.discount)}
                                        </span>
                                    </div>
                                    {transaction.shipping_cost > 0 && (
                                        <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                            <span>Ongkos Kirim</span>
                                            <span>
                                                +{" "}
                                                {formatPrice(
                                                    transaction.shipping_cost
                                                )}
                                            </span>
                                        </div>
                                    )}
                                    {transaction.tax_total > 0 && (
                                        <div className="flex justify-between text-slate-600 dark:text-slate-400">
                                            <span>PPN {transaction.tax_rate ? Number(transaction.tax_rate).toFixed(0) : "11"}%</span>
                                            <span>
                                                +{" "}
                                                {formatPrice(transaction.tax_total)}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-lg font-bold text-slate-900 dark:text-white pt-2 border-t border-slate-200 dark:border-slate-700">
                                        <span>Total</span>
                                        <span>
                                            {formatPrice(
                                                transaction.grand_total
                                            )}
                                        </span>
                                    </div>
                                    {paymentMethodKey === "cash" && (
                                        <>
                                            <div className="flex justify-between text-slate-600 dark:text-slate-400 pt-2">
                                                <span>Tunai</span>
                                                <span>
                                                    {formatPrice(
                                                        transaction.cash
                                                    )}
                                                </span>
                                            </div>
                                            <div className="flex justify-between text-success-600 dark:text-success-400 font-medium">
                                                <span>Kembali</span>
                                                <span>
                                                    {formatPrice(
                                                        transaction.change
                                                    )}
                                                </span>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>

                            {/* Barcode + Footer */}
                            <div className="px-6 py-4 border-t border-slate-100 dark:border-slate-800">
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Invoice: {transaction.invoice}
                                </p>
                                <SimpleBarcode value={transaction.invoice} />
                                <div className="text-center mt-4">
                                    <p className="text-xs text-slate-400 dark:text-slate-500 uppercase tracking-widest">
                                        Terima kasih telah berbelanja
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Confirmation Modal */}
            {showConfirmModal && canConfirmPayment && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 print:hidden">
                    {/* Backdrop */}
                    <div
                        className="absolute inset-0 bg-black/50 backdrop-blur-sm"
                        onClick={() =>
                            !isConfirming && setShowConfirmModal(false)
                        }
                    />

                    {/* Modal */}
                    <div className="relative bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
                        {/* Header */}
                        <div className="bg-gradient-to-r from-primary-500 to-primary-600 px-6 py-5 text-white">
                            <div className="flex items-center gap-3">
                                <div className="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center">
                                    <IconBuildingBank size={24} />
                                </div>
                                <div>
                                    <h3 className="text-lg font-bold">
                                        Konfirmasi Pembayaran
                                    </h3>
                                    <p className="text-sm opacity-90">
                                        Transfer Bank
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Content */}
                        <div className="p-6 space-y-4">
                            {/* Invoice Info */}
                            <div className="bg-slate-50 dark:bg-slate-800 rounded-xl p-4">
                                <div className="flex justify-between items-center mb-2">
                                    <span className="text-sm text-slate-500 dark:text-slate-400">
                                        Invoice
                                    </span>
                                    <span className="text-sm font-bold text-slate-900 dark:text-white">
                                        {transaction.invoice}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center mb-2">
                                    <span className="text-sm text-slate-500 dark:text-slate-400">
                                        Pelanggan
                                    </span>
                                    <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
                                        {transaction.customer?.name ?? "Umum"}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-sm text-slate-500 dark:text-slate-400">
                                        Total
                                    </span>
                                    <span className="text-lg font-bold text-primary-600 dark:text-primary-400">
                                        {formatPrice(
                                            transaction.grand_total ?? 0
                                        )}
                                    </span>
                                </div>
                            </div>

                            {/* Confirmation Message */}
                            <div className="flex items-start gap-3 p-4 bg-warning-50 dark:bg-warning-900/20 rounded-xl border border-warning-200 dark:border-warning-800">
                                <IconAlertCircle
                                    size={20}
                                    className="text-warning-600 dark:text-warning-400 flex-shrink-0 mt-0.5"
                                />
                                <p className="text-sm text-warning-800 dark:text-warning-300">
                                    Pastikan dana sudah diterima sebelum
                                    mengkonfirmasi pembayaran ini. Tindakan ini
                                    tidak dapat dibatalkan.
                                </p>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="px-6 pb-6 flex gap-3">
                            <button
                                onClick={() => setShowConfirmModal(false)}
                                disabled={isConfirming}
                                className="flex-1 px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50"
                            >
                                Batal
                            </button>
                            <button
                                onClick={() => {
                                    setIsConfirming(true);
                                    router.patch(
                                        route(
                                            "transactions.confirm-payment",
                                            transaction.id
                                        ),
                                        {},
                                        {
                                            onSuccess: () => {
                                                setShowConfirmModal(false);
                                                setIsConfirming(false);
                                            },
                                            onError: () => {
                                                setIsConfirming(false);
                                            },
                                        }
                                    );
                                }}
                                disabled={isConfirming}
                                className="flex-1 px-4 py-3 rounded-xl bg-success-500 hover:bg-success-600 text-white font-medium transition-colors disabled:opacity-50 flex items-center justify-center gap-2"
                            >
                                {isConfirming ? (
                                    <>
                                        <svg
                                            className="animate-spin h-4 w-4"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle
                                                className="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                strokeWidth="4"
                                                fill="none"
                                            />
                                            <path
                                                className="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            />
                                        </svg>
                                        Memproses...
                                    </>
                                ) : (
                                    <>
                                        <IconCheck size={18} />
                                        Konfirmasi Lunas
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

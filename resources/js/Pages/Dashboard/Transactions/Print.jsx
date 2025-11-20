import React from "react";
import { Head, Link } from "@inertiajs/react";
import { IconArrowLeft, IconPrinter, IconExternalLink } from "@tabler/icons-react";

export default function Print({ transaction }) {
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

    const paymentLabels = {
        cash: "Tunai",
        midtrans: "Midtrans",
        xendit: "Xendit",
    };
    const paymentMethodKey =
        (transaction?.payment_method || "cash").toLowerCase();
    const paymentMethodLabel =
        paymentLabels[paymentMethodKey] ?? "Tunai";

    const paymentStatuses = {
        paid: "Lunas",
        pending: "Menunggu Pembayaran",
        failed: "Pembayaran Gagal",
        expired: "Pembayaran Kedaluwarsa",
    };
    const paymentStatusKey = (transaction?.payment_status || "").toLowerCase();
    const paymentStatusLabel =
        paymentStatuses[paymentStatusKey] ??
        (paymentMethodKey === "cash" ? "Lunas" : "Menunggu Pembayaran");
    const paymentStatusColor =
        paymentStatusKey === "paid"
            ? "text-emerald-600"
            : paymentStatusKey === "failed"
            ? "text-rose-600"
            : "text-amber-600";

    const isNonCash = paymentMethodKey !== "cash";
    const showPaymentLink = isNonCash && transaction.payment_url;

    return (
        <>
            <Head title="Invoice Penjualan" />

            <div className="min-h-screen bg-gray-100 py-8 px-4 print:bg-white print:p-0">
                <div className="max-w-3xl mx-auto space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                        <Link
                            href={route("transactions.index")}
                            className="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-600 transition hover:border-gray-300 hover:text-gray-900"
                        >
                            <IconArrowLeft size={16} />
                            Kembali ke kasir
                        </Link>

                        {showPaymentLink && (
                            <a
                                href={transaction.payment_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 rounded-full border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50"
                            >
                                <IconExternalLink size={16} />
                                Buka pembayaran
                            </a>
                        )}

                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="inline-flex items-center gap-2 rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500"
                        >
                            <IconPrinter size={16} />
                            Cetak invoice
                        </button>
                    </div>

                    <div className="rounded-2xl border border-gray-200 bg-white shadow-xl print:border-gray-400 print:shadow-none">
                        <div className="flex flex-wrap items-start justify-between gap-4 border-b border-gray-100 bg-gray-50 px-6 py-5">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Invoice
                                </p>
                                <p className="text-2xl font-bold text-gray-900">
                                    {transaction.invoice}
                                </p>
                                <p className="text-sm text-gray-500">
                                    Tanggal dibuat -{" "}
                                    {formatDateTime(transaction.created_at)}
                                </p>
                            </div>

                            <div className="text-right text-sm text-gray-600">
                                <p className="font-semibold text-gray-800">
                                    Kasir
                                </p>
                                <p>{transaction.cashier?.name ?? "-"}</p>
                                <p className="mt-3 font-semibold text-gray-800">
                                    Metode Pembayaran
                                </p>
                                <p>{paymentMethodLabel}</p>
                                <p className={`text-xs ${paymentStatusColor}`}>
                                    {paymentStatusLabel}
                                </p>
                                {transaction.payment_reference && (
                                    <p className="text-xs text-gray-400">
                                        Ref: {transaction.payment_reference}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-4 border-b border-gray-100 px-6 py-5 md:grid-cols-2">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Pelanggan
                                </p>
                                <p className="mt-1 text-base font-semibold text-gray-900">
                                    {transaction.customer?.name ?? "Umum"}
                                </p>
                                {transaction.customer?.address && (
                                    <p className="text-sm text-gray-600">
                                        {transaction.customer.address}
                                    </p>
                                )}
                                {transaction.customer?.phone && (
                                    <p className="text-sm text-gray-600">
                                        {transaction.customer.phone}
                                    </p>
                                )}
                            </div>
                            <div className="md:text-right">
                                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Ringkasan Pembayaran
                                </p>
                                <dl className="mt-2 space-y-1 text-sm text-gray-600">
                                    <div className="flex items-center justify-between md:justify-end md:gap-6">
                                        <dt>Subtotal</dt>
                                        <dd className="font-semibold text-gray-900">
                                            {formatPrice(
                                                transaction.grand_total +
                                                    (transaction.discount || 0)
                                            )}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between md:justify-end md:gap-6">
                                        <dt>Diskon</dt>
                                        <dd>{formatPrice(transaction.discount)}</dd>
                                    </div>
                                    <div className="flex items-center justify-between md:justify-end md:gap-6">
                                        <dt>Total Bayar</dt>
                                        <dd className="font-semibold text-gray-900">
                                            {formatPrice(transaction.grand_total)}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div className="px-6 py-5">
                            <div className="overflow-x-auto rounded-2xl border border-gray-100">
                                <table className="min-w-full divide-y divide-gray-100 text-sm">
                                    <thead className="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-semibold">
                                                #
                                            </th>
                                            <th className="px-4 py-3 text-left font-semibold">
                                                Produk
                                            </th>
                                            <th className="px-4 py-3 text-right font-semibold">
                                                Harga
                                            </th>
                                            <th className="px-4 py-3 text-center font-semibold">
                                                Qty
                                            </th>
                                            <th className="px-4 py-3 text-right font-semibold">
                                                Subtotal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-50 text-gray-700">
                                        {items.map((item, index) => {
                                            const quantity = Number(item.qty) || 1;
                                            const subtotal = Number(item.price) || 0;
                                            const unitPrice = subtotal / quantity;

                                            return (
                                                <tr key={item.id ?? index}>
                                                    <td className="px-4 py-3">
                                                        {index + 1}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <p className="font-semibold text-gray-900">
                                                            {item.product?.title}
                                                        </p>
                                                        {item.product?.barcode && (
                                                            <p className="text-xs text-gray-500">
                                                                {item.product.barcode}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        {formatPrice(unitPrice)}
                                                    </td>
                                                    <td className="px-4 py-3 text-center">
                                                        {quantity}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-semibold text-gray-900">
                                                        {formatPrice(subtotal)}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            <div className="mt-6 grid gap-4 rounded-2xl border border-gray-100 bg-gray-50 p-4 text-sm text-gray-600 md:grid-cols-2">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Pembayaran
                                </p>
                                <dl className="mt-2 space-y-1">
                                    <div className="flex items-center justify-between">
                                        <dt>Status</dt>
                                        <dd
                                            className={`font-semibold ${paymentStatusColor}`}
                                        >
                                            {paymentStatusLabel}
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt>Uang diterima</dt>
                                        <dd className="font-semibold text-gray-900">
                                            {formatPrice(transaction.cash)}
                                        </dd>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <dt>Kembalian</dt>
                                            <dd className="font-semibold text-gray-900">
                                                {formatPrice(transaction.change)}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                                <div>
                                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                        Catatan
                                    </p>
                                    <p className="mt-2 text-gray-700">
                                        {isNonCash
                                            ? "Bagikan tautan pembayaran ini kepada pelanggan dan tunggu konfirmasi sistem gateway."
                                            : "Simpan invoice ini sebagai bukti transaksi resmi. Silakan hubungi kasir jika terdapat kekeliruan."}
                                    </p>
                                </div>
                            </div>

                            <p className="mt-8 text-center text-xs uppercase tracking-[0.2em] text-gray-400">
                                Terima kasih telah berbelanja
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

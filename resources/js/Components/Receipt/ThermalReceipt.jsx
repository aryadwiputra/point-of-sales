import React from "react";

/**
 * ThermalReceipt - Receipt template optimized for thermal printers (58mm/80mm)
 *
 * Features:
 * - Monospace font for alignment
 * - Fixed width for thermal paper
 * - No colors (thermal printers are B&W)
 * - Simple lines using dashes
 * - Compact layout
 */
export default function ThermalReceipt({
    transaction,
    storeName = "TOKO ANDA",
    storeAddress = "",
    storePhone = "",
    storeEmail = "",
    storeWebsite = "",
}) {
    const formatPrice = (price = 0) => {
        return "Rp " + Number(price || 0).toLocaleString("id-ID");
    };

    const formatDate = (value) => {
        return new Date(value).toLocaleString("id-ID", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    };

    const items = transaction?.details ?? [];
    const promoDiscount = items.reduce(
        (sum, item) => sum + Number(item.discount_total || 0),
        0
    );
    const loyaltyDiscount = Number(transaction?.loyalty_discount_total || 0);
    const voucherDiscount = Number(
        transaction?.customer_voucher_discount || 0
    );

    // Calculate totals
    const subtotal =
        (transaction?.grand_total || 0) +
        (transaction?.discount || 0) -
        (transaction?.shipping_cost || 0) -
        (transaction?.tax_total || 0) +
        promoDiscount +
        loyaltyDiscount +
        voucherDiscount;
    const discount = transaction?.discount || 0;
    const total = transaction?.grand_total || 0;
    const shipping = transaction?.shipping_cost || 0;
    const cash = transaction?.cash || 0;
    const change = transaction?.change || 0;

    const paymentLabels = {
        cash: "TUNAI",
        midtrans: "MIDTRANS",
        xendit: "XENDIT",
    };
    const paymentMethod =
        paymentLabels[transaction?.payment_method?.toLowerCase()] || "TUNAI";

    // Line separator
    const line = "=".repeat(32);
    const dashLine = "-".repeat(32);

    const SimpleBarcode = ({ value }) => {
        const bars = (value || "").split("").map((char, idx) => {
            const weight = (char.charCodeAt(0) + idx * 17) % 5;
            return 2 + weight;
        });

        return (
            <div className="flex items-end justify-center gap-[2px] mt-2">
                {bars.map((w, i) => (
                    <span
                        key={i}
                        style={{ width: `${w}px` }}
                        className="h-10 bg-black block"
                    />
                ))}
            </div>
        );
    };

    return (
        <div
            className="thermal-receipt font-mono text-xs leading-tight"
            style={{ width: "80mm", padding: "4mm" }}
        >
            {/* Store Header */}
            <div className="text-center mb-2">
                <p className="text-sm font-bold">{storeName}</p>
                {storeAddress && <p className="text-xs">{storeAddress}</p>}
                {storePhone && <p className="text-xs">Telp: {storePhone}</p>}
                {storeEmail && <p className="text-xs">Email: {storeEmail}</p>}
                {storeWebsite && <p className="text-xs">{storeWebsite}</p>}
            </div>

            <pre className="whitespace-pre-wrap">{line}</pre>

            {/* Invoice Info */}
            <div className="my-1">
                <div className="flex justify-between">
                    <span>No:</span>
                    <span>{transaction?.invoice}</span>
                </div>
                <div className="flex justify-between">
                    <span>Tgl:</span>
                    <span>{formatDate(transaction?.created_at)}</span>
                </div>
                <div className="flex justify-between">
                    <span>Kasir:</span>
                    <span>{transaction?.cashier?.name || "-"}</span>
                </div>
                <div className="flex justify-between">
                    <span>Pelanggan:</span>
                    <span>{transaction?.customer?.name || "Umum"}</span>
                </div>
            </div>

            <pre className="whitespace-pre-wrap">{line}</pre>

            {/* Items */}
            <div className="my-1">
                {items.map((item, index) => {
                    const qty = Number(item.qty) || 1;
                    const itemTotal = Number(item.price) || 0;
                    const unitPrice =
                        Number(item.unit_price || 0) || itemTotal / qty;
                    const baseUnitPrice =
                        Number(item.base_unit_price || 0) || unitPrice;

                    return (
                        <div key={item.id || index} className="mb-1">
                            <p className="font-medium truncate">
                                {item.product?.title}
                            </p>
                            {Number(item.discount_total || 0) > 0 &&
                                baseUnitPrice > unitPrice && (
                                    <div className="flex justify-between text-[10px] text-slate-500">
                                        <span>
                                            Promo:{" "}
                                            {item.pricing_group_label ||
                                                item.pricing_rule_name ||
                                                "Promo"}
                                        </span>
                                        <span>{formatPrice(baseUnitPrice)}</span>
                                    </div>
                                )}
                            <div className="flex justify-between">
                                <span>
                                    {qty}x @ {formatPrice(unitPrice)}
                                </span>
                                <span>{formatPrice(itemTotal)}</span>
                            </div>
                        </div>
                    );
                })}
            </div>

            <pre className="whitespace-pre-wrap">{dashLine}</pre>

            {/* Totals */}
            <div className="my-1">
                <div className="flex justify-between">
                    <span>Subtotal</span>
                    <span>{formatPrice(subtotal)}</span>
                </div>
                {promoDiscount > 0 && (
                    <div className="flex justify-between">
                        <span>Promo</span>
                        <span>-{formatPrice(promoDiscount)}</span>
                    </div>
                )}
                {discount > 0 && (
                    <div className="flex justify-between">
                        <span>Diskon Manual</span>
                        <span>-{formatPrice(discount)}</span>
                    </div>
                )}
                {voucherDiscount > 0 && (
                    <div className="flex justify-between">
                        <span>Voucher</span>
                        <span>-{formatPrice(voucherDiscount)}</span>
                    </div>
                )}
                {loyaltyDiscount > 0 && (
                    <div className="flex justify-between">
                        <span>Redeem Poin</span>
                        <span>-{formatPrice(loyaltyDiscount)}</span>
                    </div>
                )}
                {shipping > 0 && (
                    <div className="flex justify-between">
                        <span>Ongkir</span>
                        <span>{formatPrice(shipping)}</span>
                    </div>
                )}
                {Number(transaction?.tax_total || 0) > 0 && (
                    <div className="flex justify-between">
                        <span>PPN {Number(transaction?.tax_rate || 11).toFixed(0)}%</span>
                        <span>{formatPrice(transaction?.tax_total)}</span>
                    </div>
                )}
                <div className="flex justify-between font-bold text-sm">
                    <span>TOTAL</span>
                    <span>{formatPrice(total)}</span>
                </div>
            </div>

            <pre className="whitespace-pre-wrap">{dashLine}</pre>

            {/* Payment Info */}
            <div className="my-1">
                <div className="flex justify-between">
                    <span>Bayar ({paymentMethod})</span>
                    <span>{formatPrice(cash)}</span>
                </div>
                {change > 0 && (
                    <div className="flex justify-between font-bold">
                        <span>Kembali</span>
                        <span>{formatPrice(change)}</span>
                    </div>
                )}
            </div>

            <pre className="whitespace-pre-wrap">{line}</pre>

            {/* Footer */}
            <div className="text-center mt-2">
                <p className="text-xs">Terima kasih</p>
                <p className="text-xs">Barang yang sudah dibeli</p>
                <p className="text-xs">tidak dapat ditukar/dikembalikan</p>
                <p className="text-xs mt-1">#{transaction?.invoice}</p>
                <SimpleBarcode value={transaction?.invoice} />
            </div>

            {/* Print-specific styles */}
            <style>{`
                @media print {
                    .thermal-receipt {
                        width: 80mm !important;
                        margin: 0 !important;
                        padding: 2mm !important;
                        font-size: 10pt !important;
                    }
                    @page {
                        size: 80mm auto;
                        margin: 0;
                    }
                }
            `}</style>
        </div>
    );
}

/**
 * Compact Receipt for 58mm printers
 */
export function ThermalReceipt58mm({
    transaction,
    storeName = "TOKO",
    storePhone = "",
    storeEmail = "",
    storeWebsite = "",
}) {
    const formatPrice = (price = 0) => {
        return "Rp" + Number(price || 0).toLocaleString("id-ID");
    };

    const formatTime = (value) => {
        return new Date(value).toLocaleString("id-ID", {
            day: "2-digit",
            month: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
        });
    };

    const items = transaction?.details ?? [];
    const promoDiscount = items.reduce(
        (sum, item) => sum + Number(item.discount_total || 0),
        0
    );
    const loyaltyDiscount = Number(transaction?.loyalty_discount_total || 0);
    const voucherDiscount = Number(
        transaction?.customer_voucher_discount || 0
    );
    const line = "-".repeat(24);

    const SimpleBarcode = ({ value }) => {
        const bars = (value || "").split("").map((char, idx) => {
            const weight = (char.charCodeAt(0) + idx * 17) % 4;
            return 2 + weight;
        });

        return (
            <div className="flex items-end gap-[2px] mt-2 justify-center">
                {bars.map((w, i) => (
                    <span
                        key={i}
                        style={{ width: `${w}px` }}
                        className="h-8 bg-black block"
                    />
                ))}
            </div>
        );
    };

    return (
        <div
            className="thermal-receipt-58 font-mono text-xs"
            style={{ width: "58mm", padding: "2mm" }}
        >
            <div className="text-center">
                <p className="font-bold">{storeName}</p>
                {storePhone && <p>{storePhone}</p>}
                {storeEmail && <p className="text-[10px]">{storeEmail}</p>}
                {storeWebsite && <p className="text-[10px]">{storeWebsite}</p>}
            </div>

            <pre>{line}</pre>
            <p>#{transaction?.invoice}</p>
            <p>{formatTime(transaction?.created_at)}</p>
            <pre>{line}</pre>

            {items.map((item, i) => {
                const qty = Number(item.qty) || 1;
                const unitPrice =
                    Number(item.unit_price || 0) ||
                    Number(item.price || 0) / qty;
                const baseUnitPrice =
                    Number(item.base_unit_price || 0) || unitPrice;

                return (
                    <div key={i} className="mb-1">
                        <p className="truncate">{item.product?.title}</p>
                        {Number(item.discount_total || 0) > 0 &&
                            baseUnitPrice > unitPrice && (
                                <div className="flex justify-between text-[9px] text-slate-500">
                                    <span>Promo</span>
                                    <span>{formatPrice(baseUnitPrice)}</span>
                                </div>
                            )}
                        <div className="flex justify-between">
                            <span>
                                {item.qty}x @ {formatPrice(unitPrice)}
                            </span>
                            <span>{formatPrice(item.price)}</span>
                        </div>
                    </div>
                );
            })}

            <pre>{line}</pre>
            <div className="flex justify-between">
                <span>Subtotal</span>
                <span>
                    {formatPrice(
                        (transaction?.grand_total || 0) +
                            (transaction?.discount || 0) -
                            (transaction?.shipping_cost || 0) -
                            (transaction?.tax_total || 0) +
                            promoDiscount +
                            loyaltyDiscount +
                            voucherDiscount
                    )}
                </span>
            </div>
            {promoDiscount > 0 && (
                <div className="flex justify-between">
                    <span>Promo</span>
                    <span>-{formatPrice(promoDiscount)}</span>
                </div>
            )}
            {Number(transaction?.discount || 0) > 0 && (
                <div className="flex justify-between">
                    <span>Disc</span>
                    <span>-{formatPrice(transaction?.discount)}</span>
                </div>
            )}
            {voucherDiscount > 0 && (
                <div className="flex justify-between">
                    <span>Voucher</span>
                    <span>-{formatPrice(voucherDiscount)}</span>
                </div>
            )}
            {loyaltyDiscount > 0 && (
                <div className="flex justify-between">
                    <span>Poin</span>
                    <span>-{formatPrice(loyaltyDiscount)}</span>
                </div>
            )}
            {Number(transaction?.shipping_cost || 0) > 0 && (
                <div className="flex justify-between">
                    <span>Ongkir</span>
                    <span>{formatPrice(transaction?.shipping_cost)}</span>
                </div>
            )}
            {Number(transaction?.tax_total || 0) > 0 && (
                <div className="flex justify-between">
                    <span>PPN</span>
                    <span>{formatPrice(transaction?.tax_total)}</span>
                </div>
            )}
            <div className="flex justify-between font-bold">
                <span>TOTAL</span>
                <span>{formatPrice(transaction?.grand_total)}</span>
            </div>
            <div className="flex justify-between">
                <span>Bayar</span>
                <span>{formatPrice(transaction?.cash)}</span>
            </div>
            <div className="flex justify-between">
                <span>Kembali</span>
                <span>{formatPrice(transaction?.change)}</span>
            </div>
            <pre>{line}</pre>
            <p className="text-center">Terima kasih!</p>
            <SimpleBarcode value={transaction?.invoice} />

            <style>{`
                @media print {
                    .thermal-receipt-58 {
                        width: 58mm !important;
                        font-size: 9pt !important;
                    }
                    @page { size: 58mm auto; margin: 0; }
                }
            `}</style>
        </div>
    );
}

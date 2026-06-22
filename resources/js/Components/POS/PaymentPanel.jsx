import React, { useMemo } from "react";
import {
    IconCash,
    IconCreditCard,
    IconReceipt,
    IconArrowRight,
    IconCheck,
    IconAlertCircle,
    IconBuildingBank,
} from "@tabler/icons-react";

const formatPrice = (value = 0) =>
    Number(value || 0).toLocaleString("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    });

// Quick Amount Button
function QuickAmountButton({ amount, onClick, isSelected }) {
    return (
        <button
            type="button"
            onClick={() => onClick(amount)}
            className={`
                flex-1 py-3 px-2 rounded-xl text-sm font-semibold
                transition-all duration-200 min-h-touch
                ${
                    isSelected
                        ? "bg-primary-500 text-white shadow-md shadow-primary-500/30"
                        : "bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700"
                }
            `}
        >
            {formatPrice(amount)}
        </button>
    );
}

// Payment Method Card
function PaymentMethodCard({ method, isSelected, onClick }) {
    const getIcon = () => {
        if (method.value === "cash") return IconCash;
        if (method.value === "bank_transfer") return IconBuildingBank;
        return IconCreditCard;
    };
    const IconComponent = getIcon();

    return (
        <button
            type="button"
            onClick={() => onClick(method.value)}
            className={`
                w-full p-3 rounded-xl text-left transition-all duration-200
                border-2 flex items-center gap-3
                ${
                    isSelected
                        ? "border-primary-500 bg-primary-50 dark:bg-primary-950/30"
                        : "border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 bg-white dark:bg-slate-900"
                }
            `}
        >
            <div
                className={`
                w-10 h-10 rounded-lg flex items-center justify-center
                ${
                    isSelected
                        ? "bg-primary-500 text-white"
                        : "bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400"
                }
            `}
            >
                <IconComponent size={20} />
            </div>
            <div className="flex-1">
                <p
                    className={`text-sm font-semibold ${
                        isSelected
                            ? "text-primary-700 dark:text-primary-300"
                            : "text-slate-800 dark:text-slate-200"
                    }`}
                >
                    {method.label}
                </p>
                {method.description && (
                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {method.description}
                    </p>
                )}
            </div>
            {isSelected && (
                <div className="w-6 h-6 rounded-full bg-primary-500 text-white flex items-center justify-center">
                    <IconCheck size={14} />
                </div>
            )}
        </button>
    );
}

// Main PaymentPanel Component
export default function PaymentPanel({
    subtotal = 0,
    promoDiscount = 0,
    voucherDiscount = 0,
    loyaltyDiscount = 0,
    discount = 0,
    taxTotal = 0,
    discountInput = "",
    onDiscountChange,
    redeemPointsInput = "",
    onRedeemPointsChange,
    availablePoints = 0,
    selectedVoucherId = "",
    voucherOptions = [],
    onVoucherChange,
    cash = 0,
    cashInput = "",
    onCashChange,
    paymentMethod = "cash",
    onPaymentMethodChange,
    paymentOptions = [],
    bankAccounts = [],
    selectedBankAccount = null,
    onBankAccountChange,
    onSubmit,
    isSubmitting = false,
    hasItems = false,
    selectedCustomer = null,
    className = "",
}) {
    // Quick amount options
    const quickAmounts = [10000, 20000, 50000, 100000];

    // Calculations
    const payable = Math.max(subtotal - discount, 0);
    const isCashPayment = paymentMethod === "cash";
    const isBankTransfer = paymentMethod === "bank_transfer";
    const change = isCashPayment ? Math.max(cash - payable, 0) : 0;
    const remaining = isCashPayment ? Math.max(payable - cash, 0) : 0;

    // Validation
    const canSubmit =
        hasItems &&
        selectedCustomer &&
        (isCashPayment ? cash >= payable : true) &&
        (isBankTransfer ? selectedBankAccount !== null : true) &&
        !isSubmitting;

    // Submit label
    const submitLabel = useMemo(() => {
        if (!hasItems) return "Keranjang Kosong";
        if (!selectedCustomer) return "Pilih Pelanggan";
        if (isCashPayment && remaining > 0)
            return `Kurang ${formatPrice(remaining)}`;
        return "Selesaikan Transaksi";
    }, [hasItems, selectedCustomer, isCashPayment, remaining]);

    return (
        <div className={`flex flex-col h-full ${className}`}>
            {/* Header */}
            <div className="flex items-center gap-2 p-4 border-b border-slate-200 dark:border-slate-800">
                <IconReceipt
                    size={20}
                    className="text-slate-600 dark:text-slate-400"
                />
                <h2 className="text-base font-semibold text-slate-800 dark:text-white">
                    Pembayaran
                </h2>
            </div>

            {/* Content */}
            <div className="flex-1 overflow-y-auto p-4 space-y-5 scrollbar-thin">
                {/* Summary */}
                <div className="space-y-2">
                    <div className="flex justify-between text-sm">
                        <span className="text-slate-500 dark:text-slate-400">
                            Subtotal
                        </span>
                        <span className="font-medium text-slate-800 dark:text-slate-200">
                            {formatPrice(subtotal)}
                        </span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-slate-500 dark:text-slate-400">
                            Promo Otomatis
                        </span>
                        <span className="font-medium text-danger-500">
                            - {formatPrice(promoDiscount)}
                        </span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-slate-500 dark:text-slate-400">
                            Voucher
                        </span>
                        <span className="font-medium text-danger-500">
                            - {formatPrice(voucherDiscount)}
                        </span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-slate-500 dark:text-slate-400">
                            Redeem Poin
                        </span>
                        <span className="font-medium text-danger-500">
                            - {formatPrice(loyaltyDiscount)}
                        </span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span className="text-slate-500 dark:text-slate-400">
                            Diskon
                        </span>
                        <span className="font-medium text-danger-500">
                            - {formatPrice(discount)}
                        </span>
                    </div>
                    {taxTotal > 0 && (
                        <div className="flex justify-between text-sm">
                            <span className="text-slate-500 dark:text-slate-400">
                                PPN
                            </span>
                            <span className="font-medium text-slate-800 dark:text-slate-200">
                                {formatPrice(taxTotal)}
                            </span>
                        </div>
                    )}
                    <div className="h-px bg-slate-200 dark:bg-slate-700 my-2" />
                    <div className="flex justify-between">
                        <span className="text-base font-semibold text-slate-800 dark:text-white">
                            Total
                        </span>
                        <span className="text-xl font-bold text-primary-600 dark:text-primary-400">
                            {formatPrice(payable)}
                        </span>
                    </div>
                </div>

                {selectedCustomer?.is_loyalty_member && (
                    <>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Redeem Poin
                            </label>
                            <input
                                type="text"
                                inputMode="numeric"
                                value={redeemPointsInput}
                                onChange={(e) =>
                                    onRedeemPointsChange?.(
                                        e.target.value.replace(/[^\d]/g, "")
                                    )
                                }
                                placeholder={`Maks ${availablePoints} poin`}
                                className="w-full h-11 px-4 rounded-xl border border-slate-200 dark:border-slate-700
                                    bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200
                                    focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500
                                    transition-all text-base"
                            />
                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Saldo tersedia: {availablePoints} poin
                            </p>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Voucher Customer
                            </label>
                            <select
                                value={selectedVoucherId}
                                onChange={(e) =>
                                    onVoucherChange?.(e.target.value)
                                }
                                className="w-full h-11 px-4 rounded-xl border border-slate-200 dark:border-slate-700
                                    bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200
                                    focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500
                                    transition-all text-base"
                            >
                                <option value="">Tanpa voucher</option>
                                {voucherOptions.map((voucher) => (
                                    <option key={voucher.id} value={voucher.id}>
                                        {voucher.code} - {voucher.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </>
                )}

                {/* Discount Input */}
                <div>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Diskon (Rp)
                    </label>
                    <input
                        type="text"
                        inputMode="numeric"
                        value={discountInput}
                        onChange={(e) =>
                            onDiscountChange(
                                e.target.value.replace(/[^\d]/g, "")
                            )
                        }
                        placeholder="0"
                        className="w-full h-11 px-4 rounded-xl border border-slate-200 dark:border-slate-700
                            bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200
                            focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500
                            transition-all text-base"
                    />
                </div>

                {/* Payment Method */}
                <div>
                    <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Metode Pembayaran
                    </label>
                    <div className="space-y-2">
                        {paymentOptions.map((method) => (
                            <PaymentMethodCard
                                key={method.value}
                                method={method}
                                isSelected={paymentMethod === method.value}
                                onClick={onPaymentMethodChange}
                            />
                        ))}
                    </div>
                </div>

                {/* Bank Selector (only for bank_transfer) */}
                {paymentMethod === "bank_transfer" &&
                    bankAccounts.length > 0 && (
                        <div>
                            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                Pilih Rekening Tujuan
                            </label>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                {bankAccounts.map((bank) => {
                                    const isActive =
                                        selectedBankAccount?.id === bank.id;
                                    return (
                                        <button
                                            type="button"
                                            key={bank.id}
                                            onClick={() =>
                                                onBankAccountChange?.(bank)
                                            }
                                            className={`flex items-center gap-3 p-3 rounded-xl border-2 transition-colors text-left ${
                                                isActive
                                                    ? "border-primary-500 bg-primary-50 dark:bg-primary-950/30"
                                                    : "border-slate-200 dark:border-slate-700 hover:border-primary-200 dark:hover:border-primary-800"
                                            }`}
                                        >
                                            <div className="w-12 h-12 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-center overflow-hidden">
                                                {bank.logo_url ? (
                                                    <img
                                                        src={bank.logo_url}
                                                        alt={bank.bank_name}
                                                        className="max-w-full max-h-full object-contain"
                                                    />
                                                ) : (
                                                    <IconBuildingBank
                                                        size={22}
                                                        className="text-slate-500"
                                                    />
                                                )}
                                            </div>
                                            <div className="flex-1">
                                                <p className="text-sm font-semibold text-slate-800 dark:text-white">
                                                    {bank.bank_name}
                                                </p>
                                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                                    {bank.account_number}
                                                </p>
                                                <p className="text-xs text-slate-500 dark:text-slate-500">
                                                    a.n. {bank.account_name}
                                                </p>
                                            </div>
                                            {isActive && (
                                                <span className="text-primary-600 text-xs font-semibold">
                                                    Dipilih
                                                </span>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                {/* Cash Input (only for cash payment) */}
                {isCashPayment && (
                    <div>
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            Jumlah Bayar (Rp)
                        </label>

                        {/* Quick Amounts */}
                        <div className="flex gap-2 mb-3">
                            {quickAmounts.map((amount) => (
                                <QuickAmountButton
                                    key={amount}
                                    amount={amount}
                                    onClick={(a) => onCashChange(String(a))}
                                    isSelected={cash === amount}
                                />
                            ))}
                        </div>

                        {/* Cash Input */}
                        <input
                            type="text"
                            inputMode="numeric"
                            value={cashInput}
                            onChange={(e) =>
                                onCashChange(
                                    e.target.value.replace(/[^\d]/g, "")
                                )
                            }
                            placeholder="0"
                            className="w-full h-12 px-4 rounded-xl border border-slate-200 dark:border-slate-700
                                bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200
                                focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500
                                transition-all text-lg font-semibold text-center"
                        />

                        {/* Change Display */}
                        <div className="mt-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50">
                            <div className="flex justify-between items-center">
                                <span className="text-sm text-slate-500 dark:text-slate-400">
                                    Kembalian
                                </span>
                                <span
                                    className={`text-lg font-bold ${
                                        change > 0
                                            ? "text-success-500"
                                            : "text-slate-400"
                                    }`}
                                >
                                    {formatPrice(change)}
                                </span>
                            </div>
                        </div>
                    </div>
                )}

                {/* Non-cash payment info */}
                {!isCashPayment && (
                    <div className="p-3 rounded-xl bg-warning-50 dark:bg-warning-950/30 border border-warning-200 dark:border-warning-800">
                        <div className="flex gap-2">
                            <IconAlertCircle
                                size={18}
                                className="text-warning-500 flex-shrink-0 mt-0.5"
                            />
                            <p className="text-sm text-warning-700 dark:text-warning-400">
                                Tautan pembayaran akan muncul di halaman invoice
                                setelah transaksi dibuat.
                            </p>
                        </div>
                    </div>
                )}
            </div>

            {/* Submit Button */}
            <div className="p-4 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={!canSubmit}
                    className={`
                        w-full h-14 rounded-xl text-base font-semibold
                        flex items-center justify-center gap-2
                        transition-all duration-200
                        ${
                            canSubmit
                                ? "bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 active:scale-[0.98]"
                                : "bg-slate-200 dark:bg-slate-800 text-slate-400 dark:text-slate-600 cursor-not-allowed"
                        }
                    `}
                >
                    {isSubmitting ? (
                        <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                    ) : (
                        <>
                            <span>{submitLabel}</span>
                            {canSubmit && <IconArrowRight size={20} />}
                        </>
                    )}
                </button>
            </div>
        </div>
    );
}

// Export sub-components
PaymentPanel.QuickAmountButton = QuickAmountButton;
PaymentPanel.PaymentMethodCard = PaymentMethodCard;

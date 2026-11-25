import React, { useMemo, useState } from "react";
import { router } from "@inertiajs/react";
import Modal from "@/Components/Dashboard/Modal";
import Button from "@/Components/Dashboard/Button";
import NumpadModal from "@/Components/POS/NumpadModal";
import toast from "react-hot-toast";

const formatPrice = (value = 0) =>
    Number(value || 0).toLocaleString("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    });

export default function SettleBillModal({
    show,
    bill,
    customers = [],
    paymentGateways = [],
    bankAccounts = [],
    defaultPaymentGateway = "cash",
    onClose,
}) {
    const [customerId, setCustomerId] = useState("");
    const [voucherId, setVoucherId] = useState("");
    const [discount, setDiscount] = useState("");
    const [redeemPoints, setRedeemPoints] = useState("");
    const [paymentMethod, setPaymentMethod] = useState(defaultPaymentGateway);
    const [cash, setCash] = useState("");
    const [bankAccountId, setBankAccountId] = useState("");
    const [payLater, setPayLater] = useState(false);
    const [dueDate, setDueDate] = useState("");
    const [splitMode, setSplitMode] = useState(false);
    const [tenders, setTenders] = useState([
        { method: "cash", amount: "", cash_received: "", bank_account_id: "" },
        { method: "bank_transfer", amount: "", cash_received: "", bank_account_id: "" },
    ]);
    const [numpadOpen, setNumpadOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    const customer = customers.find((candidate) => String(candidate.id) === String(customerId));
    const vouchers = customer?.vouchers ?? [];
    const subtotal = Number(bill?.subtotal ?? 0);
    const manualDiscount = Math.max(0, Number(discount || 0));
    const pointsDiscount = Math.max(0, Number(redeemPoints || 0)) * 100;
    const estimatedTotal = Math.max(0, subtotal - manualDiscount - pointsDiscount);
    const cashValue = Number(cash || 0);
    const change = Math.max(0, cashValue - estimatedTotal);
    const activeTenders = tenders.filter((tender) => tender.method && Number(tender.amount) > 0);

    const paymentOptions = useMemo(() => {
        const gateways = (paymentGateways ?? []).filter(
            (gateway) => gateway?.value && gateway.value.toLowerCase() !== "cash"
        );
        return [
            { value: "cash", label: "Tunai" },
            ...gateways.map((gateway) => ({ value: gateway.value, label: gateway.label ?? gateway.value })),
        ];
    }, [paymentGateways]);

    const updateTender = (index, patch) => {
        setTenders((current) =>
            current.map((tender, position) => (position === index ? { ...tender, ...patch } : tender))
        );
    };

    const submit = () => {
        if (!bill) return;
        if (payLater && (!customerId || !dueDate)) {
            toast.error("Nota barang memerlukan pelanggan dan jatuh tempo.");
            return;
        }
        if (!payLater && !splitMode && paymentMethod === "cash" && cashValue < estimatedTotal) {
            toast.error("Uang tunai kurang dari total.");
            return;
        }

        setSubmitting(true);
        router.post(
            route("dine-bills.settle", bill.id),
            {
                customer_id: customerId || null,
                customer_voucher_id: voucherId || null,
                discount: manualDiscount,
                redeem_points: Number(redeemPoints || 0),
                pay_later: payLater,
                due_date: payLater ? dueDate : null,
                cash: splitMode ? estimatedTotal : cashValue,
                payment_gateway: splitMode || payLater ? null : (paymentMethod === "cash" ? null : paymentMethod),
                bank_account_id: bankAccountId || null,
                tenders: splitMode
                    ? activeTenders.map((tender) => ({
                          method: tender.method,
                          amount: Number(tender.amount),
                          cash_received:
                              tender.method === "cash" ? Number(tender.cash_received || tender.amount) : null,
                          bank_account_id:
                              tender.method === "bank_transfer" ? tender.bank_account_id || null : null,
                      }))
                    : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSubmitting(false);
                    onClose();
                },
                onError: () => {
                    setSubmitting(false);
                    toast.error("Gagal membayar bill.");
                },
            }
        );
    };

    return (
        <>
            <Modal show={show} onClose={onClose} title={`Bayar Bill ${bill?.sequence ?? ""}`} maxWidth="lg">
                <div className="space-y-4">
                    <div className="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800">
                        <div className="flex justify-between">
                            <span>Subtotal</span>
                            <span>{formatPrice(subtotal)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span>Diskon</span>
                            <span>- {formatPrice(manualDiscount + pointsDiscount)}</span>
                        </div>
                        <div className="mt-1 flex justify-between font-bold">
                            <span>Estimasi Total</span>
                            <span>{formatPrice(estimatedTotal)}</span>
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium">Pelanggan</label>
                        <select
                            value={customerId}
                            onChange={(event) => {
                                setCustomerId(event.target.value);
                                setVoucherId("");
                                setRedeemPoints("");
                            }}
                            className="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                        >
                            <option value="">Umum</option>
                            {customers.map((candidate) => (
                                <option key={candidate.id} value={candidate.id}>
                                    {candidate.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {customer?.is_loyalty_member && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium">Poin ({customer.loyalty_points ?? 0})</label>
                                <input
                                    type="number"
                                    min="0"
                                    value={redeemPoints}
                                    onChange={(event) => setRedeemPoints(event.target.value)}
                                    className="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium">Voucher</label>
                                <select
                                    value={voucherId}
                                    onChange={(event) => setVoucherId(event.target.value)}
                                    className="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                                >
                                    <option value="">Tanpa voucher</option>
                                    {vouchers.map((voucher) => (
                                        <option key={voucher.id} value={voucher.id}>
                                            {voucher.code} - {voucher.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    )}

                    <div>
                        <label className="mb-1 block text-sm font-medium">Diskon Manual (Rp)</label>
                        <input
                            type="number"
                            min="0"
                            value={discount}
                            onChange={(event) => setDiscount(event.target.value)}
                            className="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                        />
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={payLater}
                            onChange={(event) => setPayLater(event.target.checked)}
                        />
                        Bayar belakangan (nota barang)
                    </label>

                    {payLater && (
                        <div>
                            <label className="mb-1 block text-sm font-medium">Jatuh Tempo</label>
                            <input
                                type="date"
                                value={dueDate}
                                onChange={(event) => setDueDate(event.target.value)}
                                className="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                            />
                        </div>
                    )}

                    {!payLater && (
                        <>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={splitMode}
                                    onChange={(event) => setSplitMode(event.target.checked)}
                                />
                                Split pembayaran (maks 2)
                            </label>

                            {!splitMode ? (
                                <>
                                    <div>
                                        <label className="mb-1 block text-sm font-medium">Metode</label>
                                        <select
                                            value={paymentMethod}
                                            onChange={(event) => setPaymentMethod(event.target.value)}
                                            className="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                                        >
                                            {paymentOptions.map((option) => (
                                                <option key={option.value} value={option.value}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    {paymentMethod === "cash" ? (
                                        <div>
                                            <label className="mb-1 block text-sm font-medium">Uang Tunai</label>
                                            <button
                                                type="button"
                                                onClick={() => setNumpadOpen(true)}
                                                className="w-full rounded-xl border border-slate-200 px-3 py-3 text-left text-lg font-semibold dark:border-slate-700 dark:bg-slate-900"
                                            >
                                                {formatPrice(cashValue)}
                                            </button>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Kembalian: {formatPrice(change)}
                                            </p>
                                        </div>
                                    ) : paymentMethod === "bank_transfer" ? (
                                        <div>
                                            <label className="mb-1 block text-sm font-medium">Rekening Tujuan</label>
                                            <select
                                                value={bankAccountId}
                                                onChange={(event) => setBankAccountId(event.target.value)}
                                                className="w-full rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                                            >
                                                <option value="">Pilih rekening</option>
                                                {bankAccounts.map((bank) => (
                                                    <option key={bank.id} value={bank.id}>
                                                        {bank.bank_name} - {bank.account_number}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    ) : null}
                                </>
                            ) : (
                                <div className="space-y-3">
                                    {tenders.map((tender, index) => (
                                        <div
                                            key={index}
                                            className="grid gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700 sm:grid-cols-3"
                                        >
                                            <select
                                                value={tender.method}
                                                onChange={(event) => updateTender(index, { method: event.target.value })}
                                                className="rounded-lg border border-slate-200 px-2 py-2 dark:border-slate-700 dark:bg-slate-900"
                                            >
                                                {paymentOptions.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <input
                                                type="number"
                                                placeholder="Nominal"
                                                value={tender.amount}
                                                onChange={(event) => updateTender(index, { amount: event.target.value })}
                                                className="rounded-lg border border-slate-200 px-2 py-2 dark:border-slate-700 dark:bg-slate-900"
                                            />
                                            {tender.method === "bank_transfer" ? (
                                                <select
                                                    value={tender.bank_account_id}
                                                    onChange={(event) =>
                                                        updateTender(index, { bank_account_id: event.target.value })
                                                    }
                                                    className="rounded-lg border border-slate-200 px-2 py-2 dark:border-slate-700 dark:bg-slate-900"
                                                >
                                                    <option value="">Rekening</option>
                                                    {bankAccounts.map((bank) => (
                                                        <option key={bank.id} value={bank.id}>
                                                            {bank.bank_name}
                                                        </option>
                                                    ))}
                                                </select>
                                            ) : (
                                                <input
                                                    type="number"
                                                    placeholder="Uang diterima"
                                                    value={tender.cash_received}
                                                    onChange={(event) =>
                                                        updateTender(index, { cash_received: event.target.value })
                                                    }
                                                    className="rounded-lg border border-slate-200 px-2 py-2 dark:border-slate-700 dark:bg-slate-900"
                                                />
                                            )}
                                        </div>
                                    ))}
                                    <p className="text-xs text-slate-500">
                                        Total tender: {formatPrice(activeTenders.reduce((sum, tender) => sum + Number(tender.amount || 0), 0))}
                                    </p>
                                </div>
                            )}
                        </>
                    )}

                    <div className="flex justify-end gap-3 pt-2">
                        <Button type="button" label="Batal" onClick={onClose} />
                        <Button
                            type="button"
                            label="Bayar"
                            onClick={submit}
                            processing={submitting}
                            className="bg-primary-500 hover:bg-primary-600 text-white"
                        />
                    </div>
                </div>
            </Modal>

            <NumpadModal
                isOpen={numpadOpen}
                onClose={() => setNumpadOpen(false)}
                onConfirm={(value) => setCash(String(value))}
                title="Uang Tunai"
                initialValue={cashValue}
                isCurrency
            />
        </>
    );
}
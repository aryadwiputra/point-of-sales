import React, { useMemo, useState } from "react";
import { router } from "@inertiajs/react";
import Modal from "@/Components/Dashboard/Modal";
import Button from "@/Components/Dashboard/Button";
import toast from "react-hot-toast";

const formatPrice = (value = 0) =>
    Number(value || 0).toLocaleString("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    });

export default function SplitBillModal({ show, bill, onClose }) {
    const [selected, setSelected] = useState([]);
    const items = useMemo(
        () => (bill?.items ?? []).filter((item) => !item.voided_at),
        [bill]
    );

    const toggle = (itemId) => {
        setSelected((current) =>
            current.includes(itemId)
                ? current.filter((id) => id !== itemId)
                : [...current, itemId]
        );
    };

    const total = items
        .filter((item) => selected.includes(item.id))
        .reduce((sum, item) => sum + Number(item.price) * Number(item.qty), 0);

    const submit = () => {
        if (selected.length === 0) {
            toast.error("Pilih minimal satu item.");
            return;
        }

        router.post(
            route("dine-bills.split", bill.id),
            { item_ids: selected },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Bill berhasil dipisah.");
                    setSelected([]);
                    onClose();
                },
                onError: () => toast.error("Gagal memisah bill."),
            }
        );
    };

    return (
        <Modal show={show} onClose={onClose} title={`Split Bill ${bill?.sequence ?? ""}`} maxWidth="lg">
            <div className="space-y-2">
                {items.map((item) => (
                    <label
                        key={item.id}
                        className="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                    >
                        <span className="flex items-center gap-3">
                            <input
                                type="checkbox"
                                checked={selected.includes(item.id)}
                                onChange={() => toggle(item.id)}
                                className="h-4 w-4 rounded border-slate-300 text-primary-500"
                            />
                            <span>
                                <span className="block text-sm font-medium text-slate-800 dark:text-slate-200">
                                    {item.product?.title ?? "Produk"}
                                </span>
                                <span className="block text-xs text-slate-500">
                                    {item.qty} × {formatPrice(item.price)}
                                </span>
                            </span>
                        </span>
                        <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">
                            {formatPrice(Number(item.price) * Number(item.qty))}
                        </span>
                    </label>
                ))}
            </div>

            <div className="mt-4 flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-800">
                <span className="text-sm text-slate-500">Total pilihan</span>
                <span className="text-lg font-bold text-slate-900 dark:text-white">{formatPrice(total)}</span>
            </div>

            <div className="mt-4 flex justify-end gap-3">
                <Button type="button" label="Batal" onClick={onClose} />
                <Button
                    type="button"
                    label="Pisahkan"
                    onClick={submit}
                    className="bg-primary-500 hover:bg-primary-600 text-white"
                />
            </div>
        </Modal>
    );
}
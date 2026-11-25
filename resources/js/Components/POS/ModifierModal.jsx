import React, { useEffect, useMemo, useState } from "react";
import { IconX } from "@tabler/icons-react";

const formatPrice = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(Number(value) || 0);

const groupsFor = (product) => product?.modifier_groups || [];

export default function ModifierModal({
    show,
    product,
    unit,
    initialModifierIds = [],
    initialNote = null,
    onClose,
    onConfirm,
}) {
    const groups = useMemo(() => groupsFor(product), [product]);
    const [selected, setSelected] = useState({});
    const [note, setNote] = useState("");

    useEffect(() => {
        if (!show || !product) return;

        const initial = {};
        const preselected = initialModifierIds || [];

        groups.forEach((group) => {
            const options = group.options ?? group.modifiers ?? [];
            const provided = preselected.filter((id) =>
                options.some((option) => option.id === id)
            );

            if (provided.length > 0) {
                initial[group.id] =
                    group.selection_type === "single"
                        ? provided.slice(0, 1)
                        : provided;
                return;
            }

            const defaults = options
                .filter((option) => option.is_default)
                .map((option) => option.id);
            initial[group.id] = defaults.slice(
                0,
                group.selection_type === "single" ? 1 : options.length
            );
        });

        setSelected(initial);
        setNote(initialNote || "");
    }, [show, product, groups, initialModifierIds, initialNote]);

    if (!show || !product) return null;

    const basePrice = Number(unit?.sell_price ?? product.sell_price ?? 0);
    const selectedIds = Object.values(selected).flat();
    const delta = selectedIds.reduce((total, id) => {
        const option = groups
            .flatMap((group) => group.options ?? group.modifiers ?? [])
            .find((candidate) => candidate.id === id);

        return total + Number(option?.price_delta || 0);
    }, 0);

    const isValid = groups.every((group) => {
        const count = (selected[group.id] || []).length;
        const min = Number(group.min_select || 0);
        const max = group.selection_type === "single" ? 1 : group.max_select;

        return count >= min && (max === null || max === undefined || count <= Number(max));
    });

    const toggle = (group, optionId) => {
        setSelected((current) => {
            const groupSelection = current[group.id] || [];

            if (group.selection_type === "single") {
                return { ...current, [group.id]: [optionId] };
            }

            const max = group.max_select === null ? null : Number(group.max_select);

            if (groupSelection.includes(optionId)) {
                return {
                    ...current,
                    [group.id]: groupSelection.filter((id) => id !== optionId),
                };
            }

            if (max !== null && groupSelection.length >= max) {
                return current;
            }

            return { ...current, [group.id]: [...groupSelection, optionId] };
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
            <div className="absolute inset-0 bg-slate-900/60" onClick={onClose} />

            <div className="relative w-full sm:max-w-lg max-h-[90vh] overflow-y-auto bg-white dark:bg-slate-900 rounded-t-2xl sm:rounded-2xl p-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                            {product.title}
                        </h2>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            {formatPrice(basePrice)}
                            {unit?.code ? ` · ${unit.code}` : ""}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                        aria-label="Tutup"
                    >
                        <IconX size={20} strokeWidth={1.5} />
                    </button>
                </div>

                <div className="mt-5 space-y-5">
                    {groups.map((group) => {
                        const options = group.options ?? group.modifiers ?? [];
                        const max = group.selection_type === "single" ? 1 : group.max_select;

                        return (
                            <div key={group.id}>
                                <div className="flex items-center gap-2 mb-2">
                                    <h3 className="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                        {group.name}
                                    </h3>
                                    {Number(group.min_select) > 0 && (
                                        <span className="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-danger-100 text-danger-700 dark:bg-danger-900/50 dark:text-danger-300">
                                            Wajib
                                        </span>
                                    )}
                                    {group.selection_type === "multiple" && max ? (
                                        <span className="text-xs text-slate-400">
                                            maks {max}
                                        </span>
                                    ) : null}
                                </div>

                                <div className="space-y-2">
                                    {options.map((option) => {
                                        const checked = (selected[group.id] || []).includes(option.id);
                                        const atLimit =
                                            group.selection_type === "multiple" &&
                                            max !== null &&
                                            max !== undefined &&
                                            (selected[group.id] || []).length >= Number(max) &&
                                            !checked;

                                        return (
                                            <label
                                                key={option.id}
                                                className={`flex items-center justify-between gap-3 rounded-xl border px-3 py-2 cursor-pointer ${
                                                    checked
                                                        ? "border-primary-500 bg-primary-50 dark:bg-primary-900/20"
                                                        : "border-slate-200 dark:border-slate-800"
                                                } ${atLimit ? "opacity-50 cursor-not-allowed" : ""}`}
                                            >
                                                <span className="flex items-center gap-2">
                                                    <input
                                                        type={group.selection_type === "single" ? "radio" : "checkbox"}
                                                        name={`group-${group.id}`}
                                                        checked={checked}
                                                        disabled={atLimit}
                                                        onChange={() => toggle(group, option.id)}
                                                        className="w-4 h-4 text-primary-500 focus:ring-primary-500"
                                                    />
                                                    <span className="text-sm text-slate-800 dark:text-slate-200">
                                                        {option.name}
                                                    </span>
                                                </span>
                                                <span className="text-sm text-slate-500 dark:text-slate-400">
                                                    {Number(option.price_delta) === 0
                                                        ? ""
                                                        : `${Number(option.price_delta) > 0 ? "+" : "-"}${formatPrice(Math.abs(Number(option.price_delta)))}`}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}

                    <div>
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                            Catatan
                        </label>
                        <textarea
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            rows={2}
                            maxLength={255}
                            placeholder="Contoh: tanpa es, gula setengah"
                            className="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-slate-100"
                        />
                    </div>
                </div>

                <div className="mt-5 flex items-center justify-between gap-3">
                    <div>
                        <p className="text-xs text-slate-500 dark:text-slate-400">Total</p>
                        <p className="text-lg font-semibold text-slate-900 dark:text-white">
                            {formatPrice(basePrice + delta)}
                        </p>
                    </div>
                    <button
                        type="button"
                        disabled={!isValid}
                        onClick={() =>
                            onConfirm({
                                product,
                                unit,
                                modifierIds: selectedIds,
                                note: note.trim() === "" ? null : note.trim(),
                            })
                        }
                        className="rounded-xl bg-primary-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Tambah ke Keranjang
                    </button>
                </div>
            </div>
        </div>
    );
}
import React from "react";
import { IconAdjustments } from "@tabler/icons-react";

const formatDelta = (value) => {
    const amount = new Intl.NumberFormat("id-ID").format(Math.abs(Number(value) || 0));
    return `${Number(value) >= 0 ? "+" : "-"}Rp${amount}`;
};

export default function ModifierSelector({
    groups = [],
    selectedGroups = [],
    overrides = {},
    onChangeGroups,
    onChangeOverride,
    error,
}) {
    if (groups.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-slate-300 dark:border-slate-700 p-4 text-sm text-slate-500 dark:text-slate-400">
                Belum ada grup modifier. Buat grup terlebih dahulu di menu Modifier.
            </div>
        );
    }

    const toggleGroup = (groupId) => {
        const next = selectedGroups.includes(groupId)
            ? selectedGroups.filter((id) => id !== groupId)
            : [...selectedGroups, groupId];

        onChangeGroups(next);
    };

    return (
        <div className="space-y-3">
            {groups.map((group) => {
                const attached = selectedGroups.includes(group.id);

                return (
                    <div
                        key={group.id}
                        className="rounded-xl border border-slate-200 dark:border-slate-800 p-4"
                    >
                        <label className="flex items-center gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={attached}
                                onChange={() => toggleGroup(group.id)}
                                className="w-4 h-4 rounded border-slate-300 text-primary-500 focus:ring-primary-500"
                            />
                            <span className="text-sm font-medium text-slate-800 dark:text-slate-200">
                                {group.name}
                            </span>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                {group.selection_type === "single" ? "Pilih satu" : "Pilih banyak"}
                            </span>
                        </label>

                        {attached && (
                            <div className="mt-3 space-y-2 pl-7">
                                {(group.modifiers || []).map((option) => (
                                    <div
                                        key={option.id}
                                        className="flex flex-wrap items-center justify-between gap-3"
                                    >
                                        <div className="flex items-center gap-2">
                                            <IconAdjustments
                                                size={14}
                                                strokeWidth={1.5}
                                                className="text-slate-400"
                                            />
                                            <span className="text-sm text-slate-700 dark:text-slate-300">
                                                {option.name}
                                            </span>
                                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                                {formatDelta(option.price_delta)}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <label className="text-xs text-slate-500 dark:text-slate-400">
                                                Harga khusus
                                            </label>
                                            <input
                                                type="number"
                                                value={overrides[option.id] ?? ""}
                                                onChange={(event) =>
                                                    onChangeOverride(option.id, event.target.value)
                                                }
                                                placeholder={String(option.price_delta)}
                                                className="w-28 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-1 text-sm text-slate-900 dark:text-slate-100"
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}

            {error && <p className="text-sm text-danger-500">{error}</p>}
        </div>
    );
}
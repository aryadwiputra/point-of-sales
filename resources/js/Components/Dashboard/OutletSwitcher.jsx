import React from "react";
import { router } from "@inertiajs/react";
import { IconBuildingStore } from "@tabler/icons-react";

export default function OutletSwitcher({ outlet, outlets = [], locked = false }) {
    if (outlets.length < 2) {
        return null;
    }

    return (
        <label className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
            <IconBuildingStore size={18} className="shrink-0 text-primary-500" />
            <select
                value={outlet?.id || ""}
                disabled={locked}
                onChange={(event) => router.post(route("outlet.switch"), { outlet_id: event.target.value }, { preserveScroll: true })}
                className="max-w-36 border-0 bg-transparent p-0 text-sm font-medium text-slate-700 outline-none focus:ring-0 dark:text-slate-200 disabled:cursor-not-allowed disabled:opacity-60"
                title={locked ? "Tutup shift aktif untuk mengganti outlet" : "Pilih outlet"}
            >
                {outlets.map((item) => (
                    <option key={item.id} value={item.id}>
                        {item.code} - {item.name}
                    </option>
                ))}
            </select>
        </label>
    );
}

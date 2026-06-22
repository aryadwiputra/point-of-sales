import React, { useRef } from "react";
import toast from "react-hot-toast";
import { router } from "@inertiajs/react";

export default function ImportButton({ routeName, label = "Import", accept = ".xlsx,.xls,.csv" }) {
    const inputRef = useRef(null);

    const handleFile = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        router.post(route(routeName), { file }, {
            onSuccess: () => { toast.success("Import selesai"); e.target.value = ""; },
            onError: () => { toast.error("Gagal import"); e.target.value = ""; },
        });
    };

    return (
        <>
            <input ref={inputRef} type="file" accept={accept} onChange={handleFile} className="hidden" />
            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                className="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors w-full sm:w-auto"
            >
                <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" fill="none"><path strokeLinecap="round" strokeLinejoin="round" d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 11l5 5 5-5M12 4v12"/></svg>
                {label}
            </button>
        </>
    );
}

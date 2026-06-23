import React from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, useForm } from "@inertiajs/react";
import toast from "react-hot-toast";
import { IconPrinter } from "@tabler/icons-react";

export default function Printer({ settings }) {
    const { data, setData, post, processing, errors } = useForm({
        printer_auto_print: settings.printer_auto_print || false,
        printer_paper_size: settings.printer_paper_size || "80mm",
    });

    const submit = (e) => {
        e.preventDefault();
        post(route("settings.printer.update"), {
            preserveScroll: true,
            onSuccess: () => toast.success("Pengaturan printer disimpan"),
            onError: () => toast.error("Gagal menyimpan"),
        });
    };

    return (
        <>
            <Head title="Pengaturan Printer" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <IconPrinter size={28} className="text-primary-500" />
                        Pengaturan Printer
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Atur printer thermal untuk cetak struk otomatis</p>
                </div>

                <form onSubmit={submit} className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-6 max-w-lg">
                    <div>
                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Ukuran Kertas</label>
                        <select value={data.printer_paper_size} onChange={(e) => setData("printer_paper_size", e.target.value)}
                            className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm">
                            <option value="80mm">80 mm</option>
                            <option value="58mm">58 mm</option>
                        </select>
                    </div>

                    <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input type="checkbox" checked={data.printer_auto_print} onChange={(e) => setData("printer_auto_print", e.target.checked)}
                            className="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                        Cetak otomatis setelah transaksi
                    </label>

                    <div className="pt-4 border-t border-slate-100 dark:border-slate-800">
                        <p className="text-sm text-slate-500 mb-3">Printer thermal terhubung via WebUSB (Chrome/Edge)</p>
                        <button type="submit" disabled={processing}
                            className="px-5 py-2.5 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-medium transition-colors disabled:opacity-50">
                            {processing ? "Menyimpan..." : "Simpan Pengaturan"}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}

Printer.layout = (page) => <DashboardLayout children={page} />;

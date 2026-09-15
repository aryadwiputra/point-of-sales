import { useEffect, useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import Input from "@/Components/Dashboard/Input";
import { useAuthorization } from "@/Utils/authorization";
import { IconBuildingStore, IconBuildingWarehouse, IconCheck, IconPlus } from "@tabler/icons-react";
import toast from "react-hot-toast";

const emptyForm = { name: "", code: "", address: "", phone: "", email: "", is_sales_enabled: true, warehouse_name: "", warehouse_code: "" };

export default function Outlets({ outlets = [] }) {
    const { flash } = usePage().props;
    const { can } = useAuthorization();
    const [showForm, setShowForm] = useState(false);
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const update = (key, value) => setForm((current) => ({ ...current, [key]: value }));

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);

    const submit = (event) => {
        event.preventDefault();
        router.post(route("settings.outlets.store"), form, {
            onError: setErrors,
            onSuccess: () => { setShowForm(false); setForm(emptyForm); },
        });
    };

    return (
        <DashboardLayout>
            <Head title="Outlet" />
            <div className="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold text-slate-900 dark:text-white"><IconBuildingStore size={28} className="text-primary-500" />Outlet</h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Buat outlet penjualan dan gudang operasionalnya.</p>
                </div>
                {can("outlets-create") && <button type="button" onClick={() => { setForm(emptyForm); setErrors({}); setShowForm(true); }} className="inline-flex items-center gap-2 rounded-xl bg-primary-500 px-3 py-2 text-sm font-medium text-white hover:bg-primary-600"><IconPlus size={18} />Tambah Outlet</button>}
            </div>
            <div className="max-w-4xl space-y-6">
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    {outlets.length ? outlets.map((outlet) => <div key={outlet.id} className="flex items-center gap-4 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800"><div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-500 dark:bg-primary-950/40"><IconBuildingStore size={22} /></div><div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h2 className="font-semibold text-slate-800 dark:text-white">{outlet.name}</h2><span className="rounded-lg bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{outlet.code}</span><span className="rounded-lg bg-success-50 px-2 py-0.5 text-xs text-success-600 dark:bg-success-950/30 dark:text-success-400">{outlet.is_sales_enabled ? "Penjualan aktif" : "Gudang pusat"}</span></div><p className="mt-1 flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400"><IconBuildingWarehouse size={15} />{outlet.warehouses?.[0]?.name || "Belum ada gudang"}</p></div>{outlet.is_active && <IconCheck size={20} className="text-success-500" />}</div>) : <p className="p-8 text-center text-sm text-slate-500">Belum ada outlet.</p>}
                </div>
                {showForm && <form onSubmit={submit} className="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><h2 className="font-semibold text-slate-800 dark:text-white">Buat Outlet Baru</h2><div className="grid gap-4 sm:grid-cols-2"><Input label="Nama Outlet" value={form.name} onChange={(event) => update("name", event.target.value)} errors={errors.name} autoFocus /><Input label="Kode Outlet" placeholder="MAL" value={form.code} onChange={(event) => update("code", event.target.value.toUpperCase())} errors={errors.code} /><Input label="Telepon" value={form.phone} onChange={(event) => update("phone", event.target.value)} errors={errors.phone} /><Input label="Email" type="email" value={form.email} onChange={(event) => update("email", event.target.value)} errors={errors.email} /></div><div><label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">Alamat</label><textarea value={form.address} onChange={(event) => update("address", event.target.value)} className="h-20 w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" /></div><label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input type="checkbox" checked={form.is_sales_enabled} onChange={(event) => update("is_sales_enabled", event.target.checked)} className="rounded border-slate-300 text-primary-600" />Outlet melayani penjualan (aktif secara default)</label><div className="grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2 dark:border-slate-800"><Input label="Nama Gudang" value={form.warehouse_name} onChange={(event) => update("warehouse_name", event.target.value)} errors={errors.warehouse_name} /><Input label="Kode Gudang" placeholder="WH-MAL" value={form.warehouse_code} onChange={(event) => update("warehouse_code", event.target.value.toUpperCase())} errors={errors.warehouse_code} /></div><div className="flex justify-end gap-2"><button type="button" onClick={() => setShowForm(false)} className="rounded-xl px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300">Batal</button><button type="submit" className="rounded-xl bg-primary-500 px-4 py-2 text-sm font-medium text-white hover:bg-primary-600">Buat Outlet</button></div></form>}
            </div>
        </DashboardLayout>
    );
}

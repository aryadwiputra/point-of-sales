import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, router, useForm, usePage } from "@inertiajs/react";
import { useEffect } from "react";
import toast from "react-hot-toast";
import {
    IconPlug,
    IconCalculator,
    IconGift,
    IconArrowUpRight,
    IconPower,
    IconLock,
    IconCheck,
} from "@tabler/icons-react";

function AddonCard({ addon, children }) {
    const isActive = addon.active;
    const isEntitled = addon.entitled;

    return (
        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 flex flex-col gap-4">
            <div className="flex items-start justify-between gap-4">
                <div className="flex items-start gap-4">
                    <div
                        className={`p-3 rounded-xl ${
                            isActive
                                ? "bg-success-100 dark:bg-success-900/30"
                                : "bg-slate-100 dark:bg-slate-800"
                        }`}
                    >
                        {addon.code === "finance" ? (
                            <IconCalculator
                                size={24}
                                className={
                                    isActive
                                        ? "text-success-600 dark:text-success-400"
                                        : "text-slate-400"
                                }
                            />
                        ) : (
                            <IconGift
                                size={24}
                                className={
                                    isActive
                                        ? "text-success-600 dark:text-success-400"
                                        : "text-slate-400"
                                }
                            />
                        )}
                    </div>
                    <div>
                        <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                            {addon.label}
                        </h2>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md">
                            {addon.description}
                        </p>
                    </div>
                </div>

                <span
                    className={`shrink-0 inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold ${
                        isActive
                            ? "bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-400"
                            : "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                    }`}
                >
                    {isActive ? (
                        <>
                            <IconCheck size={12} /> Aktif
                        </>
                    ) : (
                        <>
                            <IconLock size={12} /> Nonaktif
                        </>
                    )}
                </span>
            </div>

            <div className="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                {children}
            </div>

            {!isEntitled && (
                <p className="text-xs text-warning-600 dark:text-warning-400">
                    Add-on ini belum termasuk dalam paket Anda. Hubungi
                    administrator untuk mengaktifkan.
                </p>
            )}
        </div>
    );
}

function ToggleButton({ addon, processing }) {
    const { post } = useForm();

    const toggle = () => {
        post(
            route("settings.integrations.update", addon.code),
            {
                action: addon.active ? "deactivate" : "activate",
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    toast.success(
                        addon.active
                            ? `${addon.label} dinonaktifkan`
                            : `${addon.label} diaktifkan`
                    ),
                onError: () => toast.error("Gagal mengubah status add-on"),
            }
        );
    };

    return (
        <button
            type="button"
            onClick={toggle}
            disabled={processing || !addon.entitled}
            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border-2 transition-all disabled:opacity-50 disabled:cursor-not-allowed
                border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
        >
            <IconPower size={16} />
            {addon.active ? "Nonaktifkan" : "Aktifkan"}
        </button>
    );
}

function Integrations({ addons, financeUrl }) {
    const finance = addons.find((a) => a.code === "finance");
    const bihaju = addons.find((a) => a.code === "bihaju");
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
        if (flash?.warning) toast(flash.warning);
        if (flash?.info) toast(flash.info);
    }, [flash?.success, flash?.error, flash?.warning, flash?.info]);

    return (
        <>
            <Head title="Integrasi & Add-on" />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                            Integrasi & Add-on
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Aktifkan layanan tambahan untuk tenant ini sesuai
                            paket langganan Anda
                        </p>
                    </div>
                    <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs text-slate-500 dark:text-slate-400">
                        <IconPlug size={14} />
                        {addons.filter((a) => a.active).length} add-on aktif
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {finance && (
                        <AddonCard addon={finance}>
                            {finance.active ? (
                                <>
                                    <a
                                        href={route("settings.finance.launch")}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-primary-600 text-white hover:bg-primary-700 transition-all"
                                    >
                                        <IconArrowUpRight size={16} />
                                        Buka Aplikasi Finance
                                    </a>
                                    <ToggleButton addon={finance} processing={false} />
                                    <span className="text-xs text-slate-500 dark:text-slate-400 break-all">
                                        Akses via SSO ke {financeUrl} (buka di tab baru). Entitlement divalidasi per akun/tenant.
                                    </span>
                                </>
                            ) : (
                                <>
                                    <div className="w-full rounded-xl border border-warning-200 bg-warning-50 dark:border-warning-900/40 dark:bg-warning-900/20 p-4 text-sm text-warning-800 dark:text-warning-200">
                                        <p className="font-semibold">Add-on Finance belum aktif untuk akun ini</p>
                                        <p className="mt-1">
                                            Untuk menggunakan aplikasi Finance, akun/tenant Anda harus memiliki langganan/add-on Finance yang aktif.
                                            {finance.entitled
                                                ? " Add-on tersedia pada paket Anda — aktifkan di bawah ini."
                                                : " Silakan beli atau upgrade add-on Finance (hubungi administrator/paket langganan) agar dapat diaktifkan."}
                                        </p>
                                    </div>
                                    {finance.entitled && <ToggleButton addon={finance} processing={false} />}
                                </>
                            )}
                        </AddonCard>
                    )}

                    {bihaju && (
                        <AddonCard addon={bihaju}>
                            {bihaju.active ? (
                                <>
                                    <a
                                        href={route("settings.bihaju")}
                                        className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-primary-600 text-white hover:bg-primary-700 transition-all"
                                    >
                                        <IconArrowUpRight size={16} />
                                        Kelola Binding Outlet
                                    </a>
                                    <ToggleButton addon={bihaju} processing={false} />
                                </>
                            ) : (
                                <>
                                    <div className="w-full rounded-xl border border-warning-200 bg-warning-50 dark:border-warning-900/40 dark:bg-warning-900/20 p-4 text-sm text-warning-800 dark:text-warning-200">
                                        <p className="font-semibold">Add-on Bihaju belum aktif untuk akun ini</p>
                                        <p className="mt-1">
                                            {bihaju.entitled
                                                ? "Add-on tersedia pada paket Anda — aktifkan untuk mulai menggunakan integrasi Bihaju."
                                                : "Silakan beli atau upgrade add-on Bihaju (hubungi administrator/paket langganan) untuk mengaktifkannya."}
                                        </p>
                                    </div>
                                    {bihaju.entitled && <ToggleButton addon={bihaju} processing={false} />}
                                </>
                            )}
                        </AddonCard>
                    )}
                </div>

                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6">
                    <h2 className="text-base font-semibold text-slate-900 dark:text-white mb-2">
                        Cara kerja entitlement
                    </h2>
                    <ul className="text-sm text-slate-500 dark:text-slate-400 space-y-2 list-disc pl-5">
                        <li>
                            Entitlement/validasi add-on dilakukan <strong>per akun/tenant</strong>:
                            add-on aktif untuk tenant tersebut jika disertakan paket langganan
                            atau dibeli/diaktifkan sebagai add-on terpisah (<code>tenant_addons</code>).
                        </li>
                        <li>
                            Buka Finance menggunakan tombol SSO (bukan membuka URL langsung) agar
                            akses terikat ke akun & tenant yang sedang login; Finance juga memvalidasi
                            ulang entitlement pada setiap request.
                        </li>
                        <li>
                            Jika add-on belum aktif untuk akun ini, gunakan pesan/opsi aktivasi atau
                            beli/upgrade add-on sesuai paket langganan sebelum membuka Finance.
                        </li>
                    </ul>
                </div>
            </div>
        </>
    );
}

Integrations.layout = (page) => <DashboardLayout children={page} />;

export default Integrations;

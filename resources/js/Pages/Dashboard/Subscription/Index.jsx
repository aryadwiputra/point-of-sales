import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import {
    IconCreditCard,
    IconCircleCheck,
    IconClock,
    IconCrown,
    IconSparkles,
} from "@tabler/icons-react";
import toast from "react-hot-toast";
import { formatFeature } from "@/Utils/planFeatures";
import { describeFee } from "@/Utils/transactionFee";

const formatRupiah = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value || 0);

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleDateString("id-ID", {
              day: "numeric",
              month: "long",
              year: "numeric",
          })
        : "-";

const statusStyles = {
    paid: "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300",
    pending: "bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300",
    failed: "bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300",
    expired: "bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-300",
};

export default function SubscriptionIndex({
    tenant,
    plans = [],
    usage = {},
    subscriptions = [],
    gateways = [],
}) {
    const [cycle, setCycle] = useState("monthly");
    const [processingPlanId, setProcessingPlanId] = useState(null);

    const usageRows = [
        { key: "outlets", label: "Cabang / Outlet" },
        { key: "users", label: "Pengguna" },
        { key: "products", label: "Produk" },
        { key: "customers", label: "Pelanggan" },
    ];

    const choosePlan = (plan) => {
        if (!tenant) {
            toast.error("Halaman checkout langganan hanya tersedia untuk akun tenant.");
            return;
        }

        const label = `${plan.name} (${cycle === "yearly" ? "Tahunan" : "Bulanan"})`;
        if (!confirm(`Lanjutkan langganan paket ${label}?`)) return;

        setProcessingPlanId(plan.id);
        router.post(
            route("subscription.checkout"),
            { plan_id: plan.id, billing_cycle: cycle },
            {
                preserveScroll: true,
                onError: () => toast.error("Gagal memproses langganan"),
                onFinish: () => setProcessingPlanId(null),
            }
        );
    };

    const statusLabel = {
        active: "Aktif",
        trial: "Trial",
        expired: "Kedaluwarsa",
        suspended: "Disuspend",
        cancelled: "Dibatalkan",
    };

    return (
        <>
            <Head title="Langganan & Paket" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                        Langganan & Paket
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                        Kelola paket langganan, pantau kuota, dan riwayat pembayaran.
                    </p>
                </div>

                {!tenant && (
                    <section className="rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-100">
                        <div className="font-semibold">Akun platform / tanpa konteks tenant</div>
                        <p className="mt-1">
                            Halaman ini menampilkan daftar paket dan ringkasan penggunaan, tetapi checkout dan riwayat langganan tenant hanya tersedia untuk akun yang terhubung ke tenant.
                            Gunakan panel superadmin untuk mengelola tenant jika diperlukan.
                        </p>
                    </section>
                )}

                {/* Current plan */}
                <section className="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-100 text-primary-600 dark:bg-primary-950/40 dark:text-primary-300">
                                <IconCrown size={24} />
                            </div>
                            <div>
                                <div className="text-xs uppercase tracking-wide text-slate-400">
                                    {tenant ? "Paket saat ini" : "Konteks langganan"}
                                </div>
                                <div className="text-xl font-bold text-slate-900 dark:text-white">
                                    {tenant?.plan?.name || (tenant ? "Belum ada paket" : "Tidak terkait tenant")}
                                </div>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                                    {tenant ? (
                                        <>
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    tenant.is_active
                                                        ? statusStyles.paid
                                                        : statusStyles.expired
                                                }`}
                                            >
                                                {statusLabel[tenant.status] || tenant.status}
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <IconClock size={14} />
                                                {tenant.is_active
                                                    ? tenant.subscription_ends_at
                                                        ? `Aktif s.d. ${formatDate(tenant.subscription_ends_at)}`
                                                        : "Aktif selamanya"
                                                    : `Berakhir ${formatDate(tenant.subscription_ends_at)}`}
                                            </span>
                                        </>
                                    ) : (
                                        <span>Login platform tidak memiliki tenant aktif.</span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {tenant && !tenant.is_active && (
                            <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                                Masa aktif habis. Pilih paket di bawah untuk memperpanjang langganan.
                            </div>
                        )}
                    </div>

                    {/* Usage */}
                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {usageRows.map((row) => {
                            const item = usage[row.key] || {};
                            const max = item.max;
                            const current = item.current || 0;
                            const percent = max ? Math.min(100, Math.round((current / max) * 100)) : 0;
                            const nearLimit = max && current >= max;

                            return (
                                <div
                                    key={row.key}
                                    className="rounded-xl border border-slate-200 p-4 dark:border-slate-800"
                                >
                                    <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                                        <span>{row.label}</span>
                                        <span className={nearLimit ? "font-semibold text-rose-500" : ""}>
                                            {current}
                                            {max ? ` / ${max}` : " / ∞"}
                                        </span>
                                    </div>
                                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div
                                            className={`h-full rounded-full ${
                                                nearLimit ? "bg-rose-500" : "bg-primary-500"
                                            }`}
                                            style={{ width: `${max ? percent : 4}%` }}
                                        />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>

                {/* Plan picker */}
                <section className="space-y-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                                Pilih Paket
                            </h2>
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Upgrade kapan saja, berlaku setelah pembayaran
                                dikonfirmasi.
                            </p>
                        </div>

                        <div className="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1 text-sm dark:border-slate-800 dark:bg-slate-900">
                            {[
                                { value: "monthly", label: "Bulanan" },
                                { value: "yearly", label: "Tahunan" },
                            ].map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => setCycle(option.value)}
                                    className={`rounded-lg px-4 py-1.5 font-medium transition ${
                                        cycle === option.value
                                            ? "bg-white text-primary-600 shadow-sm dark:bg-slate-800 dark:text-primary-300"
                                            : "text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                                    }`}
                                >
                                    {option.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {gateways.length === 0 && (
                        <div className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                            Pembayaran otomatis belum dikonfigurasi. Paket
                            berbayar akan diproses manual oleh admin platform.
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {plans.map((plan) => {
                            const isCurrent =
                                !!tenant && plan.id === tenant.plan_id && tenant.is_active;
                            const price =
                                cycle === "yearly"
                                    ? plan.price_yearly
                                    : plan.price_monthly;

                            return (
                                <div
                                    key={plan.id}
                                    className={`flex flex-col rounded-2xl border p-6 ${
                                        isCurrent
                                            ? "border-primary-500 bg-primary-50/60 dark:border-primary-500/60 dark:bg-primary-950/20"
                                            : "border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                                    }`}
                                >
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-lg font-bold text-slate-900 dark:text-white">
                                            {plan.name}
                                        </h3>
                                        {isCurrent ? (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-950/60 dark:text-primary-300">
                                                <IconCircleCheck size={14} />
                                                Paket aktif
                                            </span>
                                        ) : (
                                            <IconSparkles
                                                size={18}
                                                className="text-slate-400"
                                            />
                                        )}
                                    </div>

                                    <p className="mt-1 min-h-[40px] text-sm text-slate-500 dark:text-slate-400">
                                        {plan.description || "Paket langganan BPOS"}
                                    </p>

                                    <div className="mt-4">
                                        {plan.billing_type === "per_transaction" ? (
                                            <>
                                                <span className="text-2xl font-extrabold text-slate-900 dark:text-white">
                                                    Rp0
                                                </span>
                                                <span className="text-sm text-slate-500 dark:text-slate-400">
                                                    {" "}
                                                    / bulan
                                                </span>
                                                <p className="mt-1 text-sm font-medium text-warning-600 dark:text-warning-400">
                                                    {describeFee(plan)}
                                                </p>
                                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                                    Bayar hanya saat bertransaksi.
                                                </p>
                                            </>
                                        ) : (
                                            <>
                                                <span className="text-2xl font-extrabold text-slate-900 dark:text-white">
                                                    {formatRupiah(price)}
                                                </span>
                                                <span className="text-sm text-slate-500 dark:text-slate-400">
                                                    {" "}
                                                    / {cycle === "yearly" ? "tahun" : "bulan"}
                                                </span>
                                            </>
                                        )}
                                    </div>

                                    <ul className="mt-4 flex-1 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                        <li>
                                            {plan.max_outlets} cabang ·{" "}
                                            {plan.max_users} pengguna
                                        </li>
                                        <li>
                                            {plan.max_products} produk ·{" "}
                                            {plan.max_customers} pelanggan
                                        </li>
                                        {(plan.features || []).length > 0 && (
                                            <li className="flex flex-wrap gap-1.5 pt-1">
                                                {plan.features.map((feature) => (
                                                    <span
                                                        key={feature}
                                                        className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                                    >
                                                        {formatFeature(feature)}
                                                    </span>
                                                ))}
                                            </li>
                                        )}
                                    </ul>

                                    <button
                                        type="button"
                                        onClick={() => choosePlan(plan)}
                                        disabled={!tenant || isCurrent || processingPlanId === plan.id}
                                        className={`mt-5 inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition ${
                                            isCurrent || !tenant
                                                ? "cursor-not-allowed bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500"
                                                : "bg-primary-600 text-white hover:bg-primary-500 disabled:opacity-60"
                                        }`}
                                    >
                                        <IconCreditCard size={16} />
                                        {!tenant
                                            ? "Butuh akun tenant"
                                            : isCurrent
                                              ? "Sedang digunakan"
                                              : processingPlanId === plan.id
                                                ? "Memproses..."
                                                : Number(price) > 0
                                                  ? "Bayar & Aktifkan"
                                                  : "Aktifkan Paket"}
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </section>

                {/* History */}
                <section className="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div className="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                            Riwayat Langganan
                        </h2>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Riwayat pembayaran dan perpanjangan paket.
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                                <tr>
                                    <th className="px-6 py-3">Paket</th>
                                    <th className="px-6 py-3">Siklus</th>
                                    <th className="px-6 py-3">Nominal</th>
                                    <th className="px-6 py-3">Status</th>
                                    <th className="px-6 py-3">Periode</th>
                                    <th className="px-6 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {subscriptions.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-10 text-center text-slate-500 dark:text-slate-400"
                                        >
                                            Belum ada riwayat langganan.
                                        </td>
                                    </tr>
                                ) : (
                                    subscriptions.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-6 py-3 font-medium text-slate-800 dark:text-slate-100">
                                                {item.plan || "-"}
                                            </td>
                                            <td className="px-6 py-3 text-slate-600 dark:text-slate-300">
                                                {item.billing_cycle === "yearly"
                                                    ? "Tahunan"
                                                    : "Bulanan"}
                                            </td>
                                            <td className="px-6 py-3 text-slate-800 dark:text-slate-100">
                                                {formatRupiah(item.amount)}
                                            </td>
                                            <td className="px-6 py-3">
                                                <span
                                                    className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                                                        statusStyles[item.payment_status] ||
                                                        statusStyles.expired
                                                    }`}
                                                >
                                                    {item.payment_status}
                                                </span>
                                            </td>
                                            <td className="px-6 py-3 text-xs text-slate-500 dark:text-slate-400">
                                                {formatDate(item.starts_at)} —{" "}
                                                {item.ends_at
                                                    ? formatDate(item.ends_at)
                                                    : "Tanpa batas"}
                                            </td>
                                            <td className="px-6 py-3 text-right">
                                                {item.payment_status === "pending" &&
                                                item.payment_url ? (
                                                    <a
                                                        href={item.payment_url}
                                                        className="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                                                    >
                                                        Bayar
                                                    </a>
                                                ) : (
                                                    <span className="text-xs text-slate-400">
                                                        -
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

SubscriptionIndex.layout = (page) => <DashboardLayout children={page} />;

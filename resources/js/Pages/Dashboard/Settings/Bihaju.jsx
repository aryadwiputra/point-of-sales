import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import axios from "axios";
import toast from "react-hot-toast";
import { QRCodeSVG } from "qrcode.react";
import {
    IconGift,
    IconQrcode,
    IconLink,
    IconUnlink,
    IconRefresh,
    IconCheck,
    IconX,
    IconClock,
    IconUsers,
    IconCopy,
    IconExternalLink,
} from "@tabler/icons-react";

const statusBadge = (status) => {
    const map = {
        active: {
            cls: "bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-400",
            label: "Terhubung",
            icon: IconCheck,
        },
        pending: {
            cls: "bg-warning-100 text-warning-700 dark:bg-warning-900/40 dark:text-warning-400",
            label: "Menunggu Persetujuan",
            icon: IconClock,
        },
        revoked: {
            cls: "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400",
            label: "Diputus",
            icon: IconX,
        },
        succeeded: {
            cls: "bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-400",
            label: "Terkirim",
            icon: IconCheck,
        },
        failed: {
            cls: "bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-400",
            label: "Gagal",
            icon: IconX,
        },
        submitted: {
            cls: "bg-info-100 text-info-700 dark:bg-info-900/40 dark:text-info-400",
            label: "Dikirim",
            icon: IconRefresh,
        },
    };

    return map[status] || map.pending;
};

function Badge({ status }) {
    const { cls, label, icon: Icon } = statusBadge(status);

    return (
        <span
            className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ${cls}`}
        >
            <Icon size={12} />
            {label}
        </span>
    );
}

function QrModal({ binding, onClose }) {
    const [copied, setCopied] = useState(false);

    if (!binding?.bind_url) return null;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(binding.bind_url);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            toast.error("Gagal menyalin tautan");
        }
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
            onClick={onClose}
        >
            <div
                className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 w-full max-w-sm"
                onClick={(e) => e.stopPropagation()}
            >
                <h3 className="text-base font-semibold text-slate-900 dark:text-white mb-1">
                    Binding Outlet
                </h3>
                <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
                    Scan QR ini menggunakan aplikasi Bihaju, lalu setujui
                    permohonannya.
                </p>

                <div className="flex justify-center py-4">
                    <div className="bg-white p-4 rounded-xl">
                        <QRCodeSVG value={binding.bind_url} size={200} />
                    </div>
                </div>

                <p className="text-xs text-center text-slate-500 dark:text-slate-400 mb-4">
                    Berlaku hingga{" "}
                    {binding.bind_code_expires_at
                        ? new Date(binding.bind_code_expires_at).toLocaleString(
                              "id-ID"
                          )
                        : "10 menit"}
                </p>

                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={copy}
                        className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                    >
                        <IconCopy size={16} />
                        {copied ? "Tersalin" : "Salin Tautan"}
                    </button>
                    <a
                        href={binding.bind_url}
                        target="_blank"
                        rel="noreferrer"
                        className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-primary-600 text-white hover:bg-primary-700"
                    >
                        <IconExternalLink size={16} />
                        Buka
                    </a>
                </div>

                <button
                    type="button"
                    onClick={onClose}
                    className="w-full mt-2 px-4 py-2 rounded-xl text-sm font-semibold text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800"
                >
                    Tutup
                </button>
            </div>
        </div>
    );
}

function BindingCard({ binding, outlets, onShowQr }) {
    const outlet = outlets.find((o) => o.id === binding.outlet_id);
    const [processing, setProcessing] = useState(false);

    const unbind = async () => {
        if (!confirm("Putuskan outlet ini dari Bihaju?")) return;

        setProcessing(true);
        try {
            await axios.delete(route("settings.bihaju.unbind", binding.id));
            toast.success("Outlet diputus dari Bihaju");
            router.reload({ only: ["summary"] });
        } catch (err) {
            toast.error(err.response?.data?.message || "Gagal memutus koneksi");
        } finally {
            setProcessing(false);
        }
    };

    return (
        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 flex flex-col gap-3">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                        {binding.outlet_name || outlet?.name || "Outlet"}
                    </h3>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {binding.external_ref}
                    </p>
                </div>
                <Badge status={binding.status} />
            </div>

            {binding.status === "active" && (
                <div className="text-xs text-slate-500 dark:text-slate-400 space-y-1">
                    <p>
                        Merchant:{" "}
                        <span className="font-medium text-slate-700 dark:text-slate-300">
                            {binding.merchant_name || "-"}
                            {binding.merchant_city
                                ? ` · ${binding.merchant_city}`
                                : ""}
                        </span>
                    </p>
                    {binding.merchant_balance != null && (
                        <p>
                            Saldo:{" "}
                            <span className="font-medium text-slate-700 dark:text-slate-300">
                                {new Intl.NumberFormat("id-ID", {
                                    style: "currency",
                                    currency: "IDR",
                                    minimumFractionDigits: 0,
                                }).format(binding.merchant_balance)}
                            </span>
                        </p>
                    )}
                    {binding.bound_at && (
                        <p>
                            Terikat sejak{" "}
                            {new Date(binding.bound_at).toLocaleDateString(
                                "id-ID"
                            )}
                        </p>
                    )}
                </div>
            )}

            <div className="flex gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                {binding.status === "pending" && binding.bind_url && (
                    <button
                        type="button"
                        onClick={() => onShowQr(binding)}
                        className="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold bg-primary-600 text-white hover:bg-primary-700"
                    >
                        <IconQrcode size={14} />
                        Tampilkan QR
                    </button>
                )}

                {binding.status === "active" && (
                    <button
                        type="button"
                        onClick={unbind}
                        disabled={processing}
                        className="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50"
                    >
                        <IconUnlink size={14} />
                        Putuskan
                    </button>
                )}

                {binding.status === "revoked" && (
                    <span className="flex-1 text-center text-xs text-slate-400 py-2">
                        Buat binding baru dari daftar outlet
                    </span>
                )}
            </div>
        </div>
    );
}

function OutletBindList({ outlets, bindings, onBind }) {
    const [selected, setSelected] = useState("");
    const [processing, setProcessing] = useState(false);

    const available = outlets.filter(
        (o) => !bindings.some((b) => b.outlet_id === o.id && b.status !== "revoked")
    );

    const submit = async (e) => {
        e.preventDefault();
        if (!selected || processing) return;

        setProcessing(true);
        try {
            const res = await axios.post(route("settings.bihaju.bind"), {
                outlet_id: Number(selected),
            });

            toast.success(res.data?.message || "Permohonan binding dibuat");
            setSelected("");
            if (res.data?.binding) onBind(res.data.binding);
            router.reload({ only: ["summary", "outlets"] });
        } catch (err) {
            const data = err.response?.data;
            toast.error(
                data?.message ||
                    data?.errors?.outlet_id?.[0] ||
                    "Gagal membuat QR binding"
            );
        } finally {
            setProcessing(false);
        }
    };

    if (available.length === 0) {
        return (
            <p className="text-sm text-slate-500 dark:text-slate-400">
                Semua outlet sudah terikat atau dalam proses binding.
            </p>
        );
    }

    return (
        <form onSubmit={submit} className="flex flex-wrap items-center gap-3">
            <select
                value={selected}
                onChange={(e) => setSelected(e.target.value)}
                className="h-11 px-4 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20"
            >
                <option value="">Pilih outlet…</option>
                {available.map((o) => (
                    <option key={o.id} value={o.id}>
                        {o.name} ({o.code})
                    </option>
                ))}
            </select>

            <button
                type="submit"
                disabled={processing || !selected}
                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-50"
            >
                <IconLink size={16} />
                {processing ? "Membuat…" : "Buat QR Binding"}
            </button>
        </form>
    );
}

function MemberSearch({ bindings }) {
    const activeBinding = bindings.find((b) => b.status === "active");
    const [query, setQuery] = useState("");
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);

    const search = async (e) => {
        e.preventDefault();
        if (!activeBinding || query.length < 3) return;

        setLoading(true);
        try {
            const res = await axios.get(route("settings.bihaju.members"), {
                params: { binding_id: activeBinding.id, q: query },
            });
            setResults(res.data.data || []);
        } catch (err) {
            toast.error(
                err.response?.data?.message || "Gagal mencari member"
            );
        } finally {
            setLoading(false);
        }
    };

    if (!activeBinding) return null;

    return (
        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
            <div className="flex items-center gap-2 mb-3">
                <IconUsers size={18} className="text-primary-500" />
                <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                    Cari Member Bihaju
                </h3>
            </div>

            <form onSubmit={search} className="flex gap-2">
                <input
                    type="text"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Nama atau kode referral (min. 3 karakter)"
                    className="flex-1 h-11 px-4 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20"
                />
                <button
                    type="submit"
                    disabled={loading || query.length < 3}
                    className="px-4 py-2.5 rounded-xl text-sm font-semibold border-2 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50"
                >
                    {loading ? "…" : "Cari"}
                </button>
            </form>

            {results.length > 0 && (
                <ul className="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                    {results.map((m) => (
                        <li
                            key={m.id}
                            className="py-2 flex items-center justify-between text-sm"
                        >
                            <div>
                                <p className="font-medium text-slate-800 dark:text-slate-200">
                                    {m.full_name}
                                </p>
                                <p className="text-xs text-slate-500">
                                    {m.phone}
                                </p>
                            </div>
                            <span className="px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                {m.referral_code}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function TransactionHistory({ transactions, onRetry }) {
    const [processingId, setProcessingId] = useState(null);

    const retry = async (id) => {
        setProcessingId(id);
        try {
            const res = await axios.post(
                route("settings.bihaju.retry", id)
            );
            toast.success(res.data?.message || "Transaksi dikirim ulang");
            onRetry();
        } catch (err) {
            toast.error(
                err.response?.data?.message || "Gagal mengirim ulang"
            );
        } finally {
            setProcessingId(null);
        }
    };

    if (transactions.length === 0) {
        return (
            <p className="text-sm text-slate-500 dark:text-slate-400">
                Belum ada transaksi yang dikirim ke Bihaju.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-left text-xs uppercase tracking-wide text-slate-400">
                        <th className="py-2 pr-3">Invoice</th>
                        <th className="py-2 pr-3">Nominal</th>
                        <th className="py-2 pr-3">Member</th>
                        <th className="py-2 pr-3">Status</th>
                        <th className="py-2 pr-3">Kode Bihaju</th>
                        <th className="py-2" />
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                    {transactions.map((t) => (
                        <tr key={t.id}>
                            <td className="py-2.5 pr-3 font-medium text-slate-800 dark:text-slate-200">
                                {t.invoice || t.external_ref}
                            </td>
                            <td className="py-2.5 pr-3 text-slate-600 dark:text-slate-300">
                                {new Intl.NumberFormat("id-ID", {
                                    style: "currency",
                                    currency: "IDR",
                                    minimumFractionDigits: 0,
                                }).format(t.amount)}
                            </td>
                            <td className="py-2.5 pr-3 text-slate-500">
                                {t.member_code || "-"}
                            </td>
                            <td className="py-2.5 pr-3">
                                <Badge status={t.status} />
                            </td>
                            <td className="py-2.5 pr-3 text-xs text-slate-500">
                                {t.bihaju_transaction_code || "-"}
                            </td>
                            <td className="py-2.5 text-right">
                                {(t.status === "failed" ||
                                    t.status === "pending") && (
                                    <button
                                        type="button"
                                        onClick={() => retry(t.id)}
                                        disabled={processingId === t.id}
                                        className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold border-2 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50"
                                    >
                                        <IconRefresh size={12} />
                                        {processingId === t.id
                                            ? "…"
                                            : "Kirim ulang"}
                                    </button>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Bihaju({ summary, outlets }) {
    const [qrBinding, setQrBinding] = useState(null);

    const bindings = summary.bindings || [];
    const transactions = summary.recent_transactions || [];
    const counts = summary.counts || {};

    return (
        <>
            <Head title="Integrasi Bihaju" />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                            Integrasi Bihaju
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Hubungkan outlet ke ekosistem Bihaju untuk
                            komisi & cashback member
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span
                            className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold ${
                                summary.active
                                    ? "bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-400"
                                    : "bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                            }`}
                        >
                            <IconGift size={14} />
                            {summary.active
                                ? "Integrasi aktif"
                                : "Belum dikonfigurasi"}
                        </span>
                    </div>
                </div>

                {!summary.configured && (
                    <div className="bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 rounded-2xl p-4 text-sm text-warning-700 dark:text-warning-300">
                        Partner key Bihaju belum dikonfigurasi. Setel{" "}
                        <code className="font-mono">BIHAJU_PARTNER_KEY</code>{" "}
                        pada environment BPOS.
                    </div>
                )}

                {/* Ringkasan */}
                <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
                    {[
                        { label: "Outlet terhubung", value: counts.active ?? 0 },
                        { label: "Menunggu persetujuan", value: counts.pending ?? 0 },
                        { label: "Transaksi terkirim", value: counts.succeeded ?? 0 },
                        { label: "Gagal / tertunda", value: (counts.failed ?? 0) + (counts.pending_transactions ?? 0) },
                    ].map((stat) => (
                        <div
                            key={stat.label}
                            className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4"
                        >
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {stat.label}
                            </p>
                            <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                                {stat.value}
                            </p>
                        </div>
                    ))}
                </div>

                {/* Binding */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                    <h2 className="text-base font-semibold text-slate-900 dark:text-white mb-1">
                        Binding Outlet
                    </h2>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
                        Satu outlet dapat diikatkan ke satu merchant Bihaju.
                    </p>

                    <OutletBindList
                        outlets={outlets}
                        bindings={bindings}
                        onBind={(b) => setQrBinding(b)}
                    />

                    {bindings.length > 0 && (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-5">
                            {bindings.map((b) => (
                                <BindingCard
                                    key={b.id}
                                    binding={b}
                                    outlets={outlets}
                                    onShowQr={setQrBinding}
                                />
                            ))}
                        </div>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <MemberSearch bindings={bindings} />

                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                        <h3 className="text-sm font-semibold text-slate-900 dark:text-white mb-3">
                            Transaksi Terakhir
                        </h3>
                        <TransactionHistory
                            transactions={transactions}
                            onRetry={() => router.reload({ only: ["summary"] })}
                        />
                    </div>
                </div>

                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 text-sm text-slate-500 dark:text-slate-400 space-y-2">
                    <p className="font-semibold text-slate-700 dark:text-slate-300">
                        Cara kerja
                    </p>
                    <ol className="list-decimal pl-5 space-y-1">
                        <li>Pilih outlet lalu buat QR binding.</li>
                        <li>
                            Scan QR menggunakan aplikasi Bihaju dan setujui
                            permohonannya.
                        </li>
                        <li>
                            BPOS menerima webhook bertanda tangan dan menyimpan
                            kunci merchant secara terenkripsi.
                        </li>
                        <li>
                            Setiap penjualan lunas pada outlet terikat otomatis
                            dikirim (idempoten per transaksi) beserta kode
                            member bila diisi di kasir.
                        </li>
                    </ol>
                </div>
            </div>

            <QrModal binding={qrBinding} onClose={() => setQrBinding(null)} />
        </>
    );
}

Bihaju.layout = (page) => <DashboardLayout children={page} />;

export default Bihaju;

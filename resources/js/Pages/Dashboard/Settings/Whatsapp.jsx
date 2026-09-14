import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, useForm } from "@inertiajs/react";
import { useState, useEffect } from "react";
import toast from "react-hot-toast";
import axios from "axios";
import {
    IconBrandWhatsapp,
    IconPlugConnected,
    IconPlugConnectedX,
    IconCloud,
    IconServer,
    IconEye,
    IconEyeOff,
    IconSend,
    IconExternalLink,
} from "@tabler/icons-react";

export default function Whatsapp({ settings, waStatus }) {
    const { data, setData, post, processing } = useForm({
        wa_provider: settings.wa_provider || "self_hosted",
        wa_service_url: settings.wa_service_url || "",
        wa_fonnte_token: settings.wa_fonnte_token || "",
        wa_enabled: settings.wa_enabled || false,
        wa_auto_reminder: settings.wa_auto_reminder || false,
        wa_auto_invoice: settings.wa_auto_invoice || false,
    });

    const [status, setStatus] = useState(waStatus || { connected: false, phone: null, qr: null, starting: false });
    const [polling, setPolling] = useState(false);
    const [testNumber, setTestNumber] = useState("");
    const [showToken, setShowToken] = useState(false);
    const [testing, setTesting] = useState(false);

    const isFonnte = data.wa_provider === "fonnte";

    useEffect(() => {
        // Polling status hanya untuk self_hosted (Fonnte tidak perlu QR)
        let interval;
        if ((!isFonnte && (polling || status.starting))) {
            interval = setInterval(() => {
                fetchStatus();
            }, 3000);
        }
        return () => clearInterval(interval);
    }, [polling, status.starting, isFonnte]);

    const fetchStatus = async () => {
        try {
            const res = await axios.get(route("settings.whatsapp.status"));
            setStatus(res.data);
            if (res.data.connected) setPolling(false);
        } catch (e) {}
    };

    const handleConnect = async () => {
        try {
            await axios.post(route("settings.whatsapp.start"));
            setPolling(true);
            setStatus((s) => ({ ...s, starting: true }));
        } catch (e) {
            toast.error("Gagal menghubungkan");
        }
    };

    const handleDisconnect = async () => {
        try {
            await axios.post(route("settings.whatsapp.disconnect"));
            setStatus({ connected: false, phone: null, qr: null, starting: false });
            toast.success("Koneksi diputuskan");
        } catch (e) {
            toast.error("Gagal memutuskan koneksi");
        }
    };

    const handleSave = (e) => {
        e.preventDefault();
        post(route("settings.whatsapp.update"), {
            preserveScroll: true,
            onSuccess: () => toast.success("Pengaturan WhatsApp disimpan"),
            onError: () => toast.error("Gagal menyimpan"),
        });
    };

    const handleTest = async () => {
        if (!testNumber) return toast.error("Masukkan nomor tujuan");
        if (isFonnte && !data.wa_fonnte_token) return toast.error("Token Fonnte masih kosong");

        setTesting(true);
        try {
            const res = await axios.post(route("settings.whatsapp.test"), { target: testNumber });
            if (res.data.status) {
                toast.success(`Pesan test terkirim via ${res.data.provider}!`);
            } else {
                toast.error(`Gagal mengirim via ${res.data.provider}. Cek log.`);
            }
        } catch (e) {
            toast.error("Gagal mengirim: " + (e.response?.data?.message || e.message));
        } finally {
            setTesting(false);
        }
    };

    // Token masked preview kalau sudah disimpan
    const tokenMasked = data.wa_fonnte_token
        ? data.wa_fonnte_token.length > 10
            ? `${data.wa_fonnte_token.slice(0, 4)}••••${data.wa_fonnte_token.slice(-4)}`
            : "••••"
        : "";

    return (
        <>
            <Head title="Pengaturan WhatsApp" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <IconBrandWhatsapp size={28} className="text-emerald-500" />
                        WhatsApp Gateway
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Hubungkan WhatsApp untuk kirim pesan otomatis via campaign CRM
                    </p>
                </div>

                {/* Provider Picker */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 max-w-lg">
                    <h2 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
                        Pilih Provider WhatsApp
                    </h2>
                    <div className="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            onClick={() => setData("wa_provider", "self_hosted")}
                            className={`p-4 rounded-xl border-2 text-left transition-all ${
                                !isFonnte
                                    ? "border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20"
                                    : "border-slate-200 dark:border-slate-700 hover:border-slate-300"
                            }`}
                        >
                            <IconServer size={24} className={!isFonnte ? "text-emerald-600" : "text-slate-400"} />
                            <p className="mt-2 text-sm font-semibold text-slate-800 dark:text-white">Self-Hosted</p>
                            <p className="text-xs text-slate-500 mt-0.5">Node.js + whatsapp-web.js (scan QR)</p>
                        </button>

                        <button
                            type="button"
                            onClick={() => setData("wa_provider", "fonnte")}
                            className={`p-4 rounded-xl border-2 text-left transition-all ${
                                isFonnte
                                    ? "border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20"
                                    : "border-slate-200 dark:border-slate-700 hover:border-slate-300"
                            }`}
                        >
                            <IconCloud size={24} className={isFonnte ? "text-emerald-600" : "text-slate-400"} />
                            <p className="mt-2 text-sm font-semibold text-slate-800 dark:text-white">Fonnte Cloud</p>
                            <p className="text-xs text-slate-500 mt-0.5">Cloud API (paste token, tanpa QR)</p>
                        </button>
                    </div>
                </div>

                {/* Status Card — conditional berdasarkan provider */}
                {!isFonnte && (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 max-w-lg">
                        <div className="flex items-center gap-3 mb-4">
                            <div className={`w-3 h-3 rounded-full ${status.connected ? "bg-emerald-500" : status.starting ? "bg-amber-400 animate-pulse" : "bg-slate-300"}`} />
                            <span className="font-medium text-slate-800 dark:text-white">
                                {status.connected
                                    ? `Terhubung (${status.phone})`
                                    : status.starting
                                        ? "Menghubungkan..."
                                        : "Terputus"}
                            </span>
                        </div>

                        {status.qr && !status.connected && (
                            <div className="mb-4 text-center">
                                <img src={status.qr} alt="QR Code" className="mx-auto w-48 h-48" />
                                <p className="text-xs text-slate-400 mt-2">
                                    Scan dengan WhatsApp &gt; Perangkat Tertaut &gt; Perangkat Baru
                                </p>
                            </div>
                        )}

                        {!status.connected && (
                            <button onClick={handleConnect} disabled={processing || status.starting}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-medium transition-colors disabled:opacity-50">
                                <IconPlugConnected size={18} />
                                {status.starting ? "Menghubungkan..." : "Hubungkan WhatsApp"}
                            </button>
                        )}
                        {status.connected && (
                            <button onClick={handleDisconnect} disabled={processing}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-medium transition-colors disabled:opacity-50">
                                <IconPlugConnectedX size={18} />
                                Putuskan Koneksi
                            </button>
                        )}
                    </div>
                )}

                {isFonnte && (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 max-w-lg">
                        <div className="flex items-center gap-3 mb-4">
                            <div className={`w-3 h-3 rounded-full ${data.wa_fonnte_token ? "bg-emerald-500" : "bg-slate-300"}`} />
                            <span className="font-medium text-slate-800 dark:text-white">
                                {data.wa_fonnte_token ? "Fonnte Cloud API Siap" : "Token Belum Diset"}
                            </span>
                        </div>
                        <p className="text-xs text-slate-500">
                            Fonnte adalah cloud API WhatsApp — tidak perlu scan QR, cukup paste token dari dashboard Fonnte.
                        </p>
                        <a href="https://dashboard.fonnte.com/api" target="_blank" rel="noopener noreferrer"
                            className="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-600 hover:text-emerald-700 hover:underline">
                            <IconExternalLink size={14} />
                            Dapatkan token di dashboard.fonnte.com
                        </a>
                    </div>
                )}

                {/* Settings Form */}
                <form onSubmit={handleSave} className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-6 max-w-lg">
                    {/* Conditional fields berdasarkan provider */}
                    {!isFonnte && (
                        <div>
                            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">URL Service WhatsApp</label>
                            <input type="text" value={data.wa_service_url} onChange={(e) => setData("wa_service_url", e.target.value)}
                                className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm"
                                placeholder="http://localhost:3001" />
                            <p className="text-xs text-slate-400 mt-1">Alamat Node.js service whatsapp-web.js</p>
                        </div>
                    )}

                    {isFonnte && (
                        <div>
                            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Token API Fonnte</label>
                            <div className="relative">
                                <input
                                    type={showToken ? "text" : "password"}
                                    value={data.wa_fonnte_token}
                                    onChange={(e) => setData("wa_fonnte_token", e.target.value)}
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 pr-11 text-sm"
                                    placeholder="Tempel token Fonnte di sini…"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowToken(!showToken)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                >
                                    {showToken ? <IconEyeOff size={18} /> : <IconEye size={18} />}
                                </button>
                            </div>
                            {data.wa_fonnte_token && (
                                <p className="mt-1.5 text-xs text-emerald-600">
                                    Token tersimpan: <code className="font-mono">{tokenMasked}</code>
                                </p>
                            )}
                            <p className="text-xs text-slate-400 mt-1">
                                Login ke dashboard Fonnte → menu API → copy token
                            </p>
                        </div>
                    )}

                    <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input type="checkbox" checked={data.wa_enabled} onChange={(e) => setData("wa_enabled", e.target.checked)}
                            className="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                        Aktifkan WhatsApp Gateway
                    </label>

                    <div className="border-t border-slate-100 dark:border-slate-800 pt-4">
                        <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Kirim Otomatis</h3>
                        <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-300 cursor-pointer mb-2">
                            <input type="checkbox" checked={data.wa_auto_reminder} onChange={(e) => setData("wa_auto_reminder", e.target.checked)}
                                className="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                            Kirim reminder piutang otomatis
                        </label>
                        <label className="flex items-center gap-3 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                            <input type="checkbox" checked={data.wa_auto_invoice} onChange={(e) => setData("wa_auto_invoice", e.target.checked)}
                                className="rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                            Kirim invoice setelah transaksi
                        </label>
                    </div>

                    <div className="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="submit" disabled={processing}
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-medium transition-colors disabled:opacity-50">
                            Simpan Pengaturan
                        </button>
                    </div>
                </form>

                {/* Test Send — selalu tampil (kalau token/url diset) */}
                {((isFonnte && data.wa_fonnte_token) || (!isFonnte && status.connected)) && (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 max-w-lg">
                        <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">🧪 Test Kirim Pesan</h3>
                        <p className="text-xs text-slate-400 mb-3">
                            Tes apakah integrasi {isFonnte ? "Fonnte" : "self-hosted"} berfungsi.
                        </p>
                        <div className="flex gap-2">
                            <input type="text" value={testNumber} onChange={(e) => setTestNumber(e.target.value)}
                                placeholder="0812xxxxxxx"
                                className="flex-1 h-11 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm" />
                            <button onClick={handleTest} disabled={testing}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-medium transition-colors disabled:opacity-50">
                                <IconSend size={18} />
                                {testing ? "Mengirim…" : "Kirim"}
                            </button>
                        </div>
                    </div>
                )}

                {/* Info box */}
                <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-4 max-w-lg">
                    <p className="text-xs leading-relaxed text-amber-800 dark:text-amber-200">
                        <strong className="font-bold">Tips memilih provider:</strong>
                        <br />
                        • <strong>Fonnte Cloud</strong> — paling mudah, tinggal daftar di fonnte.com, paste token, langsung jalan. Tarif ~Rp 10–15/pesan, ada 10 pesan gratis.
                        <br />
                        • <strong>Self-Hosted</strong> — gratis tanpa biaya per pesan, tapi perlu VPS terpisah untuk Node.js service yang menjaga sesi WhatsApp Web (scan QR saat sesi hilang).
                    </p>
                </div>
            </div>
        </>
    );
}

Whatsapp.layout = (page) => <DashboardLayout children={page} />;

import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, useForm } from "@inertiajs/react";
import { useState, useEffect } from "react";
import toast from "react-hot-toast";
import axios from "axios";
import { IconBrandWhatsapp, IconPlugConnected, IconPlugConnectedX } from "@tabler/icons-react";

export default function Whatsapp({ settings, waStatus }) {
    const { data, setData, post, processing } = useForm({
        wa_service_url: settings.wa_service_url || "",
        wa_enabled: settings.wa_enabled || false,
        wa_auto_reminder: settings.wa_auto_reminder || false,
        wa_auto_invoice: settings.wa_auto_invoice || false,
    });

    const [status, setStatus] = useState(waStatus || { connected: false, phone: null, qr: null, starting: false });
    const [polling, setPolling] = useState(false);
    const [testNumber, setTestNumber] = useState("");

    useEffect(() => {
        let interval;
        if (polling || status.starting) {
            interval = setInterval(() => {
                fetchStatus();
            }, 3000);
        }
        return () => clearInterval(interval);
    }, [polling, status.starting]);

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
        try {
            await axios.post(route("settings.whatsapp.test"), { target: testNumber });
            toast.success("Pesan test terkirim!");
        } catch (e) {
            toast.error("Gagal mengirim");
        }
    };

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

                {/* Status Card */}
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

                {/* Settings Form */}
                <form onSubmit={handleSave} className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-6 max-w-lg">
                    <div>
                        <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">URL Service WhatsApp</label>
                        <input type="text" value={data.wa_service_url} onChange={(e) => setData("wa_service_url", e.target.value)}
                            className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm"
                            placeholder="http://localhost:3001" />
                        <p className="text-xs text-slate-400 mt-1">Alamat Node.js service whatsapp-web.js</p>
                    </div>

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

                {/* Test Send */}
                {status.connected && (
                    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 max-w-lg">
                        <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Test Kirim Pesan</h3>
                        <div className="flex gap-2">
                            <input type="text" value={testNumber} onChange={(e) => setTestNumber(e.target.value)}
                                placeholder="0812xxxxxxx"
                                className="flex-1 h-11 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm" />
                            <button onClick={handleTest} disabled={processing}
                                className="px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-medium transition-colors disabled:opacity-50">
                                Kirim
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Whatsapp.layout = (page) => <DashboardLayout children={page} />;

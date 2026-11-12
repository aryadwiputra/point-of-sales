import { Head, usePage } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";
import { IconShieldLock } from "@tabler/icons-react";

export default function Security() {
    const { brand } = usePage().props;
    const name = brand?.name || "Bihaju POS";
    const email = brand?.supportEmail || "support@bihajupos.id";
    return (
        <PublicLayout>
            <Head title={`Keamanan — ${name}`} />
            <section className="pt-20 pb-16 px-6">
                <div className="max-w-3xl mx-auto">
                    <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 text-sm font-medium mb-5 border border-primary-100 dark:border-primary-900">
                        <IconShieldLock size={16} /> Keamanan & Kepercayaan
                    </div>
                    <div className="prose prose-slate dark:prose-invert">
                        <h1>Keamanan & Perlindungan Data</h1>
                        <p>{name} dirancang untuk membantu operasional usaha dengan kontrol akses dan praktik keamanan yang wajar untuk layanan SaaS.</p>
                        <h2>Kontrol Akses</h2>
                        <ul>
                            <li>Manajemen peran/izin (RBAC) dan pembatasan akses per konteks operasional.</li>
                            <li>Konfirmasi ulang (step-up) untuk aksi sensitif tertentu.</li>
                            <li>Pencatatan aktivitas (audit log) untuk perubahan penting.</li>
                        </ul>
                        <h2>Perlindungan Data</h2>
                        <ul>
                            <li>Isolasi data per tenant dan validasi entitlement per akun/tenant.</li>
                            <li>Pengiriman data melalui koneksi aman (HTTPS) pada lingkungan produksi.</li>
                            <li>Enkripsi/penyimpanan rahasia kredensial integrasi sesuai praktik platform.</li>
                        </ul>
                        <h2>Integrasi</h2>
                        <p>Integrasi dan add-on pihak ketiga (mis. pembayaran, komunikasi, modul tambahan) diaktifkan per tenant dan diproses sesuai konfigurasi serta kewenangan yang diberikan.</p>
                        <h2>Pelaporan Kerentanan</h2>
                        <p>Jika Anda menemukan potensi masalah keamanan, laporkan secara bertanggung jawab ke <a href={`mailto:${email}`}>{email}</a> dengan detail yang cukup untuk investigasi.</p>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

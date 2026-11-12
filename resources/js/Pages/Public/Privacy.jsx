import { Head, usePage } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";

export default function Privacy() {
    const { brand } = usePage().props;
    const name = brand?.name || "Bihaju POS";
    const company = brand?.legalName || brand?.company || name;
    const email = brand?.supportEmail || "support@bihajupos.id";
    return (
        <PublicLayout>
            <Head title={`Kebijakan Privasi — ${name}`} />
            <section className="pt-20 pb-16 px-6">
                <div className="max-w-3xl mx-auto prose prose-slate dark:prose-invert">
                    <h1>Kebijakan Privasi</h1>
                    <p>Kebijakan ini menjelaskan bagaimana {company} mengumpulkan, menggunakan, dan melindungi data dalam penggunaan {name}.</p>
                    <h2>1. Data yang Kami Proses</h2>
                    <ul>
                        <li><strong>Data akun & tenant:</strong> nama, email, peran, konfigurasi toko/outlet, dan preferensi.</li>
                        <li><strong>Data operasional:</strong> produk, transaksi, pelanggan, inventori, laporan, dan data usaha lain yang Anda masukkan.</li>
                        <li><strong>Data teknis:</strong> log akses, informasi perangkat/browser, dan cookie/sesi yang diperlukan untuk keamanan dan fungsi Layanan.</li>
                    </ul>
                    <h2>2. Tujuan Pemrosesan</h2>
                    <p>Untuk menyediakan dan memelihara Layanan, autentikasi, dukungan, pemrosesan langganan/add-on, keamanan, pencegahan penyalahgunaan, serta peningkatan fitur sesuai kebutuhan operasional.</p>
                    <h2>3. Berbagi Data</h2>
                    <p>Kami tidak menjual data Anda. Data dapat diproses oleh subprosesor/integrasi yang Anda aktifkan (mis. penyedia pembayaran, komunikasi, atau add-on) sebatas yang diperlukan untuk menjalankan fungsi tersebut.</p>
                    <h2>4. Keamanan & Retensi</h2>
                    <p>Kami menerapkan langkah keamanan yang wajar secara teknis dan organisasi. Data disimpan selama akun/tenant aktif atau sesuai kebutuhan Layanan dan kewajiban hukum, lalu dapat dihapus atau dianonimkan sesuai kebijakan retensi.</p>
                    <h2>5. Hak & Kontrol</h2>
                    <p>Pengelola tenant dapat mengelola pengguna, akses, dan data operasional. Permintaan terkait akses, koreksi, ekspor, atau penghapusan data dapat diajukan melalui <a href={`mailto:${email}`}>{email}</a>, tunduk pada verifikasi dan kewajiban hukum.</p>
                    <h2>6. Cookie</h2>
                    <p>Kami menggunakan cookie/sesi yang diperlukan untuk autentikasi, preferensi, dan keamanan. Anda dapat mengatur preferensi melalui browser, namun beberapa fungsi mungkin tidak berjalan optimal.</p>
                    <h2>7. Perubahan</h2>
                    <p>Kebijakan ini dapat diperbarui dari waktu ke waktu. Versi terbaru akan tersedia pada halaman ini.</p>
                </div>
            </section>
        </PublicLayout>
    );
}

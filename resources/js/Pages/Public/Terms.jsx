import { Head } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";
import { usePage } from "@inertiajs/react";

export default function Terms() {
    const { brand } = usePage().props;
    const name = brand?.name || "Bihaju POS";
    const company = brand?.legalName || brand?.company || name;
    const email = brand?.supportEmail || "support@bihajupos.id";
    return (
        <PublicLayout>
            <Head title={`Syarat Layanan — ${name}`} />
            <section className="pt-20 pb-16 px-6">
                <div className="max-w-3xl mx-auto prose prose-slate dark:prose-invert">
                    <h1>Syarat Layanan</h1>
                    <p>Terakhir diperbarui: {new Date().getFullYear()}.</p>
                    <p>Syarat Layanan ini mengatur penggunaan layanan {name} ("Layanan") yang disediakan oleh {company}. Dengan membuat akun atau menggunakan Layanan, Anda menyetujui syarat ini.</p>
                    <h2>1. Akun & Akses</h2>
                    <p>Anda bertanggung jawab menjaga kerahasiaan kredensial, aktivitas pada akun, serta memastikan pengguna yang diberi akses memiliki kewenangan yang sesuai. Informasi pendaftaran harus akurat dan mutakhir.</p>
                    <h2>2. Penggunaan yang Diizinkan</h2>
                    <p>Layanan digunakan untuk operasional usaha yang sah. Anda tidak boleh menyalahgunakan Layanan, mengganggu sistem, mencoba akses tidak sah, atau melanggar hukum yang berlaku.</p>
                    <h2>3. Langganan, Add-on, dan Pembayaran</h2>
                    <p>Fitur, batas paket, dan add-on (mis. modul terintegrasi) mengikuti paket langganan/entitlement akun/tenant Anda. Biaya, siklus tagihan, dan ketentuan aktivasi ditampilkan pada saat pembelian/aktivasi. Kegagalan bayar dapat menyebabkan pembatasan akses sesuai kebijakan paket.</p>
                    <h2>4. Data & Kepemilikan</h2>
                    <p>Data operasional yang Anda masukkan tetap menjadi milik Anda/tenant Anda. Kami memproses data tersebut untuk menyediakan Layanan, sesuai Kebijakan Privasi. Anda bertanggung jawab atas keakuratan data dan kepatuhan penggunaan data pihak ketiga.</p>
                    <h2>5. Ketersediaan & Perubahan Layanan</h2>
                    <p>Kami berupaya menjaga ketersediaan Layanan, namun dapat melakukan pemeliharaan, pembaruan, atau perubahan fitur. Target tingkat layanan (SLA), jika ada, diatur dalam paket/perjanjian terpisah.</p>
                    <h2>6. Integrasi & Pihak Ketiga</h2>
                    <p>Layanan dapat terhubung dengan penyedia pihak ketiga (mis. pembayaran, komunikasi, add-on). Penggunaan layanan pihak ketiga tunduk pada syarat masing-masing penyedia.</p>
                    <h2>7. Penghentian</h2>
                    <p>Anda dapat berhenti menggunakan Layanan sesuai ketentuan akun. Kami dapat menangguhkan atau menghentikan akses jika terjadi pelanggaran syarat, risiko keamanan, atau kewajiban hukum.</p>
                    <h2>8. Batasan Tanggung Jawab</h2>
                    <p>Sejauh diizinkan hukum, Layanan disediakan "sebagaimana adanya". Kami tidak bertanggung jawab atas kerugian tidak langsung, kehilangan keuntungan, atau gangguan yang di luar kendali wajar kami.</p>
                    <h2>9. Kontak</h2>
                    <p>Pertanyaan terkait syarat ini dapat disampaikan ke <a href={`mailto:${email}`}>{email}</a>.</p>
                </div>
            </section>
        </PublicLayout>
    );
}

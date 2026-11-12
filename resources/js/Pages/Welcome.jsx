import { Head, Link, usePage } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";
import {
    IconShoppingCart,
    IconWallet,
    IconBuildingWarehouse,
    IconReceiptTax,
    IconChartBar,
    IconReportMoney,
    IconUsers,
    IconBrandWhatsapp,
    IconShieldLock,
    IconCloudOff,
    IconArrowRight,
    IconQrcode,
    IconApi,
} from "@tabler/icons-react";

const stats = [
    { value: "Kasir", label: "Cepat & Andal" },
    { value: "Multi", label: "Outlet & Gudang" },
    { value: "Add-on", label: "Siap Terintegrasi" },
    { value: "SaaS", label: "Aman per Tenant" },
];

const features = [
    {
        icon: IconShoppingCart,
        title: "POS Cepat & Mudah",
        desc: "Cari produk via barcode atau keyboard, scan pakai kamera (PWA), cart hold/resume, dan checkout dalam hitungan detik.",
    },
    {
        icon: IconWallet,
        title: "Multi-Payment",
        desc: "Tunai, transfer bank, QRIS (Midtrans), Xendit, hingga pay later (piutang) — semua dalam satu kasir.",
    },
    {
        icon: IconBuildingWarehouse,
        title: "Multi-Warehouse",
        desc: "Stok terpisah per gudang/cabang, transfer antar gudang, stock opname, dan tracking batch/expiry (FEFO).",
    },
    {
        icon: IconReceiptTax,
        title: "PPN & Pajak",
        desc: "Dukungan PPN 11% (exclusive/inclusive), data NPWP pelanggan, dan laporan pajak yang rapi.",
    },
    {
        icon: IconChartBar,
        title: "Laporan & Insight",
        desc: "Laporan penjualan, profit & margin, performa per kasir, jam sibuk, dan repeat customer.",
    },
    {
        icon: IconReportMoney,
        title: "Piutang & Hutang",
        desc: "Kelola piutang pelanggan & hutang supplier dengan aging analysis dan partial payment.",
    },
    {
        icon: IconUsers,
        title: "CRM & Loyalty",
        desc: "Member tiers, poin loyalty, voucher, segmentasi pelanggan otomatis, dan campaign marketing.",
    },
    {
        icon: IconBrandWhatsapp,
        title: "WhatsApp Gateway",
        desc: "Kirim struk, pengingat, dan informasi ke pelanggan melalui kanal komunikasi yang terhubung.",
    },
    {
        icon: IconShieldLock,
        title: "RBAC & Audit Log",
        desc: "Kontrol akses per role (admin/kasir), persetujuan diskon, dan jejak audit before/after setiap perubahan.",
    },
    {
        icon: IconCloudOff,
        title: "Offline Mode",
        desc: "Checkout yang sudah disiapkan dapat masuk antrean offline dan tersinkron saat koneksi kembali.",
    },
    {
        icon: IconQrcode,
        title: "Dine-in QR Menu",
        desc: "Pelanggan scan QR meja, melihat menu, membuat pesanan, dan memantau status sampai diproses staff.",
    },
    {
        icon: IconApi,
        title: "API & Integrasi",
        desc: "API terautentikasi untuk master data, POS, checkout, shift, transaksi, dan sinkronisasi offline.",
    },
];

const techStack = [
    { name: "POS & Kasir", color: "bg-primary-500" },
    { name: "Inventori", color: "bg-emerald-500" },
    { name: "Laporan", color: "bg-cyan-500" },
    { name: "Pelanggan & CRM", color: "bg-amber-500" },
    { name: "Multi-Outlet", color: "bg-orange-500" },
    { name: "Add-on & Integrasi", color: "bg-purple-500" },
];

const screenshots = [
    { src: "/screenshots/01-dashboard.png", title: "Dashboard", span: "col-span-2 row-span-2" },
    { src: "/screenshots/02-pos-checkout.png", title: "POS Checkout" },
    { src: "/screenshots/06-stock-opnames.png", title: "Stock Opname" },
    { src: "/screenshots/12-receivables.png", title: "Receivables" },
    { src: "/screenshots/15-sales-report.png", title: "Sales Report" },
];

const faqs = [
    {
        q: "Untuk siapa Bihaju POS cocok digunakan?",
        a: "Bihaju POS cocok untuk berbagai jenis usaha — ritel, F&B, jasa, dan operasional multi-outlet — yang membutuhkan kasir, inventori, laporan, dan pengelolaan pelanggan dalam satu platform.",
    },
    {
        q: "Bisakah dipakai untuk bisnis multi-cabang?",
        a: "Bisa. Platform ini mendukung multi-outlet/multi-gudang dengan stok terpisah, transfer antar lokasi, dan kontrol operasional per cabang.",
    },
    {
        q: "Bagaimana kalau internet di toko mati?",
        a: "Transaksi yang sudah disiapkan dapat diantrikan secara lokal dan disinkronkan saat koneksi kembali tersedia, sesuai kemampuan mode offline yang aktif.",
    },
    {
        q: "Bagaimana sistem langganan dan add-on?",
        a: "Akses fitur dan add-on (seperti modul terintegrasi) mengikuti paket/entitlement akun atau tenant Anda. Hak akses divalidasi per akun/tenant agar penggunaan sesuai langganan yang aktif.",
    },
    {
        q: "Ada paket tanpa biaya bulanan?",
        a: "Ada. Paket Bayar Per Transaksi tidak memungut biaya bulanan — Anda hanya dikenakan biaya kecil per transaksi (mis. 1,5% dengan minimum Rp500 dan maksimum Rp2.000, bisa diatur platform). Paket ini membuka fitur inti POS: kasir, shift, struk, pelanggan, dan laporan dasar.",
    },
    {
        q: "Apakah data dan akses aman?",
        a: "Akses dikelola per akun/tenant dengan kontrol peran dan izin. Entitlement add-on divalidasi di sisi layanan, dan praktik keamanan diterapkan untuk melindungi operasional usaha Anda.",
    },
];

export default function Welcome() {
    const { brand } = usePage().props;
    const brandName = brand?.name || "Bihaju POS";
    const brandTagline = brand?.tagline || "Bikin Usaha Maju";
    return (
        <PublicLayout>
            <Head title="Bihaju POS — Bikin Usaha Maju" />

            {/* ============ HERO ============ */}
            <section className="pt-28 pb-16 px-6">
                <div className="max-w-7xl mx-auto">
                    <div className="text-center max-w-4xl mx-auto">
                        <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 text-sm font-medium mb-6 border border-primary-100 dark:border-primary-900">
<IconShoppingCart size={16} />
                            Platform POS SaaS · Bikin Usaha Maju
                        </div>

                        <h1 className="text-5xl md:text-6xl font-extrabold text-slate-900 dark:text-white leading-tight">
                            Kelola Usaha Lebih Maju
                            <span className="block mt-2 bg-gradient-to-r from-primary-500 to-primary-600 bg-clip-text text-transparent">
                                dengan {brandName}
                            </span>
                        </h1>

                        <p className="mt-6 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                            {brandName} adalah platform kasir (POS) SaaS untuk membantu operasional usaha lebih rapi, cepat, dan terukur — dari transaksi, inventori, pelanggan, hingga laporan dan integrasi add-on sesuai kebutuhan.
                        </p>

                        <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                            <a
                                href="/login"
                                className="w-full sm:w-auto px-8 py-4 text-base font-semibold text-white bg-gradient-to-r from-primary-500 to-primary-600 rounded-2xl hover:from-primary-600 hover:to-primary-700 shadow-xl shadow-primary-500/30 transition-all flex items-center justify-center gap-2"
                            >
                                Mulai Sekarang
                                <IconArrowRight size={18} />
                            </a>
                            <Link
                                href="/login"
                                className="w-full sm:w-auto px-8 py-4 text-base font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl hover:border-primary-300 dark:hover:border-primary-700 transition-all flex items-center justify-center gap-2"
                            >
                                <IconArrowRight size={20} />
Masuk ke Akun
                            </Link>
                        </div>
                    </div>

                    {/* App preview */}
                    <div className="mt-16 relative">
                        <div className="absolute inset-0 bg-gradient-to-t from-slate-50 dark:from-slate-950 to-transparent z-10 pointer-events-none h-32 bottom-0 top-auto" />
                        <div className="rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-800 shadow-2xl bg-white dark:bg-slate-900">
                            <div className="bg-slate-100 dark:bg-slate-800 px-4 py-3 flex items-center gap-2">
                                <div className="flex gap-2">
                                    <div className="w-3 h-3 rounded-full bg-red-400" />
                                    <div className="w-3 h-3 rounded-full bg-yellow-400" />
                                    <div className="w-3 h-3 rounded-full bg-green-400" />
                                </div>
                                <div className="flex-1 text-center text-xs text-slate-500">
Bihaju POS
                                </div>
                            </div>
                            <img
                                src="/media/revamp-pos.png"
                                alt={`Tampilan ${brandName}`}
                                className="w-full"
                                loading="lazy"
                            />
                        </div>
                    </div>
                </div>
            </section>

            {/* ============ STATS ============ */}
            <section className="py-12 px-6 border-y border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/50">
                <div className="max-w-5xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-8">
                    {stats.map((stat) => (
                        <div key={stat.label} className="text-center">
                            <div className="text-3xl md:text-4xl font-extrabold text-primary-600 dark:text-primary-400">
                                {stat.value}
                            </div>
                            <div className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {stat.label}
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {/* ============ SCREENSHOTS ============ */}
            <section id="screenshot" className="py-20 px-6">
                <div className="max-w-7xl mx-auto">
                    <div className="text-center mb-14">
                        <h2 className="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white">
                            Tampilan Aplikasi
                        </h2>
                        <p className="mt-4 text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                            Dari kasir harian hingga laporan manajemen — semua dalam satu platform yang rapi dan mudah digunakan.
                        </p>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4 auto-rows-[140px] md:auto-rows-[180px]">
                        {screenshots.map((shot) => (
                            <div
                                key={shot.title}
                                className={`${shot.span || ""} relative rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 group`}
                            >
                                <img
                                    src={shot.src}
                                    alt={shot.title}
                                    className="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-300"
                                    loading="lazy"
                                />
                                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-3 py-2">
                                    <span className="text-xs font-medium text-white">
                                        {shot.title}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
</div>
            </section>

            {/* ============ FEATURES ============ */}
            <section id="fitur" className="py-20 px-6 bg-white dark:bg-slate-900/50 border-y border-slate-200 dark:border-slate-800">
                <div className="max-w-7xl mx-auto">
                    <div className="text-center mb-16">
                        <h2 className="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white">
                            Fitur Lengkap untuk Bisnis Nyata
                        </h2>
                        <p className="mt-4 text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                            Fitur terintegrasi untuk operasional harian hingga analitik — dirancang membantu usaha bertumbuh dengan alur kerja yang rapi.
                        </p>
                    </div>

                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {features.map((feature) => (
                            <div
                                key={feature.title}
                                className="group p-6 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 hover:border-primary-200 dark:hover:border-primary-800 hover:shadow-lg hover:shadow-primary-500/5 transition-all"
                            >
                                <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                    <feature.icon size={24} className="text-white" />
                                </div>
                                <h3 className="text-lg font-semibold text-slate-900 dark:text-white mb-2">
                                    {feature.title}
                                </h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                                    {feature.desc}
                                </p>
                            </div>
                        ))}
                    </div>

                    <div className="text-center mt-10">
                        <Link
                            href="/fitur"
                            className="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-800 rounded-xl hover:bg-primary-50 dark:hover:bg-primary-950/40 transition-colors"
                        >
                            Jelajahi semua fitur
                            <IconArrowRight size={16} />
                        </Link>
                    </div>
                </div>
            </section>

            {/* ============ TECH STACK ============ */}
            <section className="py-16 px-6">
                <div className="max-w-4xl mx-auto text-center">
                    <h2 className="text-3xl font-bold text-slate-900 dark:text-white mb-4">
Solusi Lengkap untuk Operasional
                    </h2>
                    <p className="text-slate-600 dark:text-slate-400 mb-10">
Modul-modul utama yang saling terhubung untuk mendukung operasional dan pertumbuhan usaha Anda
                    </p>
                    <div className="flex flex-wrap justify-center gap-4">
                        {techStack.map((tech) => (
                            <div
                                key={tech.name}
                                className="flex items-center gap-3 px-6 py-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700"
                            >
                                <div className={`w-3 h-3 rounded-full ${tech.color}`} />
                                <span className="font-medium text-slate-700 dark:text-slate-300">
                                    {tech.name}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ============ PAKET & LANGGANAN ============ */}
            <section id="paket" className="py-20 px-6 bg-white dark:bg-slate-900/50 border-y border-slate-200 dark:border-slate-800">
                <div className="max-w-5xl mx-auto text-center">
                    <h2 className="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white">Paket & Langganan Fleksibel</h2>
                    <p className="mt-4 text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                        Pilih paket sesuai skala usaha Anda. Akses fitur dan add-on (seperti modul integrasi) mengikuti entitlement akun/tenant dan dapat disesuaikan saat bisnis berkembang.
                    </p>
                    <div className="mt-10 grid md:grid-cols-2 lg:grid-cols-4 gap-6 text-left">
                        <div className="p-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                            <div className="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-warning-100 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300 mb-2">Baru</div>
                            <h3 className="text-lg font-semibold text-slate-900 dark:text-white">Bayar Per Transaksi</h3>
                            <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">Tanpa biaya bulanan — bayar hanya saat ada penjualan.</p>
                            <ul className="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                                <li>• Biaya 1,5% / transaksi (min Rp500, maks Rp2.000)</li>
                                <li>• Fitur inti POS, shift, & struk</li>
                                <li>• Laporan dasar & pelanggan</li>
                            </ul>
                        </div>
                        <div className="p-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                            <h3 className="text-lg font-semibold text-slate-900 dark:text-white">Mulai</h3>
                            <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">Untuk operasional kasir dan pencatatan dasar yang rapi.</p>
                            <ul className="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                                <li>• Transaksi & kasir</li>
                                <li>• Produk & laporan dasar</li>
                                <li>• Akses aman per akun</li>
                            </ul>
                        </div>
                        <div className="p-6 rounded-2xl border-2 border-primary-300 dark:border-primary-700 bg-white dark:bg-slate-900 shadow-lg shadow-primary-500/10">
                            <div className="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 mb-2">Populer</div>
                            <h3 className="text-lg font-semibold text-slate-900 dark:text-white">Berkembang</h3>
                            <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">Untuk usaha yang butuh kontrol stok, pelanggan, dan multi-outlet.</p>
                            <ul className="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                                <li>• Inventori & multi-outlet</li>
                                <li>• CRM & promosi</li>
                                <li>• Laporan & insight</li>
                            </ul>
                        </div>
                        <div className="p-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
                            <h3 className="text-lg font-semibold text-slate-900 dark:text-white">Scale & Add-on</h3>
                            <p className="mt-2 text-sm text-slate-600 dark:text-slate-400">Untuk kebutuhan lanjutan dengan integrasi dan modul tambahan.</p>
                            <ul className="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                                <li>• Add-on terintegrasi (per tenant)</li>
                                <li>• Kontrol & visibilitas lanjutan</li>
                                <li>• Dukungan prioritas</li>
                            </ul>
                        </div>
                    </div>
                    <div className="mt-10">
                        <Link href="/login" className="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold rounded-2xl hover:from-primary-600 hover:to-primary-700 shadow-lg shadow-primary-500/25 transition-all">
                            Lihat & Mulai Berlangganan
                            <IconArrowRight size={18} />
                        </Link>
                    </div>
                </div>
            </section>

            {/* ============ FAQ ============ */}
            <section id="faq" className="py-20 px-6 bg-white dark:bg-slate-900/50 border-y border-slate-200 dark:border-slate-800">
                <div className="max-w-3xl mx-auto">
                    <div className="text-center mb-12">
                        <h2 className="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white">
                            Pertanyaan Umum
                        </h2>
                    </div>
                    <div className="space-y-4">
                        {faqs.map((faq) => (
                            <details
                                key={faq.q}
                                className="group rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 open:shadow-md transition-all"
                            >
                                <summary className="flex items-center justify-between gap-4 px-5 py-4 cursor-pointer list-none">
                                    <span className="font-medium text-slate-900 dark:text-white">
                                        {faq.q}
                                    </span>
                                    <span className="text-primary-500 group-open:rotate-45 transition-transform text-lg">
                                        +
                                    </span>
                                </summary>
                                <p className="px-5 pb-5 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                                    {faq.a}
                                </p>
                            </details>
                        ))}
                    </div>
                </div>
            </section>

            {/* ============ CTA ============ */}
            <section className="py-20 px-6">
                <div className="max-w-4xl mx-auto">
                    <div className="bg-gradient-to-r from-primary-500 to-primary-600 rounded-3xl p-12 text-center text-white">
                        <h2 className="text-3xl md:text-4xl font-bold mb-4">
                            Siap Bikin Usaha Maju?
                        </h2>
                        <p className="text-lg opacity-90 mb-8 max-w-xl mx-auto">
                            Mulai kelola operasional usaha dengan {brandName} — platform kasir SaaS yang membantu Anda bekerja lebih rapi, cepat, dan siap bertumbuh.
                        </p>
                        <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
                            <a
                                href="/fitur"
                                className="inline-flex items-center gap-2 px-8 py-4 bg-white text-primary-600 font-semibold rounded-2xl hover:bg-slate-50 transition-colors"
                            >
                                Lihat Fitur
                            </a>
                            <Link
                                href="/login"
                                className="inline-flex items-center gap-2 px-8 py-4 border border-white/40 text-white font-semibold rounded-2xl hover:bg-white/10 transition-colors"
                            >
                                Masuk / Mulai
                                <IconArrowRight size={18} />
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

import { Head } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";
import { IconBook2, IconArrowRight, IconBrandGithub } from "@tabler/icons-react";

const GITHUB_URL = "https://github.com/aryadwiputra/point-of-sales";
const DOCS_BASE = `${GITHUB_URL}/blob/main/docs`;

const categories = [
    {
        title: "Mulai Cepat",
        docs: [
            { file: "getting-started.md", title: "Getting Started", desc: "Panduan setup lengkap dari nol sampai bisa login & mengakses dashboard." },
            { file: "configuration.md", title: "Konfigurasi", desc: "Environment, payment gateway, pajak, printer thermal, dan WhatsApp." },
            { file: "architecture-overview.md", title: "Arsitektur", desc: "Struktur kode, service layer, middleware, dan Node service." },
            { file: "feature-index.md", title: "Indeks Fitur", desc: "Daftar semua 44+ modul dan statusnya." },
        ],
    },
    {
        title: "POS & Transaksi",
        docs: [
            { file: "features/pos-transactions.md", title: "Transaksi POS", desc: "Alur cart, checkout, hold/resume, dan multi-payment." },
            { file: "features/cashier-shifts.md", title: "Shift Kasir", desc: "Buka/tutup shift dan rekap kas per shift." },
            { file: "features/sales-returns.md", title: "Retur Penjualan", desc: "Proses retur dari transaksi yang sudah jadi." },
            { file: "features/mobile-pos.md", title: "Mobile POS (PWA)", desc: "Gunakan kasir dari HP — installable dan offline-ready." },
            { file: "features/thermal-printer.md", title: "Printer Thermal", desc: "Cetak struk 58/80mm via WebUSB." },
        ],
    },
    {
        title: "Inventory & Warehouse",
        docs: [
            { file: "features/inventory-stock.md", title: "Inventory & Stok", desc: "Produk, kategori, stock opname, dan mutasi stok." },
            { file: "features/multi-warehouse.md", title: "Multi-Warehouse", desc: "Stok per gudang dan transfer antar gudang." },
            { file: "features/unit-conversion.md", title: "Multi-Satuan", desc: "Konversi satuan produk (pcs, box, kg, karton)." },
        ],
    },
    {
        title: "Purchasing & Finance",
        docs: [
            { file: "features/purchasing-chain.md", title: "Rantai Pengadaan", desc: "Purchase order, goods receiving, dan supplier return." },
            { file: "features/payables-suppliers.md", title: "Supplier & Payables", desc: "Kelola supplier dan hutang." },
            { file: "features/receivables.md", title: "Receivables", desc: "Piutang pelanggan dan pembayaran parsial." },
            { file: "features/tax-management.md", title: "Manajemen Pajak", desc: "PPN, NPWP, dan NIB." },
            { file: "features/customer-portal.md", title: "Customer Portal", desc: "Portal self-service: lihat invoice & bayar online." },
        ],
    },
    {
        title: "CRM & Loyalty",
        docs: [
            { file: "features/crm-segments.md", title: "Segmen & Campaign", desc: "Segmentasi otomatis dan campaign marketing." },
            { file: "features/member-management.md", title: "Member Management", desc: "Tier member dan poin loyalty." },
            { file: "features/promotions-loyalty.md", title: "Promo & Loyalty", desc: "Pricing rules, voucher, dan program loyalty." },
        ],
    },
    {
        title: "Admin & Tools",
        docs: [
            { file: "features/rbac-users-roles.md", title: "RBAC", desc: "User, role, dan permission." },
            { file: "features/audit-logs.md", title: "Audit Log", desc: "Jejak perubahan before/after." },
            { file: "features/settings-payments.md", title: "Payment Settings", desc: "Midtrans, Xendit, dan bank accounts." },
            { file: "features/import-export.md", title: "Import/Export", desc: "Produk & customer via Excel." },
            { file: "features/reports-documents.md", title: "Reports & Documents", desc: "Laporan dan dokumen PDF." },
            { file: "features/whatsapp-gateway.md", title: "WhatsApp Gateway", desc: "Integrasi whatsapp-web.js." },
        ],
    },
];

export default function Documentation() {
    return (
        <PublicLayout active="/dokumentasi">
            <Head title="Dokumentasi — Dikasir" />

            {/* Header */}
            <section className="pt-20 pb-14 px-6 bg-gradient-to-b from-primary-50 dark:from-primary-950/40 to-transparent">
                <div className="max-w-4xl mx-auto text-center">
                    <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 text-sm font-medium mb-5 border border-primary-100 dark:border-primary-900">
                        <IconBook2 size={16} />
                        Dokumentasi
                    </div>
                    <h1 className="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-white">
                        Dokumentasi Lengkap
                    </h1>
                    <p className="mt-5 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                        Semua panduan tersedia di repository GitHub — selalu terbaru, ikut
                        berkembang bersama kode.
                    </p>
                </div>
            </section>

            {/* Categories */}
            <section className="pb-20 px-6">
                <div className="max-w-5xl mx-auto space-y-14">
                    {categories.map((cat) => (
                        <div key={cat.title}>
                            <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-5 flex items-center gap-3">
                                <span className="w-8 h-1 rounded-full bg-gradient-to-r from-primary-500 to-primary-600" />
                                {cat.title}
                            </h2>
                            <div className="grid sm:grid-cols-2 gap-4">
                                {cat.docs.map((doc) => (
                                    <a
                                        key={doc.file}
                                        href={`${DOCS_BASE}/${doc.file}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="group p-5 rounded-xl bg-white dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 hover:border-primary-300 dark:hover:border-primary-700 hover:shadow-md transition-all"
                                    >
                                        <div className="flex items-center justify-between mb-2">
                                            <h3 className="font-semibold text-slate-900 dark:text-white group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                                {doc.title}
                                            </h3>
                                            <IconArrowRight size={16} className="text-slate-400 group-hover:text-primary-500 group-hover:translate-x-0.5 transition-all" />
                                        </div>
                                        <p className="text-sm text-slate-500 dark:text-slate-400">
                                            {doc.desc}
                                        </p>
                                    </a>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {/* CTA */}
            <section className="pb-20 px-6">
                <div className="max-w-3xl mx-auto">
                    <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 p-8 text-center">
                        <h2 className="text-xl font-bold text-slate-900 dark:text-white mb-2">
                            Dokumentasi kurang jelas?
                        </h2>
                        <p className="text-slate-600 dark:text-slate-400 mb-6">
                            Dokumentasi juga open source — perbaiki dan buat PR, atau tanya di
                            GitHub Discussions.
                        </p>
                        <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
                            <a
                                href={`${GITHUB_URL}/blob/main/docs/README.md`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold rounded-xl hover:from-primary-600 hover:to-primary-700 transition-all"
                            >
                                <IconBrandGithub size={18} />
                                Lihat semua docs di GitHub
                            </a>
                            <a
                                href={`${GITHUB_URL}/discussions`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl hover:border-primary-300 transition-colors"
                            >
                                Tanya di Discussions
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

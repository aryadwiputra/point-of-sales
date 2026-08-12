import { Head, Link } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";
import {
    IconRoute,
    IconRocket,
    IconSparkles,
    IconArrowRight,
    IconBrandGithub,
    IconCheck,
    IconBulb,
} from "@tabler/icons-react";

const GITHUB_URL = "https://github.com/aryadwiputra/point-of-sales";

const releases = [
    {
        version: "v2.0",
        tag: "Revamp besar",
        date: "2025",
        items: [
            "Redesign UI modern & responsive",
            "33 screenshot dokumentasi semua modul",
            "Fondasi arsitektur service layer",
        ],
    },
    {
        version: "v2.1",
        tag: "Komunikasi",
        date: "2025",
        items: [
            "WhatsApp Gateway via whatsapp-web.js",
            "App versioning terpusat (APP_VERSION)",
            "Notifikasi low stock & aging",
        ],
    },
    {
        version: "v2.2",
        tag: "Upgrade Stack",
        date: "2026",
        items: [
            "Upgrade Laravel 12 → 13",
            "Inertia v3 + React 19",
            "Multi-language (Indonesia & English)",
        ],
    },
    {
        version: "v2.3",
        tag: "Penguatan POS",
        date: "2026",
        items: [
            "Perbaikan cart & penanganan shift",
            "Fallback stok per warehouse",
            "Dokumentasi lengkap per modul",
        ],
    },
    {
        version: "v2.3.1",
        tag: "Rilis saat ini",
        date: "Agu 2026",
        items: [
            "Rilis pemeliharaan & penyempurnaan",
            "CI/CD pipeline (build + auto-deploy)",
            "Landing page & situs publik baru",
            "Perbaikan portal customer & invoice PDF",
        ],
    },
];

const directions = [
    {
        icon: IconSparkles,
        title: "Pengalaman mobile yang lebih dalam",
        desc: "Kasir handheld yang lebih matang: mode offline penuh, antrean sinkronisasi yang lebih cerdas, dan UI sentuh yang dioptimalkan.",
    },
    {
        icon: IconBulb,
        title: "Integrasi ekosistem",
        desc: "Ekspansi payment gateway & kurir pengiriman, konektor akuntansi, dan integrasi e-commerce.",
    },
    {
        icon: IconRocket,
        title: "Ekosistem pengembang",
        desc: "Dokumentasi API, tema & plugin, dan tooling yang memudahkan kontribusi.",
    },
];

export default function Roadmap() {
    return (
        <PublicLayout active="/roadmap">
            <Head title="Roadmap — Dikasir" />

            {/* Header */}
            <section className="pt-20 pb-14 px-6 bg-gradient-to-b from-primary-50 dark:from-primary-950/40 to-transparent">
                <div className="max-w-4xl mx-auto text-center">
                    <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 text-sm font-medium mb-5 border border-primary-100 dark:border-primary-900">
                        <IconRoute size={16} />
                        Roadmap
                    </div>
                    <h1 className="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-white">
                        Perjalanan Dikasir
                    </h1>
                    <p className="mt-5 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                        Dari kasir sederhana menjadi ekosistem POS lengkap — dan masih terus
                        berkembang bersama komunitas.
                    </p>
                </div>
            </section>

            {/* Timeline */}
            <section className="pb-20 px-6">
                <div className="max-w-3xl mx-auto">
                    <div className="relative pl-8 border-l-2 border-primary-200 dark:border-primary-900 space-y-10">
                        {releases.map((rel) => (
                            <div key={rel.version} className="relative">
                                <div className="absolute -left-[41px] top-1.5 w-5 h-5 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 border-4 border-white dark:border-slate-950" />
                                <div className="flex items-center gap-3 flex-wrap">
                                    <h2 className="text-xl font-bold text-slate-900 dark:text-white">
                                        {rel.version}
                                    </h2>
                                    <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 border border-primary-100 dark:border-primary-900">
                                        {rel.tag}
                                    </span>
                                    <span className="text-sm text-slate-400">{rel.date}</span>
                                </div>
                                <ul className="mt-3 space-y-2">
                                    {rel.items.map((item) => (
                                        <li key={item} className="flex items-start gap-2.5">
                                            <IconCheck size={16} className="text-emerald-500 mt-1 shrink-0" />
                                            <span className="text-sm text-slate-600 dark:text-slate-300">
                                                {item}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Directions */}
            <section className="py-20 px-6 bg-white dark:bg-slate-900/50 border-y border-slate-200 dark:border-slate-800">
                <div className="max-w-5xl mx-auto">
                    <div className="text-center mb-12">
                        <h2 className="text-3xl font-bold text-slate-900 dark:text-white">
                            Arah ke Depan
                        </h2>
                        <p className="mt-4 text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                            Prioritas dibentuk bersama komunitas — dari feedback pengguna dan
                            kontributor.
                        </p>
                    </div>
                    <div className="grid md:grid-cols-3 gap-6">
                        {directions.map((dir) => (
                            <div
                                key={dir.title}
                                className="p-6 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800"
                            >
                                <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center mb-4">
                                    <dir.icon size={22} className="text-white" />
                                </div>
                                <h3 className="font-semibold text-slate-900 dark:text-white mb-2">
                                    {dir.title}
                                </h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                                    {dir.desc}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Community CTA */}
            <section className="py-20 px-6">
                <div className="max-w-3xl mx-auto">
                    <div className="bg-slate-900 dark:bg-slate-800 rounded-3xl p-10 text-center">
                        <h2 className="text-2xl md:text-3xl font-bold text-white mb-3">
                            Roadmap dibentuk oleh komunitas
                        </h2>
                        <p className="text-slate-400 mb-7">
                            Punya ide fitur? Laporkan bug? Atau ingin mengerjakan salah satu arah
                            di atas? Semua dimulai dari GitHub.
                        </p>
                        <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
                            <a
                                href={`${GITHUB_URL}/issues`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold rounded-xl hover:from-primary-600 hover:to-primary-700 transition-all"
                            >
                                <IconBrandGithub size={18} />
                                Buat Issue / Ide
                            </a>
                            <Link
                                href="/kontribusi"
                                className="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-slate-300 border border-slate-600 rounded-xl hover:border-primary-400 hover:text-primary-400 transition-colors"
                            >
                                Mulai berkontribusi
                                <IconArrowRight size={16} />
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

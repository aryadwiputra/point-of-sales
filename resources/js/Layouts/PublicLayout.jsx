import { Link } from "@inertiajs/react";
import {
    IconShoppingCart,
    IconBrandGithub,
    IconStar,
    IconArrowRight,
} from "@tabler/icons-react";

const GITHUB_URL = "https://github.com/aryadwiputra/point-of-sales";
const DOCS_URL = `${GITHUB_URL}/blob/main/docs/getting-started.md`;

export const NAV_LINKS = [
    { label: "Fitur", href: "/fitur" },
    { label: "Dokumentasi", href: "/dokumentasi" },
    { label: "Roadmap", href: "/roadmap" },
    { label: "Kontribusi", href: "/kontribusi" },
];

export default function PublicLayout({ children, active = "" }) {
    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col">
            {/* ============ NAVBAR ============ */}
            <nav className="fixed top-0 left-0 right-0 z-50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-b border-slate-200 dark:border-slate-800">
                <div className="max-w-7xl mx-auto px-6 py-3 flex items-center justify-between">
                    <Link href="/" className="flex items-center gap-2.5">
                        <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center">
                            <IconShoppingCart size={20} className="text-white" />
                        </div>
                        <span className="text-lg font-bold text-slate-900 dark:text-white">
                            Dikasir
                        </span>
                    </Link>

                    <div className="hidden md:flex items-center gap-7">
                        {NAV_LINKS.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                className={`text-sm transition-colors ${
                                    active === link.href
                                        ? "text-primary-600 dark:text-primary-400 font-semibold"
                                        : "text-slate-600 dark:text-slate-400 hover:text-primary-500"
                                }`}
                            >
                                {link.label}
                            </Link>
                        ))}
                    </div>

                    <div className="flex items-center gap-3">
                        <a
                            href={GITHUB_URL}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="hidden sm:flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-xl hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                        >
                            <IconStar size={15} className="text-amber-400" />
                            Star
                        </a>
                        <Link
                            href="/login"
                            className="px-5 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-primary-500 transition-colors"
                        >
                            Masuk
                        </Link>
                        <a
                            href={GITHUB_URL}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-primary-500 to-primary-600 rounded-xl hover:from-primary-600 hover:to-primary-700 shadow-lg shadow-primary-500/25 transition-all"
                        >
                            Get Source
                        </a>
                    </div>
                </div>
            </nav>

            {/* ============ CONTENT ============ */}
            <main className="flex-1 pt-[68px]">{children}</main>

            {/* ============ FOOTER ============ */}
            <footer className="py-10 px-6 border-t border-slate-200 dark:border-slate-800">
                <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center">
                            <IconShoppingCart size={16} className="text-white" />
                        </div>
                        <div>
                            <div className="font-semibold text-slate-700 dark:text-slate-300">
                                Dikasir
                            </div>
                            <div className="text-xs text-slate-500">
                                Sistem kasir open source untuk UMKM
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-6 text-sm text-slate-500 dark:text-slate-400">
                        <Link href="/fitur" className="hover:text-primary-500 transition-colors">
                            Fitur
                        </Link>
                        <Link href="/dokumentasi" className="hover:text-primary-500 transition-colors">
                            Dokumentasi
                        </Link>
                        <Link href="/roadmap" className="hover:text-primary-500 transition-colors">
                            Roadmap
                        </Link>
                        <Link href="/kontribusi" className="hover:text-primary-500 transition-colors">
                            Kontribusi
                        </Link>
                        <a href={GITHUB_URL} target="_blank" rel="noopener noreferrer" className="hover:text-primary-500 transition-colors">
                            GitHub
                        </a>
                        <a href={`${GITHUB_URL}/blob/main/LICENSE`} target="_blank" rel="noopener noreferrer" className="hover:text-primary-500 transition-colors">
                            Lisensi MIT
                        </a>
                    </div>

                    <p className="text-sm text-slate-500">
                        © {new Date().getFullYear()} Dibuat oleh Arya Dwi Putra
                    </p>
                </div>
            </footer>
        </div>
    );
}

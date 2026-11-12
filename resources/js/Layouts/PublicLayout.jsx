import { Link, usePage } from "@inertiajs/react";
import { IconArrowRight } from "@tabler/icons-react";
import ApplicationLogo from "@/Components/ApplicationLogo";

export const NAV_LINKS = [
    { label: "Fitur", href: "/fitur" },
    { label: "Dokumentasi", href: "/dokumentasi" },
    { label: "Harga & Paket", href: "/login" },
    { label: "Keamanan", href: "/keamanan" },
];

export default function PublicLayout({ children, active = "" }) {
    const { brand, auth } = usePage().props;
    const name = brand?.name || "Bihaju POS";
    const tagline = brand?.tagline || "Bikin Usaha Maju";
    const supportEmail = brand?.supportEmail || "support@bihajupos.id";
    const year = new Date().getFullYear();

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col">
            <nav className="fixed top-0 left-0 right-0 z-50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-b border-slate-200 dark:border-slate-800">
                <div className="max-w-7xl mx-auto px-6 py-3 flex items-center justify-between">
                    <Link href="/" className="flex items-center gap-2.5">
                        <ApplicationLogo className="w-9 h-9" />
                        <div className="leading-tight">
                            <div className="text-lg font-bold text-slate-900 dark:text-white">{name}</div>
                            <div className="hidden sm:block text-[11px] text-slate-500 dark:text-slate-400">{tagline}</div>
                        </div>
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
                        {auth?.user ? (
                            <Link
                                href="/dashboard"
                                className="px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-primary-500 to-primary-600 rounded-xl hover:from-primary-600 hover:to-primary-700 shadow-lg shadow-primary-500/25 transition-all"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href="/login"
                                    className="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-primary-500 transition-colors"
                                >
                                    Masuk
                                </Link>
                                <Link
                                    href="/login"
                                    className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-primary-500 to-primary-600 rounded-xl hover:from-primary-600 hover:to-primary-700 shadow-lg shadow-primary-500/25 transition-all"
                                >
                                    Mulai
                                    <IconArrowRight size={15} />
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </nav>

            <main className="flex-1 pt-[68px]">{children}</main>

            <footer className="py-12 px-6 border-t border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/30">
                <div className="max-w-7xl mx-auto grid gap-10 md:grid-cols-4">
                    <div className="md:col-span-2">
                        <div className="flex items-center gap-3 mb-3">
                            <ApplicationLogo className="w-9 h-9" />
                            <div>
                                <div className="font-bold text-slate-800 dark:text-slate-100">{name}</div>
                                <div className="text-sm text-primary-600 dark:text-primary-400 font-medium">{tagline}</div>
                            </div>
                        </div>
                        <p className="text-sm text-slate-600 dark:text-slate-400 max-w-md">
                            Platform kasir (POS) SaaS untuk membantu operasional usaha lebih rapi, cepat, dan siap bertumbuh — dari kasir, inventori, hingga laporan dan integrasi.
                        </p>
                        <p className="mt-3 text-sm text-slate-500 dark:text-slate-400">
                            Dukungan: <a href={`mailto:${supportEmail}`} className="hover:text-primary-500">{supportEmail}</a>
                        </p>
                    </div>

                    <div>
                        <h3 className="text-sm font-semibold text-slate-900 dark:text-white mb-3">Produk</h3>
                        <ul className="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                            <li><Link href="/fitur" className="hover:text-primary-500">Fitur</Link></li>
                            <li><Link href="/dokumentasi" className="hover:text-primary-500">Dokumentasi</Link></li>
                            <li><Link href="/roadmap" className="hover:text-primary-500">Roadmap</Link></li>
                            <li><Link href="/keamanan" className="hover:text-primary-500">Keamanan</Link></li>
                        </ul>
                    </div>

                    <div>
                        <h3 className="text-sm font-semibold text-slate-900 dark:text-white mb-3">Legal</h3>
                        <ul className="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                            <li><Link href="/syarat-layanan" className="hover:text-primary-500">Syarat Layanan</Link></li>
                            <li><Link href="/kebijakan-privasi" className="hover:text-primary-500">Kebijakan Privasi</Link></li>
                            <li><Link href="/kebijakan-pengembalian" className="hover:text-primary-500">Pengembalian & Pembatalan</Link></li>
                            <li><Link href="/kontribusi" className="hover:text-primary-500">Kontribusi</Link></li>
                        </ul>
                    </div>
                </div>
                <div className="max-w-7xl mx-auto mt-10 pt-6 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-slate-500 dark:text-slate-400">
                    <p>© {year} {name}. Hak cipta dilindungi.</p>
                    <p>{tagline}</p>
                </div>
            </footer>
        </div>
    );
}

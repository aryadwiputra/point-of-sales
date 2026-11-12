import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Toaster } from 'react-hot-toast';
import {
    IconDashboard,
    IconBuildingStore,
    IconPackages,
    IconReceipt,
    IconArrowLeft,
    IconShieldLock,
    IconLogout,
    IconMoon,
    IconSun,
} from '@tabler/icons-react';
import { useTheme } from '@/Context/ThemeSwitcherContext';

export default function SuperadminLayout({ title, children }) {
    const { auth, flash } = usePage().props;
    const currentUrl = usePage().url;
    const { darkMode, themeSwitcher } = useTheme();

    const navItems = [
        {
            name: 'Dashboard',
            href: route('superadmin.dashboard'),
            active: currentUrl === '/superadmin' || currentUrl.startsWith('/superadmin?'),
            icon: IconDashboard,
        },
        {
            name: 'Penyewa (Tenants)',
            href: route('superadmin.tenants.index'),
            active: currentUrl.startsWith('/superadmin/tenants'),
            icon: IconBuildingStore,
        },
        {
            name: 'Transaksi',
            href: route('superadmin.transactions.index'),
            active: currentUrl.startsWith('/superadmin/transactions'),
            icon: IconReceipt,
        },
        {
            name: 'Paket & Kuota',
            href: route('superadmin.plans.index'),
            active: currentUrl.startsWith('/superadmin/plans'),
            icon: IconPackages,
        },
    ];

    return (
        <div className="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-900 dark:text-slate-100 flex flex-col antialiased transition-colors duration-200">
            <Toaster position="top-right" />

            {/* Topbar */}
            <header className="border-b border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-950/80 backdrop-blur sticky top-0 z-40">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <div className="flex items-center space-x-6">
                        <Link href={route('superadmin.dashboard')} className="flex items-center space-x-2 text-indigo-600 dark:text-indigo-400 font-bold text-lg tracking-tight">
                            <span className="p-1.5 bg-indigo-100 text-indigo-600 dark:bg-indigo-600/20 dark:text-indigo-400 rounded-lg border border-indigo-200 dark:border-indigo-500/30">
                                <IconShieldLock size={20} />
                            </span>
                            <span>{(usePage().props.brand?.name) || "Bihaju POS"} <span className="text-slate-500 dark:text-slate-400 font-normal text-sm">Platform Admin</span></span>
                        </Link>

                        <nav className="hidden md:flex space-x-1">
                            {navItems.map((item) => {
                                const Icon = item.icon;
                                return (
                                    <Link
                                        key={item.name}
                                        href={item.href}
                                        className={`flex items-center space-x-2 px-3 py-1.5 rounded-lg text-sm font-medium transition ${
                                            item.active
                                                ? 'bg-indigo-100 text-indigo-700 border border-indigo-200 dark:bg-indigo-600/20 dark:text-indigo-300 dark:border-indigo-500/30'
                                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800/60'
                                        }`}
                                    >
                                        <Icon size={16} />
                                        <span>{item.name}</span>
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>

                    <div className="flex items-center space-x-2 sm:space-x-3">
                        <button
                            type="button"
                            onClick={themeSwitcher}
                            className="p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition-colors"
                            title={darkMode ? 'Mode Terang' : 'Mode Gelap'}
                        >
                            {darkMode ? <IconSun size={18} className="text-amber-500" /> : <IconMoon size={18} />}
                        </button>

                        <Link
                            href={route('dashboard')}
                            className="hidden sm:flex items-center space-x-1.5 px-3 py-1.5 text-xs font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 rounded-lg border border-slate-200 dark:border-slate-700 transition"
                        >
                            <IconArrowLeft size={14} />
                            <span>Ke Toko (POS)</span>
                        </Link>

                        <div className="text-right hidden sm:block">
                            <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">{auth?.user?.name}</div>
                            <div className="text-[10px] text-indigo-600 dark:text-indigo-400 font-mono">Platform Admin</div>
                        </div>

                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="p-2 text-slate-500 hover:text-rose-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-rose-400 dark:hover:bg-slate-800 rounded-lg transition"
                            title="Keluar"
                        >
                            <IconLogout size={18} />
                        </Link>
                    </div>
                </div>
            </header>

            {/* Mobile Nav Bar */}
            <div className="md:hidden border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-4 py-2 flex space-x-1 overflow-x-auto">
                {navItems.map((item) => {
                    const Icon = item.icon;
                    return (
                        <Link
                            key={item.name}
                            href={item.href}
                            className={`flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs whitespace-nowrap ${
                                item.active
                                    ? 'bg-indigo-100 text-indigo-700 border border-indigo-200 dark:bg-indigo-600/20 dark:text-indigo-300 dark:border-indigo-500/30'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
                            }`}
                        >
                            <Icon size={14} />
                            <span>{item.name}</span>
                        </Link>
                    );
                })}
            </div>

            {/* Main Content */}
            <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {flash?.success && (
                    <div className="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 dark:bg-emerald-950/60 dark:border-emerald-500/30 dark:text-emerald-300 text-sm flex items-center justify-between">
                        <span>{flash.success}</span>
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 dark:bg-rose-950/60 dark:border-rose-500/30 dark:text-rose-300 text-sm flex items-center justify-between">
                        <span>{flash.error}</span>
                    </div>
                )}

                {children}
            </main>
        </div>
    );
}

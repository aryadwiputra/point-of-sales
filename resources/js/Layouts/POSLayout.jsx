import React, { useState, useEffect } from "react";
import { usePage, Link } from "@inertiajs/react";
import { Toaster } from "react-hot-toast";
import { useTheme } from "@/Context/ThemeSwitcherContext";
import {
    IconHome,
    IconHistory,
    IconSun,
    IconMoon,
    IconLogout,
    IconMenu2,
    IconX,
    IconUser,
    IconWallet,
} from "@tabler/icons-react";
import Notification from "@/Components/Dashboard/Notification";

export default function POSLayout({ children }) {
    const { auth, storeProfile, activeCashierShift } = usePage().props;
    const { darkMode, themeSwitcher } = useTheme();
    const [currentTime, setCurrentTime] = useState(new Date());
    const [showMobileMenu, setShowMobileMenu] = useState(false);

    // Update time every minute
    useEffect(() => {
        const timer = setInterval(() => {
            setCurrentTime(new Date());
        }, 60000);
        return () => clearInterval(timer);
    }, []);

    const formatTime = (date) => {
        return date.toLocaleTimeString("id-ID", {
            hour: "2-digit",
            minute: "2-digit",
        });
    };

    const formatDate = (date) => {
        return date.toLocaleDateString("id-ID", {
            weekday: "long",
            day: "numeric",
            month: "short",
            year: "numeric",
        });
    };

    return (
        <div className="min-h-screen flex flex-col bg-canvas-cream dark:bg-canvas-night">
            {/* Top Navigation Bar */}
            <header className="sticky top-0 z-50 h-16 flex items-center justify-between px-4 lg:px-6 bg-white dark:bg-canvas-night-elevated border-b border-hairline-light dark:border-hairline-dark">
                {/* Left Section - Logo & Time */}
                <div className="flex items-center gap-4 lg:gap-6">
                    {/* Mobile Menu Toggle */}
                    <button
                        onClick={() => setShowMobileMenu(!showMobileMenu)}
                        className="lg:hidden min-h-touch min-w-touch rounded-full hover:bg-canvas-cream dark:hover:bg-canvas-night transition-colors"
                    >
                        {showMobileMenu ? (
                            <IconX
                                size={22}
                                className="text-slate-600 dark:text-slate-400"
                            />
                        ) : (
                            <IconMenu2
                                size={22}
                                className="text-slate-600 dark:text-slate-400"
                            />
                        )}
                    </button>

                    {/* Logo */}
                    <Link href={route("dashboard")} className="flex items-center gap-2">
                        <div className="w-9 h-9 flex items-center justify-center overflow-hidden">
                            {storeProfile?.logo ? (
                                <img
                                    src={storeProfile.logo}
                                    alt={storeProfile?.name || "Store"}
                                    className="w-full h-full object-contain"
                                />
                            ) : (
                                <div className="w-full h-full rounded-full flex items-center justify-center bg-ink text-white font-bold text-sm">
                                    {(storeProfile?.name || "K").charAt(0)}
                                </div>
                            )}
                        </div>
                        <span className="hidden sm:block text-lg font-bold text-ink dark:text-white">
                            {storeProfile?.name || "KASIR"}
                        </span>
                    </Link>

                    {/* Divider */}
                    <div className="hidden md:block w-px h-8 bg-slate-200 dark:bg-slate-700" />

                    {/* Time & Date */}
                    <div className="hidden md:flex items-center gap-3">
                        <div className="text-2xl font-semibold text-ink dark:text-white tabular-nums">
                            {formatTime(currentTime)}
                        </div>
                        <div className="text-sm text-slate-500 dark:text-slate-400">
                            {formatDate(currentTime)}
                        </div>
                    </div>
                </div>

                {/* Right Section - Actions & User */}
                <div className="flex items-center gap-2 lg:gap-3">
                    {/* Quick Actions */}
                    <nav className="hidden lg:flex items-center gap-1">
                        <Link
                            href={route("dashboard")}
                            className="flex items-center gap-2 rounded-full px-3 py-2 text-sm font-medium text-shade-60 hover:text-ink hover:bg-canvas-cream dark:text-slate-400 dark:hover:text-white dark:hover:bg-canvas-night transition-colors"
                        >
                            <IconHome size={18} />
                            <span>Dashboard</span>
                        </Link>
                        <Link
                            href={route("transactions.history")}
                            className="flex items-center gap-2 rounded-full px-3 py-2 text-sm font-medium text-shade-60 hover:text-ink hover:bg-canvas-cream dark:text-slate-400 dark:hover:text-white dark:hover:bg-canvas-night transition-colors"
                        >
                            <IconHistory size={18} />
                            <span>Riwayat</span>
                        </Link>
                    </nav>

                    {/* Divider */}
                    <div className="hidden lg:block w-px h-8 bg-slate-200 dark:bg-slate-700" />

                    {/* Notifications (desktop) */}
                    <div className="hidden md:flex">
                        <Notification />
                    </div>

                    {/* Theme Toggle */}
                    <button
                        onClick={themeSwitcher}
                        className="rounded-full hover:bg-canvas-cream dark:hover:bg-canvas-night transition-colors min-w-touch min-h-touch flex items-center justify-center"
                        title={darkMode ? "Light Mode" : "Dark Mode"}
                    >
                        {darkMode ? (
                            <IconSun size={20} className="text-amber-500" />
                        ) : (
                            <IconMoon size={20} className="text-slate-500" />
                        )}
                    </button>

                    {/* Notifications (mobile) */}
                    <div className="flex md:hidden">
                        <Notification />
                    </div>

                    {/* User Info - Simplified */}
                    <div className="flex items-center gap-2 pl-2 lg:pl-3 border-l border-slate-200 dark:border-slate-700">
                        {activeCashierShift && (
                            <Link
                                href={route("cashier-shifts.show", activeCashierShift.id)}
                                className="hidden lg:flex items-center gap-2 rounded-full bg-aloe-100 px-3 py-2 text-xs font-semibold text-ink transition hover:bg-pistachio-100 dark:bg-hairline-dark dark:text-emerald-300 dark:hover:bg-emerald-950/60"
                            >
                                <IconWallet size={16} />
                                <span>
                                    Shift aktif •{" "}
                                    {new Intl.NumberFormat("id-ID").format(
                                        activeCashierShift.expected_cash || 0
                                    )}
                                </span>
                            </Link>
                        )}
                        <p className="text-sm font-medium text-ink dark:text-slate-200">
                            {auth.user.name}
                        </p>
                    </div>

                    {/* Logout */}
                    <Link
                        href={route("logout")}
                        method="post"
                        as="button"
                        className="hidden lg:flex rounded-full text-slate-500 hover:text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-950/50 transition-colors min-w-touch min-h-touch items-center justify-center"
                        title="Logout"
                    >
                        <IconLogout size={20} />
                    </Link>
                </div>
            </header>

            {/* Mobile Menu Overlay */}
            {showMobileMenu && (
                <div
                    className="lg:hidden fixed inset-0 z-40 bg-black/50"
                    onClick={() => setShowMobileMenu(false)}
                >
                    <div
                        className="absolute top-16 left-0 right-0 bg-white dark:bg-canvas-night-elevated border-b border-hairline-light dark:border-hairline-dark shadow-paper animate-slide-up"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <nav className="p-4 space-y-2">
                            <Link
                                href={route("dashboard")}
                                className="flex items-center gap-3 px-4 py-3 rounded-full text-shade-70 hover:bg-canvas-cream dark:text-slate-300 dark:hover:bg-canvas-night transition-colors"
                            >
                                <IconHome size={20} />
                                <span className="font-medium">Dashboard</span>
                            </Link>
                            <Link
                                href={route("transactions.history")}
                                className="flex items-center gap-3 px-4 py-3 rounded-full text-shade-70 hover:bg-canvas-cream dark:text-slate-300 dark:hover:bg-canvas-night transition-colors"
                            >
                                <IconHistory size={20} />
                                <span className="font-medium">
                                    Riwayat Transaksi
                                </span>
                            </Link>
                            <Link
                                href={route("profile.edit")}
                                className="flex items-center gap-3 px-4 py-3 rounded-full text-shade-70 hover:bg-canvas-cream dark:text-slate-300 dark:hover:bg-canvas-night transition-colors"
                            >
                                <IconUser size={20} />
                                <span className="font-medium">Profil</span>
                            </Link>
                            <hr className="border-slate-200 dark:border-slate-700" />
                            <Link
                                href={route("logout")}
                                method="post"
                                as="button"
                                className="flex items-center gap-3 px-4 py-3 rounded-full text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-950/50 transition-colors w-full"
                            >
                                <IconLogout size={20} />
                                <span className="font-medium">Keluar</span>
                            </Link>
                        </nav>
                    </div>
                </div>
            )}

            {/* Main Content - Full Height */}
            <main className="flex-1 overflow-hidden">
                <Toaster
                    position="top-right"
                    toastOptions={{
                        className: "text-sm",
                        duration: 3000,
                        style: {
                            background: darkMode ? "#0a0a0a" : "#fff",
                            color: darkMode ? "#f1f5f9" : "#000",
                            border: `1px solid ${
                                darkMode ? "#1e2c31" : "#e4e4e7"
                            }`,
                            borderRadius: "9999px",
                        },
                    }}
                />
                {children}
            </main>
        </div>
    );
}

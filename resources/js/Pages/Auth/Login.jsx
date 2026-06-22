import { useEffect } from "react";
import { Head, Link, useForm } from "@inertiajs/react";
import AuthBotGuardFields from "@/Components/AuthBotGuardFields";
import {
    IconShoppingCart,
    IconMail,
    IconLock,
    IconEye,
    IconEyeOff,
    IconLoader2,
} from "@tabler/icons-react";
import { useState } from "react";

export default function Login({ status, canResetPassword, canRegister, botGuard }) {
    const honeypotField = botGuard?.honeypot_field || "company_website";
    const tokenField = botGuard?.token_field || "bot_guard_token";
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false,
        [honeypotField]: "",
        [tokenField]: botGuard?.token || "",
    });
    const [showPassword, setShowPassword] = useState(false);

    useEffect(() => {
        return () => reset("password");
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route("login"));
    };

    return (
        <>
            <Head title="Masuk" />

            <div className="min-h-screen flex bg-canvas-cream dark:bg-canvas-night">
                {/* Left - Form */}
                <div className="flex-1 flex items-center justify-center p-8">
                    <div className="w-full max-w-md">
                        {/* Logo */}
                        <div className="mb-8">
                            <Link
                                href="/"
                                className="inline-flex items-center gap-3 mb-6"
                            >
                                <div className="w-12 h-12 rounded-full bg-ink text-white flex items-center justify-center">
                                    <IconShoppingCart
                                        size={24}
                                        className="text-white"
                                    />
                                </div>
                                <span className="text-2xl font-bold text-ink dark:text-white">
                                    Aplikasi Kasir
                                </span>
                            </Link>
                            <h1 className="text-3xl font-bold text-ink dark:text-white">
                                Selamat Datang Kembali
                            </h1>
                            <p className="mt-2 text-shade-60 dark:text-slate-400">
                                Masuk untuk mengakses dashboard Anda
                            </p>
                        </div>

                        {/* Status Message */}
                        {status && (
                            <div className="mb-6 p-4 rounded-card bg-success-50 dark:bg-success-950/50 text-success-700 dark:text-success-400 text-sm">
                                {status}
                            </div>
                        )}

                        {/* Form */}
                        <form onSubmit={submit} className="space-y-5">
                            <AuthBotGuardFields
                                botGuard={botGuard}
                                data={data}
                                setData={setData}
                            />
                            {errors.human && (
                                <div className="rounded-card bg-danger-50 px-4 py-3 text-sm text-danger-600 dark:bg-danger-950/40 dark:text-danger-300">
                                    {errors.human}
                                </div>
                            )}
                            {/* Email */}
                            <div>
                                <label className="block text-sm font-medium text-shade-70 dark:text-slate-300 mb-2">
                                    Email
                                </label>
                                <div className="relative">
                                    <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                        <IconMail size={20} />
                                    </div>
                                    <input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData("email", e.target.value)
                                        }
                                        placeholder="nama@email.com"
                                        className={`w-full h-12 pl-12 pr-4 rounded-md border ${
                                            errors.email
                                                ? "border-danger-500 focus:border-danger-500"
                                                : "border-hairline-light dark:border-hairline-dark focus:border-ink"
                                        } bg-white dark:bg-canvas-night-elevated text-ink dark:text-white placeholder-slate-400 focus:ring-4 focus:ring-aloe-100/70 transition-all`}
                                    />
                                </div>
                                {errors.email && (
                                    <p className="mt-1.5 text-sm text-danger-500">
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            {/* Password */}
                            <div>
                                <label className="block text-sm font-medium text-shade-70 dark:text-slate-300 mb-2">
                                    Password
                                </label>
                                <div className="relative">
                                    <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                        <IconLock size={20} />
                                    </div>
                                    <input
                                        type={
                                            showPassword ? "text" : "password"
                                        }
                                        value={data.password}
                                        onChange={(e) =>
                                            setData("password", e.target.value)
                                        }
                                        placeholder="••••••••"
                                        className={`w-full h-12 pl-12 pr-12 rounded-md border ${
                                            errors.password
                                                ? "border-danger-500 focus:border-danger-500"
                                                : "border-hairline-light dark:border-hairline-dark focus:border-ink"
                                        } bg-white dark:bg-canvas-night-elevated text-ink dark:text-white placeholder-slate-400 focus:ring-4 focus:ring-aloe-100/70 transition-all`}
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword(!showPassword)
                                        }
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                    >
                                        {showPassword ? (
                                            <IconEyeOff size={20} />
                                        ) : (
                                            <IconEye size={20} />
                                        )}
                                    </button>
                                </div>
                                {errors.password && (
                                    <p className="mt-1.5 text-sm text-danger-500">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            {/* Remember & Forgot */}
                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) =>
                                            setData(
                                                "remember",
                                                e.target.checked
                                            )
                                        }
                                        className="w-4 h-4 rounded border-hairline-light dark:border-hairline-dark text-ink focus:ring-aloe-100"
                                    />
                                    <span className="text-sm text-shade-60 dark:text-slate-400">
                                        Ingat saya
                                    </span>
                                </label>

                                {canResetPassword && (
                                    <Link
                                        href={route("password.request")}
                                        className="text-sm text-ink hover:text-shade-70 font-medium"
                                    >
                                        Lupa Password?
                                    </Link>
                                )}
                            </div>

                            {/* Submit */}
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full h-12 rounded-full bg-ink text-white font-semibold hover:bg-shade-70 focus:ring-4 focus:ring-aloe-100 disabled:opacity-50 transition-all flex items-center justify-center gap-2"
                            >
                                {processing ? (
                                    <>
                                        <IconLoader2
                                            size={20}
                                            className="animate-spin"
                                        />
                                        Memproses...
                                    </>
                                ) : (
                                    "Masuk"
                                )}
                            </button>

                            {/* Register Link */}
                            {canRegister && (
                                <p className="text-center text-sm text-shade-60 dark:text-slate-400">
                                    Belum punya akun?{" "}
                                    <Link
                                        href="/register"
                                        className="text-ink hover:text-shade-70 font-semibold"
                                    >
                                        Daftar Sekarang
                                    </Link>
                                </p>
                            )}
                        </form>
                    </div>
                </div>

                {/* Right - Image/Decoration */}
                <div className="hidden lg:flex flex-1 bg-pistachio-100 dark:bg-canvas-night-elevated items-center justify-center p-12">
                    <div className="max-w-md text-center text-ink dark:text-white">
                        <div className="w-24 h-24 rounded-full bg-white border border-hairline-light flex items-center justify-center mx-auto mb-8 shadow-paper dark:bg-canvas-night dark:border-hairline-dark">
                            <IconShoppingCart size={48} />
                        </div>
                        <h2 className="text-3xl font-bold mb-4">
                            Kelola Bisnis Anda dengan Mudah
                        </h2>
                        <p className="text-lg text-shade-60 dark:text-slate-300">
                            Sistem Point of Sale modern yang membantu Anda
                            mengelola transaksi, inventori, dan laporan keuangan
                            dengan efisien.
                        </p>
                        <div className="mt-8 flex flex-wrap justify-center gap-3">
                            {[
                                "Transaksi Cepat",
                                "Laporan Real-time",
                                "Multi User",
                            ].map((feature, i) => (
                                <span
                                    key={i}
                                    className="px-4 py-2 bg-white border border-hairline-light rounded-full text-sm font-medium text-ink shadow-sm dark:bg-canvas-night dark:border-hairline-dark dark:text-white"
                                >
                                    {feature}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

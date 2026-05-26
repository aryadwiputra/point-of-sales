import { useEffect, useState } from "react";
import { Head, Link, useForm } from "@inertiajs/react";
import AuthBotGuardFields from "@/Components/AuthBotGuardFields";
import {
    IconShoppingCart,
    IconUser,
    IconMail,
    IconLock,
    IconEye,
    IconEyeOff,
    IconLoader2,
    IconCheck,
} from "@tabler/icons-react";

export default function Register({ botGuard }) {
    const honeypotField = botGuard?.honeypot_field || "company_website";
    const tokenField = botGuard?.token_field || "bot_guard_token";
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
        [honeypotField]: "",
        [tokenField]: botGuard?.token || "",
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    useEffect(() => {
        return () => reset("password", "password_confirmation");
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route("register"));
    };

    return (
        <>
            <Head title="Daftar" />

            <div className="min-h-screen flex bg-canvas-cream dark:bg-canvas-night">
                {/* Left - Decoration */}
                <div className="hidden lg:flex flex-1 bg-pistachio-100 dark:bg-canvas-night-elevated items-center justify-center p-12">
                    <div className="max-w-md text-center text-ink dark:text-white">
                        <div className="w-24 h-24 rounded-full bg-white border border-hairline-light flex items-center justify-center mx-auto mb-8 shadow-paper dark:bg-canvas-night dark:border-hairline-dark">
                            <IconShoppingCart size={48} />
                        </div>
                        <h2 className="text-3xl font-bold mb-4">
                            Bergabung Bersama Kami
                        </h2>
                        <p className="text-lg text-shade-60 dark:text-slate-300">
                            Mulai kelola bisnis Anda dengan sistem Point of Sale
                            yang modern, cepat, dan mudah digunakan.
                        </p>
                        <div className="mt-8 space-y-3">
                            {[
                                "Gratis untuk memulai",
                                "Setup dalam 5 menit",
                                "Dukungan penuh",
                            ].map((feature, i) => (
                                <div
                                    key={i}
                                    className="flex items-center justify-center gap-2 text-sm font-medium"
                                >
                                    <IconCheck
                                        size={18}
                                    className="text-ink dark:text-white"
                                    />
                                    {feature}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Right - Form */}
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
                                Buat Akun Baru
                            </h1>
                            <p className="mt-2 text-shade-60 dark:text-slate-400">
                                Daftarkan bisnis Anda sekarang
                            </p>
                        </div>

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
                            {/* Name */}
                            <div>
                                <label className="block text-sm font-medium text-shade-70 dark:text-slate-300 mb-2">
                                    Nama Lengkap
                                </label>
                                <div className="relative">
                                    <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                        <IconUser size={20} />
                                    </div>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData("name", e.target.value)
                                        }
                                        placeholder="Nama Anda"
                                        className={`w-full h-12 pl-12 pr-4 rounded-md border ${
                                            errors.name
                                                ? "border-danger-500 focus:border-danger-500"
                                                : "border-hairline-light dark:border-hairline-dark focus:border-ink"
                                        } bg-white dark:bg-canvas-night-elevated text-ink dark:text-white placeholder-slate-400 focus:ring-4 focus:ring-aloe-100/70 transition-all`}
                                    />
                                </div>
                                {errors.name && (
                                    <p className="mt-1.5 text-sm text-danger-500">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

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
                                        placeholder="Minimal 8 karakter"
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

                            {/* Confirm Password */}
                            <div>
                                <label className="block text-sm font-medium text-shade-70 dark:text-slate-300 mb-2">
                                    Konfirmasi Password
                                </label>
                                <div className="relative">
                                    <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                        <IconLock size={20} />
                                    </div>
                                    <input
                                        type={
                                            showConfirmPassword
                                                ? "text"
                                                : "password"
                                        }
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                "password_confirmation",
                                                e.target.value
                                            )
                                        }
                                        placeholder="Ulangi password"
                                        className={`w-full h-12 pl-12 pr-12 rounded-md border ${
                                            errors.password_confirmation
                                                ? "border-danger-500 focus:border-danger-500"
                                                : "border-hairline-light dark:border-hairline-dark focus:border-ink"
                                        } bg-white dark:bg-canvas-night-elevated text-ink dark:text-white placeholder-slate-400 focus:ring-4 focus:ring-aloe-100/70 transition-all`}
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowConfirmPassword(
                                                !showConfirmPassword
                                            )
                                        }
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                    >
                                        {showConfirmPassword ? (
                                            <IconEyeOff size={20} />
                                        ) : (
                                            <IconEye size={20} />
                                        )}
                                    </button>
                                </div>
                                {errors.password_confirmation && (
                                    <p className="mt-1.5 text-sm text-danger-500">
                                        {errors.password_confirmation}
                                    </p>
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
                                    "Daftar Sekarang"
                                )}
                            </button>

                            {/* Login Link */}
                            <p className="text-center text-sm text-shade-60 dark:text-slate-400">
                                Sudah punya akun?{" "}
                                <Link
                                    href="/login"
                                    className="text-ink hover:text-shade-70 font-semibold"
                                >
                                    Masuk disini
                                </Link>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}

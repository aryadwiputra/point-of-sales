import { useEffect, useMemo, useState } from "react";
import { Head, Link, useForm, usePage } from "@inertiajs/react";
import {
    IconShoppingCart,
    IconShieldLock,
    IconLock,
    IconEye,
    IconEyeOff,
    IconLoader2,
} from "@tabler/icons-react";

export default function ConfirmPassword({ challenge = null }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: "",
    });
    const [showPassword, setShowPassword] = useState(false);

    useEffect(() => {
        return () => {
            reset("password");
        };
    }, []);

    const challengeLabel = useMemo(() => {
        if (!challenge?.route) {
            return "aksi sensitif";
        }

        return challenge.route.replaceAll(".", " / ");
    }, [challenge]);

    const submit = (e) => {
        e.preventDefault();

        post(route("password.confirm"));
    };

    return (
        <>
            <Head title="Konfirmasi Password" />

            <div className="min-h-screen flex bg-slate-50 dark:bg-slate-950">
                <div className="flex-1 flex items-center justify-center p-8">
                    <div className="w-full max-w-md">
                        <div className="mb-8">
                            <Link href="/" className="inline-flex items-center gap-3 mb-6">
                                <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center">
                                    <IconShoppingCart size={24} className="text-white" />
                                </div>
                                <span className="text-2xl font-bold text-slate-900 dark:text-white">
                                    {brand?.name || "Bihaju POS"}
                                </span>
                            </Link>
                            <div className="w-14 h-14 rounded-2xl bg-primary-100 dark:bg-primary-950/50 flex items-center justify-center mb-5">
                                <IconShieldLock size={28} className="text-primary-600 dark:text-primary-400" />
                            </div>
                            <h1 className="text-3xl font-bold text-slate-900 dark:text-white">
                                Konfirmasi Password
                            </h1>
                            <p className="mt-2 text-slate-600 dark:text-slate-400">
                                Untuk melanjutkan {challengeLabel}, masukkan kembali password akun Anda.
                            </p>
                        </div>

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                                    Password
                                </label>
                                <div className="relative">
                                    <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                        <IconLock size={20} />
                                    </div>
                                    <input
                                        id="password"
                                        type={showPassword ? "text" : "password"}
                                        value={data.password}
                                        onChange={(e) => setData("password", e.target.value)}
                                        className={`w-full h-12 pl-12 pr-12 rounded-xl border-2 ${
                                            errors.password
                                                ? "border-danger-500 focus:border-danger-500"
                                                : "border-slate-200 dark:border-slate-700 focus:border-primary-500"
                                        } bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-4 focus:ring-primary-500/20 transition-all`}
                                        autoFocus
                                        placeholder="Masukkan password Anda"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword((value) => !value)}
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                    >
                                        {showPassword ? <IconEyeOff size={20} /> : <IconEye size={20} />}
                                    </button>
                                </div>
                                {errors.password && (
                                    <p className="mt-1.5 text-sm text-danger-500">{errors.password}</p>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full h-12 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold hover:from-primary-600 hover:to-primary-700 focus:ring-4 focus:ring-primary-500/30 disabled:opacity-50 transition-all flex items-center justify-center gap-2"
                            >
                                {processing ? (
                                    <>
                                        <IconLoader2 size={18} className="animate-spin" />
                                        Memverifikasi...
                                    </>
                                ) : (
                                    "Lanjutkan"
                                )}
                            </button>
                        </form>
                    </div>
                </div>

                <div className="hidden lg:flex flex-1 bg-gradient-to-br from-primary-500 to-primary-700 items-center justify-center p-12">
                    <div className="max-w-md text-center text-white">
                        <div className="w-24 h-24 rounded-2xl bg-white/20 flex items-center justify-center mx-auto mb-8">
                            <IconShieldLock size={48} />
                        </div>
                        <h2 className="text-3xl font-bold mb-4">Proteksi Aksi Admin</h2>
                        <p className="text-lg opacity-90">
                            Konfirmasi password ulang membantu menahan aksi sensitif saat sesi admin sudah lama aktif.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

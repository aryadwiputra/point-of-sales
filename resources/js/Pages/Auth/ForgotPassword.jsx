import { Head, Link, useForm, usePage } from "@inertiajs/react";
import {
    IconShoppingCart,
    IconMail,
    IconLoader2,
    IconArrowLeft,
} from "@tabler/icons-react";
import AuthBotGuardFields from "@/Components/AuthBotGuardFields";

export default function ForgotPassword({ status, botGuard }) {
    const { brand } = usePage().props || {};
    const brandName = brand?.name || "Bihaju POS";
    const honeypotField = botGuard?.honeypot_field || "company_website";
    const tokenField = botGuard?.token_field || "bot_guard_token";
    const { data, setData, post, processing, errors } = useForm({
        email: "",
        [honeypotField]: "",
        [tokenField]: botGuard?.token || "",
    });

    const submit = (e) => {
        e.preventDefault();

        post(route("password.email"));
    };

    return (
        <>
            <Head title="Lupa Password" />

            <div className="min-h-screen flex bg-slate-50 dark:bg-slate-950">
                <div className="flex-1 flex items-center justify-center p-8">
                    <div className="w-full max-w-md">
                        <div className="mb-8">
                            <Link
                                href="/"
                                className="inline-flex items-center gap-3 mb-6"
                            >
                                <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center">
                                    <IconShoppingCart size={24} className="text-white" />
                                </div>
                                <span className="text-2xl font-bold text-slate-900 dark:text-white">
                                    {brandName}
                                </span>
                            </Link>
                            <h1 className="text-3xl font-bold text-slate-900 dark:text-white">
                                Reset Password
                            </h1>
                            <p className="mt-2 text-slate-600 dark:text-slate-400">
                                Masukkan email Anda untuk menerima link reset password.
                            </p>
                        </div>

                        {status && (
                            <div className="mb-6 rounded-xl bg-success-50 p-4 text-sm text-success-700 dark:bg-success-950/50 dark:text-success-400">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-5">
                            <AuthBotGuardFields
                                botGuard={botGuard}
                                data={data}
                                setData={setData}
                            />
                            {errors.human && (
                                <div className="rounded-xl bg-danger-50 px-4 py-3 text-sm text-danger-600 dark:bg-danger-950/40 dark:text-danger-300">
                                    {errors.human}
                                </div>
                            )}
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Email
                                </label>
                                <div className="relative">
                                    <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                        <IconMail size={20} />
                                    </div>
                                    <input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData("email", e.target.value)}
                                        className={`w-full h-12 pl-12 pr-4 rounded-xl border-2 ${
                                            errors.email
                                                ? "border-danger-500 focus:border-danger-500"
                                                : "border-slate-200 dark:border-slate-700 focus:border-primary-500"
                                        } bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-4 focus:ring-primary-500/20 transition-all`}
                                        placeholder="nama@email.com"
                                    />
                                </div>
                                {errors.email && (
                                    <p className="mt-1.5 text-sm text-danger-500">{errors.email}</p>
                                )}
                            </div>

                            <div className="flex gap-3">
                                <Link
                                    href={route("login")}
                                    className="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-xl border-2 border-slate-200 bg-white font-semibold text-slate-700 transition-all hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                >
                                    <IconArrowLeft size={18} />
                                    Kembali
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 font-semibold text-white transition-all hover:from-primary-600 hover:to-primary-700 disabled:opacity-50"
                                >
                                    {processing ? (
                                        <>
                                            <IconLoader2 size={18} className="animate-spin" />
                                            Mengirim...
                                        </>
                                    ) : (
                                        "Kirim Link Reset"
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div className="hidden lg:flex flex-1 bg-gradient-to-br from-primary-500 to-primary-700 items-center justify-center p-12">
                    <div className="max-w-md text-center text-white">
                        <div className="w-24 h-24 rounded-2xl bg-white/20 flex items-center justify-center mx-auto mb-8">
                            <IconMail size={48} />
                        </div>
                        <h2 className="text-3xl font-bold mb-4">Pemulihan Akun Aman</h2>
                        <p className="text-lg opacity-90">
                            Link reset password membantu memulihkan akses akun tanpa membuka jalur bypass ke dashboard.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

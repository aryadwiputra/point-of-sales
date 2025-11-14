import { useEffect } from "react";
import { Head, useForm } from "@inertiajs/react";
import ApplicationLogo from "@/Components/ApplicationLogo";

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false,
    });

    useEffect(() => {
        return () => reset("password");
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route("login"));
    };

    return (
        <>
            <Head title="Login" />

            <div className="min-h-screen grid grid-cols-1 lg:grid-cols-2 bg-white text-gray-900 dark:bg-neutral-900 dark:text-gray-100">
                {/* Left - Form */}
                <div className="flex items-center justify-center p-8">
                    <div className="w-full max-w-md">
                        <div className="mb-8">
                            <ApplicationLogo className="w-16 h-16 mb-4" />
                            <h1 className="text-3xl font-bold">
                                Aplikasi Kasir
                            </h1>
                            <p className="text-gray-600 dark:text-gray-400">
                                Masuk ke Dashboard
                            </p>
                        </div>

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <label className="block text-sm font-medium">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData("email", e.target.value)
                                    }
                                    className="mt-1 block w-full px-4 py-2 rounded-md border border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 focus:ring-2 focus:ring-neutral-700 focus:border-neutral-700"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium">
                                    Password
                                </label>
                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData("password", e.target.value)
                                    }
                                    className="mt-1 block w-full px-4 py-2 rounded-md border border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 focus:ring-2 focus:ring-neutral-700 focus:border-neutral-700"
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <label className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) =>
                                            setData(
                                                "remember",
                                                e.target.checked
                                            )
                                        }
                                        className="h-4 w-4 text-neutral-900 dark:text-neutral-200 border-gray-300 dark:border-neutral-700 rounded focus:ring-neutral-700"
                                    />
                                    <span className="text-sm">Ingat saya</span>
                                </label>

                                {canResetPassword && (
                                    <a
                                        href={route("password.request")}
                                        className="text-sm text-neutral-800 dark:text-neutral-300 hover:underline"
                                    >
                                        Lupa Password?
                                    </a>
                                )}
                            </div>

                            <button
                                type="submit"
                                className="w-full py-2.5 rounded-md bg-black dark:bg-neutral-800 text-white font-semibold hover:bg-neutral-900 dark:hover:bg-neutral-700 focus:ring-4 focus:ring-neutral-500"
                            >
                                Masuk
                            </button>
                        </form>
                    </div>
                </div>

                {/* Right - Image */}
                <div className="hidden lg:block">
                    <div
                        className="h-full w-full bg-cover bg-center"
                        style={{
                            backgroundImage: `url('/assets/photo/auth.jpg')`,
                        }}
                    />
                </div>
            </div>
        </>
    );
}

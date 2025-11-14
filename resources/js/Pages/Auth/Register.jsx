import { useEffect } from "react";
import { Head, Link, useForm } from "@inertiajs/react";
import ApplicationLogo from "@/Components/ApplicationLogo";
import InputLabel from "@/Components/InputLabel";
import TextInput from "@/Components/TextInput";
import InputError from "@/Components/InputError";
import PrimaryButton from "@/Components/PrimaryButton";

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
    });

    useEffect(() => {
        return () => reset("password", "password_confirmation");
    }, []);

    const submit = (e) => {
        e.preventDefault();
        post(route("register"));
    };

    return (
        <>
            <Head title="Register" />

            <div className="min-h-screen grid grid-cols-1 lg:grid-cols-2 bg-white text-gray-900 dark:bg-neutral-900 dark:text-gray-100">
                {/* Left: Form */}
                <div className="flex items-center justify-center p-8">
                    <div className="w-full max-w-md">
                        <div className="mb-8">
                            <ApplicationLogo className="w-16 h-16 mb-4" />
                            <h1 className="text-3xl font-bold">
                                Aplikasi Kasir
                            </h1>
                            <p className="text-gray-600 dark:text-gray-400">
                                Buat akun baru
                            </p>
                        </div>

                        <form onSubmit={submit} className="space-y-5">
                            <div>
                                <InputLabel htmlFor="name" value="Name" />
                                <TextInput
                                    id="name"
                                    name="name"
                                    value={data.name}
                                    className="mt-1 block w-full border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 focus:ring-neutral-700 focus:border-neutral-700"
                                    onChange={(e) =>
                                        setData("name", e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel htmlFor="email" value="Email" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className="mt-1 block w-full border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 focus:ring-neutral-700 focus:border-neutral-700"
                                    onChange={(e) =>
                                        setData("email", e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.email}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="password"
                                    value="Password"
                                />
                                <TextInput
                                    id="password"
                                    type="password"
                                    name="password"
                                    value={data.password}
                                    className="mt-1 block w-full border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 focus:ring-neutral-700 focus:border-neutral-700"
                                    onChange={(e) =>
                                        setData("password", e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.password}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="password_confirmation"
                                    value="Confirm Password"
                                />
                                <TextInput
                                    id="password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    value={data.password_confirmation}
                                    className="mt-1 block w-full border-gray-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 focus:ring-neutral-700 focus:border-neutral-700"
                                    onChange={(e) =>
                                        setData(
                                            "password_confirmation",
                                            e.target.value
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                    className="mt-2"
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <Link
                                    href={route("login")}
                                    className="text-sm text-neutral-800 dark:text-neutral-300 hover:underline"
                                >
                                    Sudah punya akun?
                                </Link>

                                <PrimaryButton
                                    className="px-6"
                                    disabled={processing}
                                >
                                    Register
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Right: Image */}
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

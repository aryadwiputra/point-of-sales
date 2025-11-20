import React, { useEffect } from "react";
import { Head, useForm, usePage } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import Card from "@/Components/Dashboard/Card";
import Input from "@/Components/Dashboard/Input";
import Checkbox from "@/Components/Dashboard/Checkbox";
import Button from "@/Components/Dashboard/Button";
import { IconCreditCard } from "@tabler/icons-react";
import toast from "react-hot-toast";

export default function Payment({ setting, supportedGateways = [] }) {
    const { flash } = usePage().props;

    const {
        data,
        setData,
        put,
        errors,
        processing,
    } = useForm({
        default_gateway: setting?.default_gateway ?? "cash",
        midtrans_enabled: setting?.midtrans_enabled ?? false,
        midtrans_server_key: setting?.midtrans_server_key ?? "",
        midtrans_client_key: setting?.midtrans_client_key ?? "",
        midtrans_production: setting?.midtrans_production ?? false,
        xendit_enabled: setting?.xendit_enabled ?? false,
        xendit_secret_key: setting?.xendit_secret_key ?? "",
        xendit_public_key: setting?.xendit_public_key ?? "",
        xendit_production: setting?.xendit_production ?? false,
    });

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    const handleSubmit = (event) => {
        event.preventDefault();
        put(route("settings.payments.update"), {
            preserveScroll: true,
        });
    };

    const isGatewaySelectable = (gateway) => {
        if (gateway === "cash") {
            return true;
        }

        if (gateway === "midtrans") {
            return data.midtrans_enabled;
        }

        if (gateway === "xendit") {
            return data.xendit_enabled;
        }

        return false;
    };

    return (
        <>
            <Head title="Pengaturan Payment Gateway" />
            <form
                onSubmit={handleSubmit}
                className="max-w-4xl space-y-5"
            >
                <Card
                    title="Pengaturan Umum"
                    icon={<IconCreditCard size={20} />}
                >
                    <div className="space-y-3">
                        <p className="text-sm text-gray-600 dark:text-gray-300">
                            Tentukan gateway pembayaran default yang digunakan
                            kasir saat membuka halaman transaksi. Gateway non
                            tunai hanya bisa dipilih jika sudah diaktifkan di
                            bawah.
                        </p>
                        <div>
                            <label className="text-sm text-gray-600 dark:text-gray-300">
                                Gateway Default
                            </label>
                            <select
                                value={data.default_gateway}
                                onChange={(event) =>
                                    setData(
                                        "default_gateway",
                                        event.target.value
                                    )
                                }
                                className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 focus:border-indigo-500 focus:outline-none dark:border-gray-800 dark:bg-gray-950 dark:text-gray-100"
                            >
                                {supportedGateways.map((gateway) => (
                                    <option
                                        key={gateway.value}
                                        value={gateway.value}
                                        disabled={
                                            !isGatewaySelectable(
                                                gateway.value
                                            )
                                        }
                                    >
                                        {gateway.label}
                                        {!isGatewaySelectable(
                                            gateway.value
                                        ) && " (nonaktif)"}
                                    </option>
                                ))}
                            </select>
                            {errors?.default_gateway && (
                                <small className="text-xs text-rose-500">
                                    {errors.default_gateway}
                                </small>
                            )}
                        </div>
                    </div>
                </Card>

                <Card title="Midtrans Snap">
                    <div className="space-y-4">
                        <Checkbox
                            label="Aktifkan Midtrans"
                            checked={data.midtrans_enabled}
                            onChange={(event) =>
                                setData(
                                    "midtrans_enabled",
                                    event.target.checked
                                )
                            }
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <Input
                                label="Server Key"
                                type="text"
                                value={data.midtrans_server_key}
                                onChange={(event) =>
                                    setData(
                                        "midtrans_server_key",
                                        event.target.value
                                    )
                                }
                                errors={errors?.midtrans_server_key}
                            />
                            <Input
                                label="Client Key"
                                type="text"
                                value={data.midtrans_client_key}
                                onChange={(event) =>
                                    setData(
                                        "midtrans_client_key",
                                        event.target.value
                                    )
                                }
                                errors={errors?.midtrans_client_key}
                            />
                        </div>
                        <Checkbox
                            label="Gunakan mode produksi"
                            checked={data.midtrans_production}
                            onChange={(event) =>
                                setData(
                                    "midtrans_production",
                                    event.target.checked
                                )
                            }
                        />
                    </div>
                </Card>

                <Card title="Xendit Invoice">
                    <div className="space-y-4">
                        <Checkbox
                            label="Aktifkan Xendit"
                            checked={data.xendit_enabled}
                            onChange={(event) =>
                                setData("xendit_enabled", event.target.checked)
                            }
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <Input
                                label="Secret Key"
                                type="text"
                                value={data.xendit_secret_key}
                                onChange={(event) =>
                                    setData(
                                        "xendit_secret_key",
                                        event.target.value
                                    )
                                }
                                errors={errors?.xendit_secret_key}
                            />
                            <Input
                                label="Public Key"
                                type="text"
                                value={data.xendit_public_key}
                                onChange={(event) =>
                                    setData(
                                        "xendit_public_key",
                                        event.target.value
                                    )
                                }
                                errors={errors?.xendit_public_key}
                            />
                        </div>
                        <Checkbox
                            label="Gunakan mode produksi"
                            checked={data.xendit_production}
                            onChange={(event) =>
                                setData(
                                    "xendit_production",
                                    event.target.checked
                                )
                            }
                        />
                    </div>
                </Card>

                <div className="flex justify-end">
                    <Button
                        type="submit"
                        label="Simpan Konfigurasi"
                        className="bg-indigo-600 text-white hover:bg-indigo-500"
                        disabled={processing}
                    />
                </div>
            </form>
        </>
    );
}

Payment.layout = (page) => <DashboardLayout children={page} />;

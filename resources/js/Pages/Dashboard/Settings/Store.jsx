import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, useForm } from "@inertiajs/react";
import Input from "@/Components/Dashboard/Input";
import Textarea from "@/Components/Dashboard/TextArea";
import toast from "react-hot-toast";
import { useState, useEffect } from "react";
import {
    IconBuildingStore,
    IconDeviceFloppy,
    IconPhone,
    IconMapPin,
    IconWorld,
    IconMail,
    IconPhoto,
    IconLayoutGrid,
    IconList,
} from "@tabler/icons-react";

export default function Store({ settings }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        store_name: settings.store_name || "",
        store_logo: null,
        store_address: settings.store_address || "",
        store_phone: settings.store_phone || "",
        store_email: settings.store_email || "",
        store_website: settings.store_website || "",
        store_city: settings.store_city || "",
        product_display_mode: settings.product_display_mode || "image_grid",
    });

    const [logoPreview, setLogoPreview] = useState(settings.store_logo || null);

    useEffect(() => {
        return () => {
            if (logoPreview && logoPreview.startsWith("blob:")) {
                URL.revokeObjectURL(logoPreview);
            }
        };
    }, [logoPreview]);

    const submit = (e) => {
        e.preventDefault();
        post(route("settings.store.update"), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Profil toko disimpan");
                reset("store_logo");
            },
            onError: () => toast.error("Gagal menyimpan profil toko"),
        });
    };

    return (
        <>
            <Head title="Profil Toko" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                        Pengaturan Toko
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                        Atur identitas toko yang muncul di struk dan laporan.
                    </p>
                </div>

                <form onSubmit={submit} className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 space-y-6">
                    <div className="flex flex-col lg:flex-row gap-6">
                        {/* Logo */}
                        <div className="lg:w-1/3">
                            <label className="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 flex items-center gap-2">
                                <IconPhoto size={18} />
                                Logo Toko
                            </label>
                            <div className="w-32 h-32 rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 flex items-center justify-center overflow-hidden mb-3">
                                {logoPreview ? (
                                    <img
                                        src={logoPreview.startsWith("http") || logoPreview.startsWith("/storage")
                                            ? logoPreview
                                            : `/storage/${logoPreview}`}
                                        alt="Logo"
                                        className="w-full h-full object-cover"
                                    />
                                ) : (
                                    <IconBuildingStore size={36} className="text-slate-300" />
                                )}
                            </div>
                            <input
                                type="file"
                                accept="image/*"
                                onChange={(e) => {
                                    const file = e.target.files[0];
                                    if (file) {
                                        setData("store_logo", file);
                                        setLogoPreview(URL.createObjectURL(file));
                                    }
                                }}
                            />
                            {errors.store_logo && (
                                <p className="text-xs text-danger-500 mt-1">
                                    {errors.store_logo}
                                </p>
                            )}
                        </div>

                        {/* Info */}
                        <div className="lg:flex-1 space-y-4">
                            <Input
                                label="Nama Toko"
                                value={data.store_name}
                                errors={errors.store_name}
                                onChange={(e) => setData("store_name", e.target.value)}
                                placeholder="Nama toko"
                            />
                            <Textarea
                                label="Alamat Lengkap"
                                value={data.store_address}
                                errors={errors.store_address}
                                onChange={(e) => setData("store_address", e.target.value)}
                                rows={3}
                            />
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <Input
                                    label="Kota/Kabupaten"
                                    value={data.store_city}
                                    errors={errors.store_city}
                                    onChange={(e) => setData("store_city", e.target.value)}
                                    placeholder="contoh: Surabaya"
                                    icon={<IconMapPin size={16} />}
                                />
                                <Input
                                    label="Nomor Telepon"
                                    value={data.store_phone}
                                    errors={errors.store_phone}
                                    onChange={(e) => setData("store_phone", e.target.value)}
                                    placeholder="0812xxxxxxx"
                                    icon={<IconPhone size={16} />}
                                />
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <Input
                                    label="Email"
                                    type="email"
                                    value={data.store_email}
                                    errors={errors.store_email}
                                    onChange={(e) => setData("store_email", e.target.value)}
                                    placeholder="email@toko.com"
                                    icon={<IconMail size={16} />}
                                />
                                <Input
                                    label="Website / Sosial Media"
                                    value={data.store_website}
                                    errors={errors.store_website}
                                    onChange={(e) => setData("store_website", e.target.value)}
                                    placeholder="https://"
                                    icon={<IconWorld size={16} />}
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Mode Tampilan Produk
                                </label>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                "product_display_mode",
                                                "image_grid"
                                            )
                                        }
                                        className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-left transition-colors ${
                                            data.product_display_mode ===
                                            "image_grid"
                                                ? "border-primary-400 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-950/40 dark:text-primary-300"
                                                : "border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                        }`}
                                    >
                                        <IconLayoutGrid size={20} />
                                        <span>
                                            <span className="block text-sm font-semibold">
                                                Image Grid
                                            </span>
                                            <span className="block text-xs text-slate-500 dark:text-slate-400">
                                                Produk dan kategori memakai gambar.
                                            </span>
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                "product_display_mode",
                                                "compact_list"
                                            )
                                        }
                                        className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-left transition-colors ${
                                            data.product_display_mode ===
                                            "compact_list"
                                                ? "border-primary-400 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-950/40 dark:text-primary-300"
                                                : "border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                                        }`}
                                    >
                                        <IconList size={20} />
                                        <span>
                                            <span className="block text-sm font-semibold">
                                                Compact List
                                            </span>
                                            <span className="block text-xs text-slate-500 dark:text-slate-400">
                                                Tanpa foto produk dan kategori.
                                            </span>
                                        </span>
                                    </button>
                                </div>
                                {errors.product_display_mode && (
                                    <p className="mt-1 text-xs text-danger-500">
                                        {errors.product_display_mode}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary-500 hover:bg-primary-600 text-white font-medium transition-colors disabled:opacity-50"
                        >
                            <IconDeviceFloppy size={18} />
                            {processing ? "Menyimpan..." : "Simpan Profil"}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}

Store.layout = (page) => <DashboardLayout children={page} />;

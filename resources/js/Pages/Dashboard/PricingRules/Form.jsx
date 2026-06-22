import React, { useState } from "react";
import { Head, useForm } from "@inertiajs/react";
import axios from "axios";
import Button from "@/Components/Dashboard/Button";
import {
    IconArrowLeft,
    IconChartInfographic,
    IconDeviceFloppy,
    IconPlus,
    IconTrash,
} from "@tabler/icons-react";

const targetOptions = [
    { value: "all", label: "Semua Produk" },
    { value: "product", label: "Produk Tertentu" },
    { value: "category", label: "Kategori Tertentu" },
];

const customerScopeOptions = [
    { value: "all", label: "Semua Pelanggan" },
    { value: "walk_in", label: "Tanpa Pelanggan / Umum" },
    { value: "registered", label: "Pelanggan Terdaftar" },
    { value: "member", label: "Member Loyalty" },
];

const discountTypeOptions = [
    { value: "percentage", label: "Persentase (%)" },
    { value: "fixed_amount", label: "Potongan Nominal" },
    { value: "fixed_price", label: "Harga Final" },
];

function InputError({ message }) {
    if (!message) return null;
    return <p className="mt-1 text-xs text-rose-500">{message}</p>;
}

function CardSection({ title, description, children }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div className="mb-4">
                <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                    {title}
                </h2>
                {description && (
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

export default function Form({
    mode = "create",
    rule = null,
    products = [],
    categories = [],
    tierOptions = [],
    kindOptions = [],
}) {
    const isEdit = mode === "edit";
    const { data, setData, post, put, processing, errors } = useForm({
        name: rule?.name ?? "",
        kind: rule?.kind ?? "standard_discount",
        is_active: Boolean(rule?.is_active ?? true),
        priority: String(rule?.priority ?? 100),
        target_type: rule?.target_type ?? "all",
        product_id: rule?.product_id ? String(rule.product_id) : "",
        category_id: rule?.category_id ? String(rule.category_id) : "",
        customer_scope: rule?.customer_scope ?? "all",
        eligible_loyalty_tiers: rule?.eligible_loyalty_tiers ?? [],
        discount_type: rule?.discount_type ?? "percentage",
        discount_value:
            rule?.discount_value !== undefined && rule?.discount_value !== null
                ? String(rule.discount_value)
                : "",
        preview_quantity_multiplier: String(rule?.preview_quantity_multiplier ?? 1),
        starts_at: rule?.starts_at
            ? new Date(rule.starts_at).toISOString().slice(0, 16)
            : "",
        ends_at: rule?.ends_at
            ? new Date(rule.ends_at).toISOString().slice(0, 16)
            : "",
        notes: rule?.notes ?? "",
        qty_breaks: rule?.qty_breaks?.length
            ? rule.qty_breaks.map((item) => ({
                  min_qty: String(item.min_qty),
                  discount_type: item.discount_type,
                  discount_value: String(item.discount_value),
                  sort_order: String(item.sort_order ?? 0),
              }))
            : [{ min_qty: "3", discount_type: "fixed_price", discount_value: "", sort_order: "0" }],
        bundle_items: rule?.bundle_items?.length
            ? rule.bundle_items.map((item) => ({
                  product_id: String(item.product_id),
                  quantity: String(item.quantity),
                  sort_order: String(item.sort_order ?? 0),
              }))
            : [
                  { product_id: "", quantity: "1", sort_order: "0" },
                  { product_id: "", quantity: "1", sort_order: "1" },
              ],
        buy_get_items: rule?.buy_get_items?.length
            ? rule.buy_get_items.map((item) => ({
                  product_id: String(item.product_id),
                  role: item.role,
                  quantity: String(item.quantity),
                  sort_order: String(item.sort_order ?? 0),
              }))
            : [
                  { product_id: "", role: "buy", quantity: "1", sort_order: "0" },
                  { product_id: "", role: "get", quantity: "1", sort_order: "1" },
              ],
    });
    const [previewState, setPreviewState] = useState({
        loading: false,
        data: null,
        error: null,
    });

    const submit = (event) => {
        event.preventDefault();

        if (isEdit) {
            put(route("pricing-rules.update", rule.id));
            return;
        }

        post(route("pricing-rules.store"));
    };

    const updateArrayRow = (key, index, field, value) => {
        const next = [...data[key]];
        next[index] = { ...next[index], [field]: value };
        setData(key, next);
    };

    const addRow = (key, template) => {
        setData(key, [...data[key], template]);
    };

    const removeRow = (key, index) => {
        setData(
            key,
            data[key].filter((_, currentIndex) => currentIndex !== index)
        );
    };

    const runPreview = async () => {
        setPreviewState({ loading: true, data: null, error: null });

        try {
            const response = await axios.post(route("pricing-rules.preview"), data);
            setPreviewState({
                loading: false,
                data: response.data?.data ?? null,
                error: null,
            });
        } catch (error) {
            const responseErrors = error.response?.data?.errors;
            const firstValidationMessage = responseErrors
                ? Object.values(responseErrors).flat()[0]
                : null;

            setPreviewState({
                loading: false,
                data: null,
                error:
                    firstValidationMessage ||
                    error.response?.data?.message ||
                    "Preview belum bisa dijalankan. Periksa kembali isian rule.",
            });
        }
    };

    const previewGroups = previewState.data?.applied_groups || [];

    return (
        <>
            <Head title={isEdit ? "Edit Promo Harga" : "Buat Promo Harga"} />

            <div className="w-full">
                <div className="mb-6">
                    <Button
                        type="link"
                        href={route("pricing-rules.index")}
                        icon={<IconArrowLeft size={18} />}
                        className="mb-3 border-none bg-transparent px-0 text-slate-500 shadow-none hover:bg-transparent hover:text-primary-600 dark:text-slate-400"
                        label="Kembali ke promo harga"
                    />
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                        {isEdit ? "Edit Promo Harga" : "Buat Promo Harga"}
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                        Kelola promo standar, grosir, bundle, dan buy x get y dalam satu engine.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <CardSection
                        title="Informasi Rule"
                        description="Identitas dasar rule, jenis promo, dan prioritas penerapan."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Nama Rule
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData("name", event.target.value)
                                    }
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Jenis Rule
                                </label>
                                <select
                                    value={data.kind}
                                    onChange={(event) =>
                                        setData("kind", event.target.value)
                                    }
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    {kindOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.kind} />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Priority
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={data.priority}
                                    onChange={(event) =>
                                        setData("priority", event.target.value)
                                    }
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Qty Preview POS
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    value={data.preview_quantity_multiplier}
                                    onChange={(event) =>
                                        setData(
                                            "preview_quantity_multiplier",
                                            event.target.value
                                        )
                                    }
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                />
                            </div>
                        </div>
                    </CardSection>

                    <CardSection
                        title="Target & Scope"
                        description="Tentukan produk/kategori yang terkena promo dan siapa yang berhak."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Target Rule
                                </label>
                                <select
                                    value={data.target_type}
                                    onChange={(event) =>
                                        setData("target_type", event.target.value)
                                    }
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    {targetOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Scope Pelanggan
                                </label>
                                <select
                                    value={data.customer_scope}
                                    onChange={(event) =>
                                        setData("customer_scope", event.target.value)
                                    }
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                >
                                    {customerScopeOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            {data.target_type === "product" && (
                                <div className="md:col-span-2">
                                    <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Produk
                                    </label>
                                    <select
                                        value={data.product_id}
                                        onChange={(event) =>
                                            setData("product_id", event.target.value)
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        <option value="">Pilih produk</option>
                                        {products.map((product) => (
                                            <option key={product.id} value={product.id}>
                                                {product.title}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.product_id} />
                                </div>
                            )}
                            {data.target_type === "category" && (
                                <div className="md:col-span-2">
                                    <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Kategori
                                    </label>
                                    <select
                                        value={data.category_id}
                                        onChange={(event) =>
                                            setData("category_id", event.target.value)
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        <option value="">Pilih kategori</option>
                                        {categories.map((category) => (
                                            <option key={category.id} value={category.id}>
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.category_id} />
                                </div>
                            )}
                            {data.customer_scope === "member" && (
                                <div className="md:col-span-2">
                                    <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Tier Member yang Berhak
                                    </label>
                                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                        {tierOptions.map((tier) => {
                                            const checked = data.eligible_loyalty_tiers.includes(
                                                tier.value
                                            );

                                            return (
                                                <label
                                                    key={tier.value}
                                                    className="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        onChange={(event) => {
                                                            const next = event.target.checked
                                                                ? [
                                                                      ...data.eligible_loyalty_tiers,
                                                                      tier.value,
                                                                  ]
                                                                : data.eligible_loyalty_tiers.filter(
                                                                      (value) =>
                                                                          value !== tier.value
                                                                  );

                                                            setData(
                                                                "eligible_loyalty_tiers",
                                                                next
                                                            );
                                                        }}
                                                    />
                                                    {tier.label}
                                                </label>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>
                    </CardSection>

                    {(data.kind === "standard_discount" ||
                        data.kind === "qty_break") && (
                        <CardSection
                            title="Diskon Rule"
                            description="Tentukan tipe diskon yang dipakai rule ini."
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Tipe Diskon
                                    </label>
                                    <select
                                        value={data.discount_type}
                                        onChange={(event) =>
                                            setData("discount_type", event.target.value)
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        {discountTypeOptions.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Nilai Diskon
                                    </label>
                                    <input
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={data.discount_value}
                                        onChange={(event) =>
                                            setData("discount_value", event.target.value)
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    />
                                    <InputError message={errors.discount_value} />
                                </div>
                            </div>
                        </CardSection>
                    )}

                    {data.kind === "qty_break" && (
                        <CardSection
                            title="Qty Break / Grosir"
                            description="Satu rule bisa memiliki beberapa breakpoint quantity."
                        >
                            <div className="space-y-3">
                                {data.qty_breaks.map((row, index) => (
                                    <div
                                        key={`qty-break-${index}`}
                                        className="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-4 dark:border-slate-700 dark:bg-slate-800"
                                    >
                                        <input
                                            type="number"
                                            min="1"
                                            value={row.min_qty}
                                            onChange={(event) =>
                                                updateArrayRow(
                                                    "qty_breaks",
                                                    index,
                                                    "min_qty",
                                                    event.target.value
                                                )
                                            }
                                            className="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                            placeholder="Min qty"
                                        />
                                        <select
                                            value={row.discount_type}
                                            onChange={(event) =>
                                                updateArrayRow(
                                                    "qty_breaks",
                                                    index,
                                                    "discount_type",
                                                    event.target.value
                                                )
                                            }
                                            className="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        >
                                            {discountTypeOptions.map((option) => (
                                                <option key={option.value} value={option.value}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                        <input
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            value={row.discount_value}
                                            onChange={(event) =>
                                                updateArrayRow(
                                                    "qty_breaks",
                                                    index,
                                                    "discount_value",
                                                    event.target.value
                                                )
                                            }
                                            className="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                            placeholder="Nilai"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => removeRow("qty_breaks", index)}
                                            className="inline-flex h-11 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 dark:border-rose-900/50 dark:bg-rose-950/40"
                                        >
                                            <IconTrash size={16} />
                                        </button>
                                    </div>
                                ))}
                                <button
                                    type="button"
                                    onClick={() =>
                                        addRow("qty_breaks", {
                                            min_qty: "1",
                                            discount_type: "fixed_price",
                                            discount_value: "",
                                            sort_order: String(data.qty_breaks.length),
                                        })
                                    }
                                    className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"
                                >
                                    <IconPlus size={16} />
                                    Tambah Break
                                </button>
                                <InputError message={errors.qty_breaks} />
                            </div>
                        </CardSection>
                    )}

                    {data.kind === "bundle_price" && (
                        <CardSection
                            title="Bundle Price"
                            description="Pilih kombinasi produk dan harga paket final."
                        >
                            <div className="mb-4">
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Harga Bundle
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    value={data.discount_value}
                                    onChange={(event) =>
                                        setData("discount_value", event.target.value)
                                    }
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                />
                            </div>
                            <div className="space-y-3">
                                {data.bundle_items.map((row, index) => (
                                    <div
                                        key={`bundle-item-${index}`}
                                        className="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-[1fr_160px_48px] dark:border-slate-700 dark:bg-slate-800"
                                    >
                                        <select
                                            value={row.product_id}
                                            onChange={(event) =>
                                                updateArrayRow(
                                                    "bundle_items",
                                                    index,
                                                    "product_id",
                                                    event.target.value
                                                )
                                            }
                                            className="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        >
                                            <option value="">Pilih produk</option>
                                            {products.map((product) => (
                                                <option key={product.id} value={product.id}>
                                                    {product.title}
                                                </option>
                                            ))}
                                        </select>
                                        <input
                                            type="number"
                                            min="1"
                                            value={row.quantity}
                                            onChange={(event) =>
                                                updateArrayRow(
                                                    "bundle_items",
                                                    index,
                                                    "quantity",
                                                    event.target.value
                                                )
                                            }
                                            className="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                            placeholder="Qty"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => removeRow("bundle_items", index)}
                                            className="inline-flex h-11 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 dark:border-rose-900/50 dark:bg-rose-950/40"
                                        >
                                            <IconTrash size={16} />
                                        </button>
                                    </div>
                                ))}
                                <button
                                    type="button"
                                    onClick={() =>
                                        addRow("bundle_items", {
                                            product_id: "",
                                            quantity: "1",
                                            sort_order: String(data.bundle_items.length),
                                        })
                                    }
                                    className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"
                                >
                                    <IconPlus size={16} />
                                    Tambah Item Bundle
                                </button>
                            </div>
                        </CardSection>
                    )}

                    {data.kind === "buy_x_get_y" && (
                        <CardSection
                            title="Buy X Get Y"
                            description="Atur item pembelian (buy) dan item hadiah/diskon (get)."
                        >
                            <div className="space-y-3">
                                {data.buy_get_items.map((row, index) => (
                                    <div
                                        key={`buy-get-item-${index}`}
                                        className="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 md:grid-cols-[160px_1fr_140px_48px] dark:border-slate-700 dark:bg-slate-800"
                                    >
                                        <select
                                            value={row.role}
                                            onChange={(event) =>
                                                updateArrayRow(
                                                    "buy_get_items",
                                                    index,
                                                    "role",
                                                    event.target.value
                                                )
                                            }
                                            className="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        >
                                            <option value="buy">Buy</option>
                                            <option value="get">Get</option>
                                        </select>
                                        <select
                                            value={row.product_id}
                                            onChange={(event) =>
                                                updateArrayRow(
                                                    "buy_get_items",
                                                    index,
                                                    "product_id",
                                                    event.target.value
                                                )
                                            }
                                            className="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        >
                                            <option value="">Pilih produk</option>
                                            {products.map((product) => (
                                                <option key={product.id} value={product.id}>
                                                    {product.title}
                                                </option>
                                            ))}
                                        </select>
                                        <input
                                            type="number"
                                            min="1"
                                            value={row.quantity}
                                            onChange={(event) =>
                                                updateArrayRow(
                                                    "buy_get_items",
                                                    index,
                                                    "quantity",
                                                    event.target.value
                                                )
                                            }
                                            className="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => removeRow("buy_get_items", index)}
                                            className="inline-flex h-11 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 dark:border-rose-900/50 dark:bg-rose-950/40"
                                        >
                                            <IconTrash size={16} />
                                        </button>
                                    </div>
                                ))}
                                <button
                                    type="button"
                                    onClick={() =>
                                        addRow("buy_get_items", {
                                            product_id: "",
                                            role: "buy",
                                            quantity: "1",
                                            sort_order: String(data.buy_get_items.length),
                                        })
                                    }
                                    className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200"
                                >
                                    <IconPlus size={16} />
                                    Tambah Item Buy/Get
                                </button>
                            </div>
                        </CardSection>
                    )}

                    <CardSection
                        title="Jadwal & Catatan"
                        description="Gunakan jadwal bila promo hanya aktif pada periode tertentu."
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Mulai
                                </label>
                                <input
                                    type="datetime-local"
                                    value={data.starts_at}
                                    onChange={(event) =>
                                        setData("starts_at", event.target.value)
                                    }
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                />
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Berakhir
                                </label>
                                <input
                                    type="datetime-local"
                                    value={data.ends_at}
                                    onChange={(event) =>
                                        setData("ends_at", event.target.value)
                                    }
                                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                />
                            </div>
                            <div className="md:col-span-2">
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Catatan
                                </label>
                                <textarea
                                    rows="3"
                                    value={data.notes}
                                    onChange={(event) =>
                                        setData("notes", event.target.value)
                                    }
                                    className="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                />
                            </div>
                            <label className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(event) =>
                                        setData("is_active", event.target.checked)
                                    }
                                />
                                Aktifkan rule ini
                            </label>
                        </div>
                    </CardSection>

                    <CardSection
                        title="Preview Draft"
                        description="Simulasikan rule ini terhadap contoh produk sebelum disimpan."
                    >
                        <div className="mb-4 flex flex-wrap gap-3">
                            <button
                                type="button"
                                onClick={runPreview}
                                disabled={previewState.loading}
                                className="inline-flex items-center gap-2 rounded-xl border border-primary-200 bg-primary-50 px-4 py-2 text-sm font-medium text-primary-700 dark:border-primary-900/50 dark:bg-primary-950/40 dark:text-primary-300"
                            >
                                <IconChartInfographic size={16} />
                                {previewState.loading
                                    ? "Memuat preview..."
                                    : "Jalankan Preview"}
                            </button>
                        </div>

                        {previewState.error && (
                            <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300">
                                {previewState.error}
                            </div>
                        )}

                        {previewState.data && (
                            <div className="space-y-4">
                                <div className="grid gap-3 md:grid-cols-3">
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">
                                            Base subtotal
                                        </p>
                                        <p className="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                                            Rp {Number(previewState.data.summary.base_subtotal || 0).toLocaleString("id-ID")}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">
                                            Promo discount
                                        </p>
                                        <p className="mt-1 text-lg font-semibold text-rose-600 dark:text-rose-300">
                                            Rp {Number(previewState.data.summary.promo_discount_total || 0).toLocaleString("id-ID")}
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">
                                            After promo
                                        </p>
                                        <p className="mt-1 text-lg font-semibold text-slate-900 dark:text-white">
                                            Rp {Number(previewState.data.summary.subtotal_after_promo || 0).toLocaleString("id-ID")}
                                        </p>
                                    </div>
                                </div>

                                {previewGroups.length > 0 && (
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                                        <h3 className="mb-3 text-sm font-semibold text-slate-900 dark:text-white">
                                            Applied Groups
                                        </h3>
                                        <div className="space-y-2">
                                            {previewGroups.map((group) => (
                                                <div
                                                    key={group.key}
                                                    className="flex items-center justify-between rounded-xl bg-white px-4 py-3 text-sm dark:bg-slate-900"
                                                >
                                                    <span className="font-medium text-slate-700 dark:text-slate-200">
                                                        {group.label}
                                                    </span>
                                                    <span className="text-rose-600 dark:text-rose-300">
                                                        -Rp {Number(group.discount_total || 0).toLocaleString("id-ID")}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardSection>

                    <div className="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                        <Button
                            type="link"
                            href={route("pricing-rules.index")}
                            className="border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            label="Batal"
                        />
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-500 px-5 py-2.5 font-medium text-white hover:bg-primary-600 disabled:opacity-50"
                        >
                            <IconDeviceFloppy size={18} />
                            {processing ? "Menyimpan..." : "Simpan Rule"}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}

import React, { useEffect, useMemo, useState } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import axios from "axios";
import toast from "react-hot-toast";
import DashboardLayout from "@/Layouts/DashboardLayout";
import Card from "@/Components/Dashboard/Card";
import Input from "@/Components/Dashboard/Input";
import Button from "@/Components/Dashboard/Button";
import InputSelect from "@/Components/Dashboard/InputSelect";
import Table from "@/Components/Dashboard/Table";
import {
    IconArrowRight,
    IconBarcode,
    IconCash,
    IconCreditCard,
    IconReceipt,
    IconShoppingBag,
    IconShoppingCartPlus,
    IconTrash,
    IconUser,
} from "@tabler/icons-react";

export default function Index({
    carts = [],
    carts_total = 0,
    customers = [],
    paymentGateways = [],
    defaultPaymentGateway = "cash",
}) {
    const { auth, errors } = usePage().props;

    const [barcode, setBarcode] = useState("");
    const [product, setProduct] = useState(null);
    const [qty, setQty] = useState(1);
    const [discountInput, setDiscountInput] = useState("");
    const [cashInput, setCashInput] = useState("");
    const [selectedCustomer, setSelectedCustomer] = useState(null);
    const [isSearching, setIsSearching] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState(
        defaultPaymentGateway ?? "cash"
    );

    useEffect(() => {
        setPaymentMethod(defaultPaymentGateway ?? "cash");
    }, [defaultPaymentGateway]);

    const discount = useMemo(
        () => Math.max(0, Number(discountInput) || 0),
        [discountInput]
    );
    const subtotal = useMemo(() => carts_total ?? 0, [carts_total]);
    const payable = useMemo(
        () => Math.max(subtotal - discount, 0),
        [subtotal, discount]
    );
    const cash = useMemo(
        () =>
            paymentMethod === "cash"
                ? Math.max(0, Number(cashInput) || 0)
                : payable,
        [cashInput, paymentMethod, payable]
    );
    const change = useMemo(() => Math.max(cash - payable, 0), [cash, payable]);
    const remaining = useMemo(
        () => Math.max(payable - cash, 0),
        [payable, cash]
    );
    const cartCount = useMemo(
        () => carts.reduce((total, item) => total + Number(item.qty), 0),
        [carts]
    );

    const paymentOptions = useMemo(() => {
        const options = Array.isArray(paymentGateways)
            ? paymentGateways.filter(
                  (gateway) =>
                      gateway?.value && gateway.value.toLowerCase() !== "cash"
              )
            : [];

        return [
            {
                value: "cash",
                label: "Tunai",
                description: "Pembayaran tunai langsung di kasir.",
            },
            ...options,
        ];
    }, [paymentGateways]);

    const activePaymentOption =
        paymentOptions.find((option) => option.value === paymentMethod) ??
        paymentOptions[0];

    const isCashPayment = activePaymentOption?.value === "cash";

    useEffect(() => {
        if (
            paymentOptions.length &&
            !paymentOptions.find((option) => option.value === paymentMethod)
        ) {
            setPaymentMethod(paymentOptions[0].value);
        }
    }, [paymentOptions, paymentMethod]);

    useEffect(() => {
        if (!isCashPayment && payable >= 0) {
            setCashInput(String(payable));
        }
    }, [isCashPayment, payable]);

    const submitLabel = isCashPayment
        ? remaining > 0
            ? "Menunggu Pembayaran"
            : "Selesaikan Transaksi"
        : `Buat Pembayaran ${activePaymentOption?.label ?? ""}`;

    const isSubmitDisabled =
        carts.length === 0 || (isCashPayment && remaining > 0);

    const formatPrice = (value = 0) =>
        value.toLocaleString("id-ID", {
            style: "currency",
            currency: "IDR",
            minimumFractionDigits: 0,
        });

    const sanitizeNumericInput = (value) => {
        const numbersOnly = value.replace(/[^\d]/g, "");

        if (numbersOnly === "") return "";

        return numbersOnly.replace(/^0+(?=\d)/, "");
    };

    const resetProductForm = () => {
        setBarcode("");
        setProduct(null);
        setQty(1);
    };

    const handleSearchProduct = async (event) => {
        event?.preventDefault();

        if (!barcode.trim()) {
            setProduct(null);
            toast.error("Masukkan barcode terlebih dahulu");
            return;
        }

        setIsSearching(true);

        try {
            const { data } = await axios.post(
                "/dashboard/transactions/searchProduct",
                { barcode }
            );

            if (data.success) {
                setProduct(data.data);
                setQty(1);
            } else {
                setProduct(null);
                toast.error("Produk tidak ditemukan");
            }
        } catch (error) {
            console.error(error);
            toast.error("Gagal mencari produk, coba lagi");
        } finally {
            setIsSearching(false);
        }
    };

    const handleAddToCart = (event) => {
        event.preventDefault();

        if (!product?.id) {
            toast.error("Silakan scan produk terlebih dahulu");
            return;
        }

        if (qty < 1) {
            toast.error("Jumlah minimal 1");
            return;
        }

        if (product?.stock && qty > product.stock) {
            toast.error("Jumlah melebihi stok tersedia");
            return;
        }

        router.post(
            route("transactions.addToCart"),
            {
                product_id: product.id,
                sell_price: product.sell_price,
                qty,
            },
            {
                onSuccess: () => {
                    toast.success("Produk ditambahkan ke keranjang");
                    resetProductForm();
                },
            }
        );
    };

    const handleSubmitTransaction = (event) => {
        event.preventDefault();

        if (carts.length === 0) {
            toast.error("Keranjang masih kosong");
            return;
        }

        if (!selectedCustomer?.id) {
            toast.error("Pilih pelanggan terlebih dahulu");
            return;
        }

        if (isCashPayment && cash < payable) {
            toast.error("Jumlah pembayaran kurang dari total");
            return;
        }

        router.post(
            route("transactions.store"),
            {
                customer_id: selectedCustomer.id,
                discount,
                grand_total: payable,
                cash: isCashPayment ? cash : payable,
                change: isCashPayment ? change : 0,
                payment_gateway: isCashPayment ? null : paymentMethod,
            },
            {
                onSuccess: () => {
                    setDiscountInput("");
                    setCashInput("");
                    setSelectedCustomer(null);
                    setPaymentMethod(defaultPaymentGateway ?? "cash");
                    toast.success("Transaksi berhasil disimpan");
                },
            }
        );
    };

    const qtyDisabled = !product?.id;

    return (
        <>
            <Head title="Transaksi" />

            <div className="space-y-5">
                <div className="grid gap-4 md:grid-cols-3">
                    <Card
                        title="Total Item"
                        icon={<IconShoppingBag size={18} />}
                    >
                        <p className="text-3xl font-semibold text-gray-900 dark:text-white">
                            {cartCount}
                        </p>
                        <p className="text-sm text-gray-500 mt-1">
                            Produk di keranjang
                        </p>
                    </Card>
                    <Card title="Subtotal" icon={<IconReceipt size={18} />}>
                        <p className="text-3xl font-semibold text-gray-900 dark:text-white">
                            {formatPrice(subtotal)}
                        </p>
                        <p className="text-sm text-gray-500 mt-1">
                            Belanja sebelum diskon
                        </p>
                    </Card>
                    <Card title="Kembalian" icon={<IconCash size={18} />}>
                        <p className="text-3xl font-semibold text-gray-900 dark:text-white">
                            {formatPrice(change)}
                        </p>
                        <p className="text-sm text-gray-500 mt-1">
                            {remaining > 0
                                ? `Kurang ${formatPrice(remaining)}`
                                : "Siap diberikan ke pelanggan"}
                        </p>
                    </Card>
                </div>

                <div className="grid gap-5 xl:grid-cols-3">
                    <div className="space-y-5 xl:col-span-2">
                        <Card
                            title="Scan / Cari Produk"
                            icon={<IconBarcode size={20} />}
                            footer={
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p className="text-sm text-gray-500">
                                        {product
                                            ? `Stok tersedia: ${product.stock}`
                                            : "Scan barcode atau ketik manual"}
                                    </p>
                                    <Button
                                        type="submit"
                                        label="Tambahkan ke Keranjang"
                                        icon={<IconShoppingCartPlus size={18} />}
                                        disabled={qtyDisabled}
                                        className={`border bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900 w-full sm:w-auto ${
                                            qtyDisabled ? 'opacity-50 cursor-not-allowed' : ''
                                        }`}
                                    />
                                </div>
                            }
                            form={handleAddToCart}
                        >
                            <div className="grid gap-4 md:grid-cols-[2fr_1fr]">
                                <div className="space-y-3">
                                    <Input
                                        type="text"
                                        label="Scan / Input Barcode"
                                        placeholder="Masukkan barcode produk"
                                        value={barcode}
                                        disabled={isSearching}
                                        onChange={(event) =>
                                            setBarcode(event.target.value)
                                        }
                                        onKeyDown={(event) =>
                                            event.key === "Enter" &&
                                            handleSearchProduct(event)
                                        }
                                    />
                                    <div className="flex gap-3">
                                        <Button
                                            type="button"
                                            onClick={handleSearchProduct}
                                            label={isSearching ? 'Mencari...' : 'Cari Produk'}
                                            className="border bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900 w-full"
                                            disabled={isSearching}
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={resetProductForm}
                                            label="Reset"
                                            className="border bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900 w-full"
                                        />
                                    </div>
                                </div>
                                <div className="rounded-lg border border-dashed border-gray-200 p-4 dark:border-gray-700">
                                    {product ? (
                                        <div className="space-y-2">
                                            <p className="text-sm text-gray-500">
                                                Produk terpilih
                                            </p>
                                            <h4 className="text-lg font-semibold text-gray-900 dark:text-white">
                                                {product.title}
                                            </h4>
                                            <p className="text-sm text-gray-500">
                                                Harga jual
                                            </p>
                                            <p className="text-xl font-semibold text-indigo-500">
                                                {formatPrice(
                                                    product.sell_price
                                                )}
                                            </p>
                                            <div className="mt-4 space-y-2">
                                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                                    Jumlah
                                                </label>
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        className="px-3 py-2 border bg-white text-gray-700 hover:bg-gray-100 rounded-md text-lg font-semibold dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900"
                                                        disabled={
                                                            qtyDisabled ||
                                                            qty <= 1
                                                        }
                                                        onClick={() =>
                                                            setQty((prev) =>
                                                                Math.max(
                                                                    1,
                                                                    Number(
                                                                        prev
                                                                    ) - 1
                                                                )
                                                            )
                                                        }
                                                    >
                                                        -
                                                    </button>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        className="w-full px-3 py-1.5 border text-sm rounded-md text-center focus:outline-none focus:ring-0 bg-white text-gray-700 focus:border-gray-200 border-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-gray-700 dark:border-gray-800"
                                                        value={qty}
                                                        disabled={qtyDisabled}
                                                        onChange={(event) =>
                                                            setQty(
                                                                Math.max(
                                                                    1,
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value
                                                                    ) || 1
                                                                )
                                                            )
                                                        }
                                                    />
                                                    <button
                                                        type="button"
                                                        className="px-3 py-2 border bg-white text-gray-700 hover:bg-gray-100 rounded-md text-lg font-semibold dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900"
                                                        disabled={qtyDisabled}
                                                        onClick={() =>
                                                            setQty(
                                                                (prev) =>
                                                                    Number(
                                                                        prev
                                                                    ) + 1
                                                            )
                                                        }
                                                    >
                                                        +
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="flex h-full flex-col items-center justify-center text-center text-gray-500">
                                            <IconShoppingBag
                                                size={36}
                                                className="mb-2"
                                            />
                                            <p className="text-sm">
                                                Belum ada produk yang dipilih
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </Card>

                        <Table.Card title="Keranjang">
                            <Table>
                                <Table.Thead>
                                    <tr>
                                        <Table.Th className="w-16 text-center">
                                            No
                                        </Table.Th>
                                        <Table.Th>Produk</Table.Th>
                                        <Table.Th className="text-right">
                                            Harga
                                        </Table.Th>
                                        <Table.Th className="text-center">
                                            Qty
                                        </Table.Th>
                                        <Table.Th className="text-right">
                                            Subtotal
                                        </Table.Th>
                                        <Table.Th></Table.Th>
                                    </tr>
                                </Table.Thead>
                                <Table.Tbody>
                                    {carts.length === 0 && (
                                        <tr>
                                            <Table.Td
                                                colSpan={6}
                                                className="py-6 text-center text-gray-500"
                                            >
                                                Keranjang masih kosong
                                            </Table.Td>
                                        </tr>
                                    )}

                                    {carts.map((item, index) => (
                                        <tr
                                            key={`${item.id}-${item.product_id}`}
                                        >
                                            <Table.Td className="text-center">
                                                {index + 1}
                                            </Table.Td>
                                            <Table.Td>
                                                <p className="font-semibold text-gray-900 dark:text-white">
                                                    {item.product.title}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    SKU: {item.product.barcode}
                                                </p>
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {formatPrice(item.price)}
                                            </Table.Td>
                                            <Table.Td className="text-center">
                                                {item.qty}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {formatPrice(
                                                    item.price * item.qty
                                                )}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                <Button
                                                    type="delete"
                                                    icon={
                                                        <IconTrash size={16} />
                                                    }
                                                    url={route(
                                                        "transactions.destroyCart",
                                                        item.id
                                                    )}
                                                    className="border border-rose-200 bg-rose-100 text-rose-500 hover:bg-rose-200 dark:border-rose-800 dark:bg-rose-950"
                                                />
                                            </Table.Td>
                                        </tr>
                                    ))}
                                </Table.Tbody>
                                <tfoot>
                                    <tr>
                                        <Table.Td
                                            colSpan={4}
                                            className="text-right font-semibold"
                                        >
                                            Total
                                        </Table.Td>
                                        <Table.Td className="text-right font-semibold">
                                            {formatPrice(subtotal)}
                                        </Table.Td>
                                        <Table.Td></Table.Td>
                                    </tr>
                                </tfoot>
                            </Table>
                        </Table.Card>
                    </div>

                    <div className="space-y-5">
                        <Card
                            title="Informasi Pelanggan"
                            icon={<IconUser size={18} />}
                        >
                            <div className="space-y-3">
                                <Input
                                    label="Kasir"
                                    type="text"
                                    value={auth.user.name}
                                    disabled
                                />

                                <InputSelect
                                    label="Pelanggan"
                                    data={customers}
                                    selected={selectedCustomer}
                                    setSelected={setSelectedCustomer}
                                    placeholder="Cari nama pelanggan"
                                    errors={errors?.customer_id}
                                    multiple={false}
                                    searchable
                                    displayKey="name"
                                />
                            </div>
                        </Card>

                        <Card
                            title="Ringkasan Pembayaran"
                            icon={<IconReceipt size={18} />}
                        >
                            <div className="space-y-4">
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">
                                        Subtotal
                                    </span>
                                    <span className="font-medium">
                                        {formatPrice(subtotal)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-gray-500">
                                        Diskon
                                    </span>
                                    <span className="font-medium text-rose-500">
                                        - {formatPrice(discount)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-base font-semibold">
                                    <span>Total Bayar</span>
                                    <span>{formatPrice(payable)}</span>
                                </div>

                                <div className="grid gap-3">
                                    <Input
                                        type="text"
                                        inputMode="numeric"
                                        label="Diskon (Rp)"
                                        placeholder="0"
                                        value={discountInput}
                                        onChange={(event) =>
                                            setDiscountInput(
                                                sanitizeNumericInput(
                                                    event.target.value
                                                )
                                            )
                                        }
                                    />
                                    <Input
                                        type="text"
                                        inputMode="numeric"
                                        label={
                                            isCashPayment
                                                ? "Bayar Tunai (Rp)"
                                                : "Nominal Pembayaran"
                                        }
                                        placeholder="0"
                                        value={
                                            isCashPayment
                                                ? cashInput
                                                : payable.toString()
                                        }
                                        disabled={!isCashPayment}
                                        readOnly={!isCashPayment}
                                        onChange={(event) =>
                                            setCashInput(
                                                sanitizeNumericInput(
                                                    event.target.value
                                                )
                                            )
                                        }
                                    />
                                    {!isCashPayment && (
                                        <p className="text-xs text-amber-600">
                                            Nominal mengikuti total tagihan
                                            saat membuat tautan pembayaran.
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-3">
                                    <p className="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        Pilih Metode Pembayaran
                                    </p>
                                    <div className="grid gap-3">
                                        {paymentOptions.map((option) => {
                                            const isActive =
                                                option.value === paymentMethod;
                                            const IconComponent =
                                                option.value === "cash"
                                                    ? IconCash
                                                    : IconCreditCard;

                                            return (
                                                <button
                                                    key={option.value}
                                                    type="button"
                                                    onClick={() =>
                                                        setPaymentMethod(
                                                            option.value
                                                        )
                                                    }
                                                    className={`w-full rounded-xl border p-3 text-left transition ${
                                                        isActive
                                                            ? "border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10"
                                                            : "border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700"
                                                    }`}
                                                >
                                                    <div className="flex items-center justify-between gap-3">
                                                        <div>
                                                            <p className="font-semibold text-gray-900 dark:text-white">
                                                                {option.label}
                                                            </p>
                                                            {option?.description && (
                                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                                    {
                                                                        option.description
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <IconComponent
                                                            size={18}
                                                            className={
                                                                isActive
                                                                    ? "text-indigo-600"
                                                                    : "text-gray-400"
                                                            }
                                                        />
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {!isCashPayment && (
                                        <p className="text-xs text-amber-600">
                                            Tautan pembayaran akan muncul di
                                            halaman invoice setelah transaksi
                                            dibuat.
                                        </p>
                                    )}
                                </div>

                                <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-900/40">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-gray-500">
                                            Metode
                                        </span>
                                        <span className="font-medium">
                                            {activePaymentOption?.label ??
                                                "Tunai"}
                                        </span>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-sm">
                                        <span className="text-gray-500">
                                            {isCashPayment ? "Kembalian" : "Status"}
                                        </span>
                                        <span
                                            className={`font-semibold ${
                                                isCashPayment
                                                    ? "text-emerald-500"
                                                    : "text-amber-500"
                                            }`}
                                        >
                                            {isCashPayment
                                                ? change > 0
                                                    ? formatPrice(change)
                                                    : "-"
                                                : "Menunggu pembayaran"}
                                        </span>
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    label={submitLabel}
                                    icon={<IconArrowRight size={18} />}
                                    onClick={handleSubmitTransaction}
                                    disabled={isSubmitDisabled}
                                    className={`border bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900 w-full ${
                                        isSubmitDisabled
                                            ? "opacity-50 cursor-not-allowed"
                                            : ""
                                    }`}
                                />
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => <DashboardLayout children={page} />;

import React, {
    useEffect,
    useMemo,
    useState,
    useCallback,
    useRef,
} from "react";
import { Head, router, usePage } from "@inertiajs/react";
import axios from "axios";
import toast from "react-hot-toast";
import POSLayout from "@/Layouts/POSLayout";
import ProductGrid from "@/Components/POS/ProductGrid";
import CartPanel from "@/Components/POS/CartPanel";
import PaymentPanel from "@/Components/POS/PaymentPanel";
import CustomerSelect from "@/Components/POS/CustomerSelect";
import NumpadModal from "@/Components/POS/NumpadModal";
import HeldTransactions, {
    HoldButton,
} from "@/Components/POS/HeldTransactions";
import useBarcodeScanner from "@/Hooks/useBarcodeScanner";
import { getProductImageUrl } from "@/Utils/imageUrl";
import { useAuthorization } from "@/Utils/authorization";
import {
    IconUser,
    IconShoppingCart,
    IconReceipt,
    IconKeyboard,
    IconBarcode,
    IconTrash,
    IconCash,
    IconCreditCard,
    IconBuildingBank,
    IconAlertTriangle,
    IconWallet,
} from "@tabler/icons-react";

const formatPrice = (value = 0) =>
    Number(value || 0).toLocaleString("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    });

const formatQty = (value = 0) =>
    Number(value || 0).toLocaleString("id-ID", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });

const parseScannedBarcode = (value = "") => {
    const rawValue = String(value || "").trim();
    const match = rawValue.match(/^(\d+(?:[.,]\d{1,3})?)\*(.+)$/);

    if (!match) {
        return {
            qty: 1,
            barcode: rawValue,
        };
    }

    const qty = Number(match[1].replace(",", "."));

    return {
        qty: qty > 0 ? qty : 1,
        barcode: match[2].trim(),
    };
};

const normalizeBarcode = (value = "") => String(value || "").trim().toLowerCase();

const emptyPricingPreview = {
    items: [],
    summary: {
        base_subtotal: 0,
        promo_discount_total: 0,
        subtotal_after_promo: 0,
        voucher_discount_total: 0,
        loyalty_discount_total: 0,
        manual_discount_total: 0,
        shipping_cost: 0,
        grand_total: 0,
    },
};

const sumCartPrices = (items = []) =>
    items.reduce((total, item) => total + Number(item.price || 0), 0);

const getCartUnitPrice = (item) => {
    const quantity = Number(item?.qty || 0);

    return (
        Number(item?.product_unit?.sell_price || item?.productUnit?.sell_price || 0) ||
        Number(item?.product?.sell_price || 0) ||
        (quantity > 0 ? Number(item?.price || 0) / quantity : 0)
    );
};

const getAxiosErrorMessage = (error, fallback) =>
    error?.response?.data?.message ||
    error?.response?.data?.errors?.message?.[0] ||
    fallback;

export default function Index({
    carts: initialCarts = [],
    carts_total: initialCartsTotal = 0,
    heldCarts = [],
    customers = [],
    products = [],
    categories = [],
    initialPricingPreview = { items: [], summary: {} },
    paymentGateways = [],
    defaultPaymentGateway = "cash",
    bankAccounts = [],
    loyaltyTierOptions = [],
}) {
    const {
        auth,
        errors,
        appSettings = {},
        lowStockNotifications = [],
        activeCashierShift,
    } = usePage().props;
    const isCompactMode =
        appSettings.product_display_mode === "compact_list";
    const { can } = useAuthorization();
    const canOpenShift = can("cashier-shifts-open");

    // State
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedCategory, setSelectedCategory] = useState(null);
    const [isSearching, setIsSearching] = useState(false);
    const [addingProductId, setAddingProductId] = useState(null);
    const [removingItemId, setRemovingItemId] = useState(null);
    const [carts, setCarts] = useState(initialCarts);
    const [cartsTotal, setCartsTotal] = useState(initialCartsTotal);
    const [selectedCustomer, setSelectedCustomer] = useState(null);
    const [pricingPreview, setPricingPreview] = useState(initialPricingPreview);
    const [isLoadingPricing, setIsLoadingPricing] = useState(false);
    const [discountInput, setDiscountInput] = useState("");
    const [redeemPointsInput, setRedeemPointsInput] = useState("");
    const [cashInput, setCashInput] = useState("");
    const [shippingInput, setShippingInput] = useState("");
    const [paymentMethod, setPaymentMethod] = useState(
        defaultPaymentGateway ?? "cash"
    );
    const [payLater, setPayLater] = useState(false);
    const [dueDate, setDueDate] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [mobileView, setMobileView] = useState("products"); // 'products' | 'cart'
    const [numpadOpen, setNumpadOpen] = useState(false);
    const [showShortcuts, setShowShortcuts] = useState(false);
    const [selectedBankAccount, setSelectedBankAccount] = useState(null);
    const [selectedVoucherId, setSelectedVoucherId] = useState("");
    const [openingCashInput, setOpeningCashInput] = useState("");
    const [shiftNotesInput, setShiftNotesInput] = useState("");
    const qtyUpdateTimers = useRef({});
    const qtyUpdateSnapshots = useRef({});
    const qtyUpdateSequences = useRef({});
    const pricingItemsByCartId = useMemo(() => {
        const items = pricingPreview?.items || [];

        return items.reduce((accumulator, item) => {
            accumulator[item.cart_id] = item;

            return accumulator;
        }, {});
    }, [pricingPreview]);

    // Ref for search input to enable keyboard focus
    const searchInputRef = useRef(null);
    const handleAddToCartRef = useRef(null);

    // Set default payment method
    useEffect(() => {
        setPaymentMethod(defaultPaymentGateway ?? "cash");
    }, [defaultPaymentGateway]);

    useEffect(() => {
        setPricingPreview(initialPricingPreview);
    }, [initialPricingPreview]);

    useEffect(() => {
        setCarts(initialCarts);
        setCartsTotal(initialCartsTotal);
    }, [initialCarts, initialCartsTotal]);

    const findProductByBarcode = useCallback(
        (barcode) => {
            const normalizedBarcode = normalizeBarcode(barcode);

            if (!normalizedBarcode) {
                return { product: null, unit: null };
            }

            for (const product of products) {
                const unit = product.units?.find(
                    (productUnit) =>
                        normalizeBarcode(productUnit.barcode) ===
                        normalizedBarcode
                );

                if (unit) {
                    return { product, unit };
                }
            }

            const product = products.find(
                (item) => normalizeBarcode(item.barcode) === normalizedBarcode
            );

            if (!product) {
                return { product: null, unit: null };
            }

            const unit =
                product.units?.find(
                    (productUnit) =>
                        normalizeBarcode(productUnit.barcode) ===
                        normalizedBarcode
                ) ||
                product.units?.find((productUnit) => productUnit.is_base_unit) ||
                product.units?.[0] ||
                null;

            return { product, unit };
        },
        [products]
    );

    // Barcode scanner integration
    const handleBarcodeScan = useCallback(
        (scanText) => {
            const { qty, barcode } = parseScannedBarcode(scanText);
            const { product, unit: scannedUnit } = findProductByBarcode(barcode);

            if (product) {
                const unitConversionQty = Number(scannedUnit?.conversion_qty || 1);
                const baseQty = qty * unitConversionQty;

                if (Number(product.stock || 0) >= baseQty) {
                    handleAddToCartRef.current?.(product, scannedUnit, qty);
                    setSearchQuery("");
                    return true;
                } else {
                    toast.error(`${product.title} stok habis`);
                    return false;
                }
            } else {
                toast.error(`Produk tidak ditemukan: ${barcode || scanText}`);
                return false;
            }
        },
        [findProductByBarcode]
    );

    const { isScanning } = useBarcodeScanner(handleBarcodeScan, {
        enabled: true,
        minLength: 3,
    });

    const LowStockAlerts = () => null;

    // Calculations
    const discount = useMemo(
        () => Math.max(0, Number(discountInput) || 0),
        [discountInput]
    );
    const shipping = useMemo(
        () => Math.max(0, Number(shippingInput) || 0),
        [shippingInput]
    );
    const baseSubtotal = useMemo(
        () => Number(pricingPreview?.summary?.base_subtotal ?? cartsTotal ?? 0),
        [pricingPreview, cartsTotal]
    );
    const promoDiscount = useMemo(
        () => Number(pricingPreview?.summary?.promo_discount_total ?? 0),
        [pricingPreview]
    );
    const voucherDiscount = useMemo(
        () => Number(pricingPreview?.summary?.voucher_discount_total ?? 0),
        [pricingPreview]
    );
    const loyaltyDiscount = useMemo(
        () => Number(pricingPreview?.summary?.loyalty_discount_total ?? 0),
        [pricingPreview]
    );
    const subtotal = useMemo(
        () => Number(pricingPreview?.summary?.subtotal_after_promo ?? 0),
        [pricingPreview]
    );
    const payable = useMemo(
        () => Number(pricingPreview?.summary?.grand_total ?? 0),
        [pricingPreview]
    );
    const isCashPayment = !payLater && paymentMethod === "cash";
    const isBankTransfer = !payLater && paymentMethod === "bank_transfer";
    const cash = useMemo(
        () => (isCashPayment ? Math.max(0, Number(cashInput) || 0) : payable),
        [cashInput, isCashPayment, payable]
    );
    const cartCount = useMemo(
        () => carts.reduce((total, item) => total + Number(item.qty), 0),
        [carts]
    );
    useEffect(() => {
        if (carts.length === 0) {
            setPricingPreview(emptyPricingPreview);

            return;
        }

        let cancelled = false;
        setIsLoadingPricing(true);

        axios
            .post(route("transactions.pricing-preview"), {
                customer_id: selectedCustomer?.id ?? null,
                discount,
                shipping_cost: shipping,
                redeem_points: Number(redeemPointsInput || 0),
                customer_voucher_id: selectedVoucherId || null,
            })
            .then((response) => {
                if (!cancelled) {
                    setPricingPreview(response.data?.data ?? initialPricingPreview);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    toast.error("Gagal memuat promo aktif");
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setIsLoadingPricing(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [
        selectedCustomer?.id,
        discount,
        shipping,
        redeemPointsInput,
        selectedVoucherId,
    ]);

    useEffect(() => {
        if (!selectedCustomer?.is_loyalty_member) {
            setRedeemPointsInput("");
            setSelectedVoucherId("");
        }
    }, [selectedCustomer?.id, selectedCustomer?.is_loyalty_member]);

    useEffect(() => {
        const eligibleVoucherIds = new Set(
            (pricingPreview?.eligible_vouchers || []).map((voucher) =>
                String(voucher.id)
            )
        );

        if (selectedVoucherId && !eligibleVoucherIds.has(selectedVoucherId)) {
            setSelectedVoucherId("");
        }
    }, [pricingPreview?.eligible_vouchers, selectedVoucherId]);

    // Payment options
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

    // Auto-set cash input for non-cash payment
    useEffect(() => {
        if (!isCashPayment && payable >= 0) {
            setCashInput(String(payable));
        }
    }, [isCashPayment, payable]);

    const handleOpenShift = () => {
        router.post(route("cashier-shifts.store"), {
            opening_cash: Number(openingCashInput || 0),
            notes: shiftNotesInput,
            redirect_to: "transactions",
        });
    };

    const cartMutationPayload = useCallback(
        () => ({
            customer_id: selectedCustomer?.id ?? null,
            discount,
            shipping_cost: shipping,
            redeem_points: Number(redeemPointsInput || 0),
            customer_voucher_id: selectedVoucherId || null,
        }),
        [
            selectedCustomer?.id,
            discount,
            shipping,
            redeemPointsInput,
            selectedVoucherId,
        ]
    );

    const applyCartResponse = useCallback((payload = {}) => {
        const nextCarts = payload.carts ?? [];

        setCarts(nextCarts);
        setCartsTotal(Number(payload.carts_total ?? sumCartPrices(nextCarts)));
        setPricingPreview(payload.pricingPreview ?? emptyPricingPreview);
    }, []);

    useEffect(() => {
        return () => {
            Object.values(qtyUpdateTimers.current).forEach((timer) =>
                clearTimeout(timer)
            );
        };
    }, []);

    // Handle add product to cart
    const handleAddToCart = useCallback(async (product, selectedUnit = null, qty = 1) => {
        if (!product?.id) return;
        const unit =
            selectedUnit ||
            product.units?.find((productUnit) => productUnit.is_base_unit) ||
            product.units?.[0] ||
            null;
        const itemQty = Math.max(0.001, Number(qty) || 1);
        const snapshot = { carts, cartsTotal, pricingPreview };
        const unitId = unit?.id ?? null;
        const unitPrice = Number(unit?.sell_price || product.sell_price || 0);

        setAddingProductId(product.id);

        setCarts((currentCarts) => {
            let matched = false;
            const nextCarts = currentCarts.map((item) => {
                const sameProduct = Number(item.product_id) === Number(product.id);
                const sameUnit = unitId === null
                    ? sameProduct
                    : String(item.product_unit_id ?? "") === String(unitId);

                if (!sameProduct || !sameUnit) {
                    return item;
                }

                matched = true;
                const nextQty = Number(item.qty || 0) + itemQty;

                return {
                    ...item,
                    qty: nextQty,
                    price: Math.round(unitPrice * nextQty),
                };
            });

            if (!matched) {
                nextCarts.unshift({
                    id: `temp-${product.id}-${Date.now()}`,
                    cashier_id: auth?.user?.id ?? null,
                    product_id: product.id,
                    product_unit_id: unitId,
                    unit_label: unit?.label || "unit",
                    unit_conversion_qty: unit?.conversion_qty || 1,
                    qty: itemQty,
                    price: Math.round(unitPrice * itemQty),
                    product,
                    product_unit: unit,
                });
            }

            setCartsTotal(sumCartPrices(nextCarts));

            return nextCarts;
        });

        try {
            const response = await axios.post(route("transactions.addToCart"), {
                product_id: product.id,
                product_unit_id: unitId,
                qty: itemQty,
                ...cartMutationPayload(),
            });

            applyCartResponse(response.data);
            toast.success(
                `${formatQty(itemQty)} ${unit?.label || "unit"} ${
                    product.title
                } ditambahkan`
            );
        } catch (error) {
            setCarts(snapshot.carts);
            setCartsTotal(snapshot.cartsTotal);
            setPricingPreview(snapshot.pricingPreview);
            toast.error(getAxiosErrorMessage(error, "Gagal menambahkan produk"));
        } finally {
            setAddingProductId(null);
        }
    }, [
        applyCartResponse,
        auth?.user?.id,
        cartMutationPayload,
        carts,
        cartsTotal,
        pricingPreview,
    ]);

    useEffect(() => {
        handleAddToCartRef.current = handleAddToCart;
    }, [handleAddToCart]);

    // Handle update cart quantity
    const [updatingCartId, setUpdatingCartId] = useState(null);

    const handleUpdateQty = useCallback((cartId, newQty) => {
        if (newQty < 1) return;
        setUpdatingCartId(cartId);

        if (!qtyUpdateSnapshots.current[cartId]) {
            qtyUpdateSnapshots.current[cartId] = {
                carts,
                cartsTotal,
                pricingPreview,
            };
        }

        setCarts((currentCarts) => {
            const nextCarts = currentCarts.map((item) => {
                if (String(item.id) !== String(cartId)) {
                    return item;
                }

                return {
                    ...item,
                    qty: newQty,
                    price: Math.round(getCartUnitPrice(item) * newQty),
                };
            });

            setCartsTotal(sumCartPrices(nextCarts));

            return nextCarts;
        });

        clearTimeout(qtyUpdateTimers.current[cartId]);
        qtyUpdateSequences.current[cartId] =
            (qtyUpdateSequences.current[cartId] || 0) + 1;
        const sequence = qtyUpdateSequences.current[cartId];

        qtyUpdateTimers.current[cartId] = setTimeout(async () => {
            try {
                const response = await axios.patch(
                    route("transactions.updateCart", cartId),
                    {
                        qty: newQty,
                        ...cartMutationPayload(),
                    }
                );

                if (qtyUpdateSequences.current[cartId] !== sequence) {
                    return;
                }

                applyCartResponse(response.data);
                delete qtyUpdateSnapshots.current[cartId];
            } catch (error) {
                if (qtyUpdateSequences.current[cartId] !== sequence) {
                    return;
                }

                const snapshot = qtyUpdateSnapshots.current[cartId];

                if (snapshot) {
                    setCarts(snapshot.carts);
                    setCartsTotal(snapshot.cartsTotal);
                    setPricingPreview(snapshot.pricingPreview);
                    delete qtyUpdateSnapshots.current[cartId];
                }

                toast.error(getAxiosErrorMessage(error, "Gagal update quantity"));
            } finally {
                if (qtyUpdateSequences.current[cartId] === sequence) {
                    setUpdatingCartId(null);
                    delete qtyUpdateTimers.current[cartId];
                }
            }
        }, 250);
    }, [
        applyCartResponse,
        cartMutationPayload,
        carts,
        cartsTotal,
        pricingPreview,
    ]);

    // Handle numpad confirm for cash input
    const handleNumpadConfirm = useCallback((value) => {
        setCashInput(String(value));
    }, []);

    // Handle hold transaction
    const [isHolding, setIsHolding] = useState(false);

    const handleHoldCart = async (label = null) => {
        if (carts.length === 0) {
            toast.error("Keranjang kosong");
            return;
        }

        setIsHolding(true);

        router.post(
            route("transactions.hold"),
            { label },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success("Transaksi ditahan");
                    setIsHolding(false);
                },
                onError: (errors) => {
                    toast.error(errors?.message || "Gagal menahan transaksi");
                    setIsHolding(false);
                },
            }
        );
    };

    // Keyboard shortcuts
    useEffect(() => {
        const handleKeyDown = (e) => {
            // Don't trigger if user is typing in an input
            if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA")
                return;

            switch (e.key) {
                case "/":
                case "F5":
                    e.preventDefault();
                    // Focus search input
                    if (searchInputRef.current) {
                        searchInputRef.current.focus();
                    }
                    break;
                case "F1":
                    e.preventDefault();
                    setNumpadOpen(true);
                    break;
                case "F2":
                    e.preventDefault();
                    if (carts.length > 0)
                        handleSubmitTransaction();
                    break;
                case "F3":
                    e.preventDefault();
                    setMobileView(
                        mobileView === "products" ? "cart" : "products"
                    );
                    break;
                case "F4":
                    e.preventDefault();
                    setShowShortcuts(!showShortcuts);
                    break;
                case "Escape":
                    setNumpadOpen(false);
                    setShowShortcuts(false);
                    setSearchQuery("");
                    break;
            }
        };

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [carts, selectedCustomer, mobileView, showShortcuts]);

    // Handle remove from cart
    const handleRemoveFromCart = useCallback(async (cartId) => {
        const snapshot = { carts, cartsTotal, pricingPreview };
        setRemovingItemId(cartId);

        setCarts((currentCarts) => {
            const nextCarts = currentCarts.filter(
                (item) => String(item.id) !== String(cartId)
            );

            setCartsTotal(sumCartPrices(nextCarts));

            if (nextCarts.length === 0) {
                setPricingPreview(emptyPricingPreview);
            }

            return nextCarts;
        });

        try {
            const response = await axios.delete(
                route("transactions.destroyCart", cartId),
                {
                    data: cartMutationPayload(),
                }
            );

            applyCartResponse(response.data);
            toast.success("Item dihapus dari keranjang");
        } catch (error) {
            setCarts(snapshot.carts);
            setCartsTotal(snapshot.cartsTotal);
            setPricingPreview(snapshot.pricingPreview);
            toast.error(getAxiosErrorMessage(error, "Gagal menghapus item"));
        } finally {
            setRemovingItemId(null);
        }
    }, [
        applyCartResponse,
        cartMutationPayload,
        carts,
        cartsTotal,
        pricingPreview,
    ]);

    // Handle submit transaction
    const handleSubmitTransaction = () => {
        if (carts.length === 0) {
            toast.error("Keranjang masih kosong");
            return;
        }

        if (payLater && !dueDate) {
            toast.error("Isi tanggal jatuh tempo untuk nota barang");
            return;
        }

        if (payLater && !selectedCustomer?.id) {
            toast.error("Pilih pelanggan untuk nota barang/piutang");
            return;
        }

        if (!payLater && isCashPayment && cash < payable) {
            toast.error("Jumlah pembayaran kurang dari total");
            return;
        }

        if (isBankTransfer && !selectedBankAccount) {
            toast.error("Pilih rekening bank tujuan");
            return;
        }

        setIsSubmitting(true);

        router.post(
            route("transactions.store"),
            {
                customer_id: selectedCustomer?.id ?? null,
                discount,
                redeem_points: Number(redeemPointsInput || 0),
                customer_voucher_id: selectedVoucherId || null,
                shipping_cost: shipping,
                grand_total: payable,
                cash: isCashPayment ? cash : payable,
                change: isCashPayment ? Math.max(cash - payable, 0) : 0,
                payment_gateway: payLater ? null : isCashPayment ? null : paymentMethod,
                bank_account_id: isBankTransfer
                    ? selectedBankAccount?.id
                    : null,
                pay_later: payLater,
                due_date: dueDate,
            },
            {
                onSuccess: () => {
                    setDiscountInput("");
                    setRedeemPointsInput("");
                    setCashInput("");
                    setShippingInput("");
                    setSelectedCustomer(null);
                    setSelectedBankAccount(null);
                    setSelectedVoucherId("");
                    setPaymentMethod(defaultPaymentGateway ?? "cash");
                    setPayLater(false);
                    setDueDate("");
                    setIsSubmitting(false);
                    toast.success("Transaksi berhasil!");
                },
                onError: () => {
                    setIsSubmitting(false);
                    toast.error("Gagal menyimpan transaksi");
                },
            }
        );
    };

    if (!activeCashierShift) {
        return (
            <>
                <Head title="Buka Shift Kasir" />

                <div className="mx-auto flex min-h-[calc(100vh-8rem)] max-w-3xl items-center justify-center px-4 py-10">
                    <div className="w-full rounded-3xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-200/60 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                        <div className="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <IconWallet size={28} />
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                            Shift kasir belum dibuka
                        </h1>
                        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Buka shift terlebih dulu untuk mengaktifkan transaksi, keranjang, dan cash closing.
                        </p>

                        <div className="mt-6 grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Modal Awal
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    value={openingCashInput}
                                    onChange={(event) => setOpeningCashInput(event.target.value)}
                                    className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    placeholder="0"
                                />
                                {errors?.opening_cash && (
                                    <p className="mt-2 text-xs text-rose-500">{errors.opening_cash}</p>
                                )}
                            </div>
                            <div>
                                <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    Catatan
                                </label>
                                <input
                                    type="text"
                                    value={shiftNotesInput}
                                    onChange={(event) => setShiftNotesInput(event.target.value)}
                                    className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-800 outline-none transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    placeholder="Opsional"
                                />
                            </div>
                        </div>

                        <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                            {canOpenShift && (
                                <button
                                    type="button"
                                    onClick={handleOpenShift}
                                    className="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary-500 px-5 py-3 text-sm font-medium text-white transition-colors hover:bg-primary-600"
                                >
                                    <IconWallet size={18} />
                                    <span>Buka Shift Sekarang</span>
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={() => router.visit(route("cashier-shifts.index"))}
                                className="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 px-5 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                <span>Lihat Histori Shift</span>
                            </button>
                        </div>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Transaksi" />

            <div className="h-[calc(100vh-4rem)] flex flex-col lg:flex-row">
                {/* Mobile Tab Switcher */}
                <div className="lg:hidden flex border-b border-hairline-light dark:border-hairline-dark bg-white dark:bg-canvas-night-elevated">
                    <button
                        onClick={() => setMobileView("products")}
                        className={`flex-1 flex items-center justify-center gap-2 py-3 text-sm font-medium transition-colors ${
                            mobileView === "products"
                                ? "text-ink border-b-2 border-ink"
                                : "text-shade-50"
                        }`}
                    >
                        <IconShoppingCart size={18} />
                        <span>Produk</span>
                    </button>
                    <button
                        onClick={() => setMobileView("cart")}
                        className={`flex-1 flex items-center justify-center gap-2 py-3 text-sm font-medium transition-colors relative ${
                            mobileView === "cart"
                                ? "text-ink border-b-2 border-ink"
                                : "text-shade-50"
                        }`}
                    >
                        <IconReceipt size={18} />
                        <span className="relative inline-flex items-center gap-1">
                            Keranjang
                            {cartCount > 0 && (
                                <span className="inline-flex items-center justify-center px-1.5 min-w-[20px] h-5 text-[11px] font-bold bg-ink text-white rounded-full">
                                    {cartCount}
                                </span>
                            )}
                        </span>
                    </button>
                </div>

                {/* Left Panel - Products */}
                <div
                    className={`flex-1 bg-canvas-cream dark:bg-canvas-night overflow-hidden ${
                        mobileView !== "products"
                            ? "hidden lg:flex lg:flex-col"
                            : "flex flex-col"
                    }`}
                >
                    <ProductGrid
                        products={products}
                        categories={categories}
                        selectedCategory={selectedCategory}
                        onCategoryChange={(categoryId) =>
                            setSelectedCategory(
                                categoryId === null ? null : Number(categoryId)
                            )
                        }
                        searchQuery={searchQuery}
                        onSearchChange={setSearchQuery}
                        onSearch={handleBarcodeScan}
                        isSearching={isSearching}
                        onAddToCart={handleAddToCart}
                        addingProductId={addingProductId}
                        searchInputRef={searchInputRef}
                    />
                </div>

                {/* Right Panel - Cart & Payment */}
                <div
                    className={`w-full lg:w-[420px] xl:w-[480px] flex flex-col bg-white dark:bg-canvas-night-elevated border-l border-hairline-light dark:border-hairline-dark min-h-0 overflow-hidden ${
                        mobileView !== "cart" ? "hidden lg:flex" : "flex"
                    }`}
                    style={{ height: "calc(100vh - 4rem)" }}
                >
                    {/* Customer Select - Fixed */}
                    <div className="p-3 border-b border-hairline-light dark:border-hairline-dark flex-shrink-0">
                        <CustomerSelect
                            customers={customers}
                            selected={selectedCustomer}
                            onSelect={setSelectedCustomer}
                            placeholder="Pembeli sekali beli"
                            error={errors?.customer_id}
                            label="Pelanggan (opsional)"
                            tierOptions={loyaltyTierOptions}
                        />
                    </div>

                    {/* Held Transactions & Alerts */}
                    {heldCarts.length > 0 && (
                        <div className="p-3 border-b border-hairline-light dark:border-hairline-dark">
                            <HeldTransactions
                                heldCarts={heldCarts}
                                hasActiveCart={carts.length > 0}
                            />
                        </div>
                    )}

                    {/* Cart Items - Scrollable */}
                    <div className="flex-1 overflow-y-auto min-h-0">
                        {/* Hold Button - at top of cart section */}
                        {carts.length > 0 && (
                            <div className="p-3 border-b border-hairline-light dark:border-hairline-dark">
                                <HoldButton
                                    hasItems={carts.length > 0}
                                    onHold={handleHoldCart}
                                    isHolding={isHolding}
                                />
                            </div>
                        )}

                        <div className="p-3 border-b border-hairline-light dark:border-hairline-dark">
                            <div className="flex items-center justify-between mb-3">
                                <h3 className="text-sm font-semibold text-ink dark:text-slate-300 flex items-center gap-2">
                                    <IconShoppingCart size={16} />
                                    Keranjang
                                </h3>
                                {carts.length > 0 && (
                                    <span className="px-2.5 py-0.5 text-xs font-bold bg-aloe-100 text-ink dark:bg-hairline-dark dark:text-primary-300 rounded-full whitespace-nowrap">
                                        {cartCount} item
                                    </span>
                                )}
                            </div>

                            {carts.length > 0 ? (
                                <div className="space-y-2 max-h-[200px] overflow-y-auto pr-1">
                                    {carts.map((item) => (
                                        (() => {
                                            const pricingItem =
                                                pricingItemsByCartId[item.id];
                                            const baseLineTotal = Number(
                                                pricingItem?.line_base_total ??
                                                    item.price ??
                                                    0
                                            );
                                            const effectiveLineTotal = Number(
                                                pricingItem?.line_total ??
                                                    item.price ??
                                                    0
                                            );
                                            const effectiveUnitPrice = Number(
                                                pricingItem?.effective_unit_price ??
                                                    item.product?.sell_price ??
                                                    0
                                            );
                                            const baseUnitPrice = Number(
                                                pricingItem?.base_unit_price ??
                                                    item.product?.sell_price ??
                                                    0
                                            );
                                            const pricingRule =
                                                pricingItem?.pricing_rule;
                                            const unitLabel =
                                                item.unit_label ||
                                                item.product_unit?.label ||
                                                "unit";
                                            const itemQty = Number(item.qty || 0);

                                            return (
                                        <div
                                            key={item.id}
                                            className="flex items-center gap-2 p-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 group"
                                        >
                                            {!isCompactMode && (
                                                <div className="w-10 h-10 rounded-lg bg-slate-200 dark:bg-slate-700 overflow-hidden flex-shrink-0">
                                                    {item.product?.image ? (
                                                        <img
                                                            src={getProductImageUrl(
                                                                item.product.image
                                                            )}
                                                            alt={item.product.title}
                                                            className="w-full h-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="w-full h-full flex items-center justify-center">
                                                            <IconShoppingCart
                                                                size={14}
                                                                className="text-slate-400"
                                                            />
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                            <div className="flex-1 min-w-0">
                                                <p className="text-xs font-medium text-slate-700 dark:text-slate-300 truncate">
                                                    {item.product?.title ||
                                                        "Produk"}
                                                </p>
                                                <div className="text-xs text-slate-500">
                                                    {pricingRule &&
                                                        effectiveUnitPrice <
                                                            baseUnitPrice && (
                                                            <p className="line-through text-slate-400">
                                                                {formatPrice(
                                                                    baseUnitPrice
                                                                )}{" "}
                                                                x {formatQty(itemQty)} {unitLabel}
                                                            </p>
                                                        )}
                                                    <p>
                                                        {formatPrice(
                                                            effectiveUnitPrice
                                                        )}{" "}
                                                        x {formatQty(itemQty)} {unitLabel}
                                                    </p>
                                                    {pricingRule && (
                                                        <p className="mt-0.5 text-[11px] font-medium text-rose-500">
                                                            {pricingRule.name}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <button
                                                    onClick={() =>
                                                        handleUpdateQty(
                                                            item.id,
                                                            Math.max(
                                                                1,
                                                                itemQty - 1
                                                            )
                                                        )
                                                    }
                                                    disabled={itemQty <= 1}
                                                    className="w-6 h-6 rounded flex items-center justify-center bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300 disabled:opacity-50 text-xs"
                                                >
                                                    -
                                                </button>
                                                <span className="w-6 text-center text-xs font-medium">
                                                    {formatQty(itemQty)}
                                                </span>
                                                <button
                                                    onClick={() =>
                                                        handleUpdateQty(
                                                            item.id,
                                                            itemQty + 1
                                                        )
                                                    }
                                                    className="w-6 h-6 rounded flex items-center justify-center bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300 text-xs"
                                                >
                                                    +
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        handleRemoveFromCart(
                                                            item.id
                                                        )
                                                    }
                                                    className="w-6 h-6 rounded flex items-center justify-center text-slate-400 hover:text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-950/50 ml-1"
                                                >
                                                    <IconTrash size={12} />
                                                </button>
                                            </div>
                                            <p className="text-xs font-semibold text-primary-600 dark:text-primary-400 w-16 text-right">
                                                {formatPrice(
                                                    effectiveLineTotal
                                                )}
                                            </p>
                                        </div>
                                            );
                                        })()
                                    ))}
                                </div>
                            ) : (
                                <div className="py-6 text-center">
                                    <IconShoppingCart
                                        size={32}
                                        className="mx-auto text-slate-300 dark:text-slate-600 mb-2"
                                    />
                                    <p className="text-sm text-slate-400">
                                        Keranjang kosong
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Payment Details - Scrollable */}
                        <div className="p-3 space-y-4">
                            {/* Pay later toggle */}
                            <div className="flex items-center justify-between p-3 rounded-card border border-hairline-light dark:border-hairline-dark bg-canvas-cream dark:bg-canvas-night">
                                <div>
                                    <p className="text-sm font-semibold text-ink dark:text-white">
                                        Bayar Belakangan (Nota Barang)
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        Tidak perlu bayar sekarang, catat sebagai piutang.
                                    </p>
                                </div>
                                <label className="inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="sr-only"
                                        checked={payLater}
                                        onChange={(e) => {
                                            setPayLater(e.target.checked);
                                            if (e.target.checked) {
                                                setSelectedBankAccount(null);
                                                setPaymentMethod("cash");
                                            }
                                        }}
                                    />
                                    <span
                                        className={`w-11 h-6 flex items-center bg-shade-30 rounded-full p-1 transition ${
                                            payLater ? "bg-ink" : ""
                                        }`}
                                    >
                                        <span
                                            className={`bg-white w-4 h-4 rounded-full shadow transform transition ${
                                                payLater ? "translate-x-5" : ""
                                            }`}
                                        />
                                    </span>
                                </label>
                            </div>

                            {payLater && (
                                <div>
                                    <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-2">
                                        Tanggal Jatuh Tempo
                                    </label>
                                    <input
                                        type="date"
                                        value={dueDate}
                                        onChange={(e) => setDueDate(e.target.value)}
                                        className="w-full h-11 px-3 rounded-md border border-hairline-light dark:border-hairline-dark bg-white dark:bg-canvas-night-elevated text-sm focus:ring-4 focus:ring-aloe-100/70 focus:border-ink"
                                    />
                                </div>
                            )}

                            {/* Payment Method Selection */}
                            <div>
                                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-2">
                                    Metode Pembayaran
                                </label>
                                <div className="grid grid-cols-2 gap-2">
                                    {paymentOptions.map((method) => (
                                        <button
                                            key={method.value}
                                            onClick={() =>
                                                !payLater &&
                                                setPaymentMethod(method.value)
                                            }
                                            disabled={payLater}
                                            className={`p-3 rounded-card border transition-all flex items-center gap-2 ${
                                                paymentMethod === method.value && !payLater
                                                    ? "border-ink bg-aloe-100 dark:bg-hairline-dark"
                                                    : "border-hairline-light dark:border-hairline-dark hover:border-shade-30 dark:hover:border-slate-600"
                                            } ${payLater ? "opacity-50 cursor-not-allowed" : ""}`}
                                        >
                                            <div
                                                className={`w-8 h-8 rounded-lg flex items-center justify-center ${
                                                    paymentMethod ===
                                                        method.value &&
                                                    !payLater
                                                        ? "bg-ink text-white"
                                                        : "bg-canvas-cream dark:bg-canvas-night text-slate-500"
                                                }`}
                                            >
                                                {method.value === "cash" ? (
                                                    <IconCash size={16} />
                                                ) : method.value ===
                                                  "bank_transfer" ? (
                                                    <IconBuildingBank
                                                        size={16}
                                                    />
                                                ) : (
                                                    <IconCreditCard size={16} />
                                                )}
                                            </div>
                                            <div className="text-left">
                                                <p
                                                    className={`text-sm font-semibold ${
                                                        paymentMethod ===
                                                        method.value
                                                            ? "text-ink dark:text-primary-300"
                                                            : "text-slate-700 dark:text-slate-300"
                                                    }`}
                                                >
                                                    {method.label}
                                                </p>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Bank Selector - Only for bank_transfer */}
                            {paymentMethod === "bank_transfer" &&
                                bankAccounts.length > 0 &&
                                !payLater && (
                                    <div>
                                        <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-2">
                                            Rekening Tujuan
                                        </label>
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            {bankAccounts.map((bank) => {
                                                const isActive =
                                                    selectedBankAccount?.id ===
                                                    bank.id;
                                                return (
                                                    <button
                                                        key={bank.id}
                                                        onClick={() =>
                                                            setSelectedBankAccount(
                                                                bank
                                                            )
                                                        }
                                                        className={`p-3 rounded-card border transition-colors flex items-center gap-3 text-left ${
                                                            isActive
                                                                ? "border-ink bg-aloe-100 dark:bg-hairline-dark"
                                                                : "border-hairline-light dark:border-hairline-dark hover:border-shade-30 dark:hover:border-primary-800"
                                                        }`}
                                                    >
                                                        <div className="w-10 h-10 rounded-md bg-white dark:bg-canvas-night border border-hairline-light dark:border-hairline-dark flex items-center justify-center overflow-hidden">
                                                            {bank.logo_url ? (
                                                                <img
                                                                    src={
                                                                        bank.logo_url
                                                                    }
                                                                    alt={
                                                                        bank.bank_name
                                                                    }
                                                                    className="max-w-full max-h-full object-contain"
                                                                />
                                                            ) : (
                                                                <IconBuildingBank
                                                                    size={18}
                                                                    className="text-slate-500"
                                                                />
                                                            )}
                                                        </div>
                                                        <div className="flex-1">
                                                            <p className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                                                {
                                                                    bank.bank_name
                                                                }
                                                            </p>
                                                            <p className="text-xs text-slate-600 dark:text-slate-400">
                                                                {
                                                                    bank.account_number
                                                                }
                                                            </p>
                                                            <p className="text-[11px] text-slate-500 dark:text-slate-500">
                                                                a.n.{" "}
                                                                {
                                                                    bank.account_name
                                                                }
                                                            </p>
                                                        </div>
                                                        {isActive && (
                                                            <span className="text-[11px] font-semibold text-primary-600">
                                                                Dipilih
                                                            </span>
                                                        )}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                            {/* Quick Amounts - Only for cash */}
                            {paymentMethod === "cash" && !payLater && (
                                <div>
                                    <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-2">
                                        Nominal Cepat
                                    </label>
                                    <div className="grid grid-cols-4 gap-2">
                                        {[10000, 20000, 50000, 100000].map(
                                            (amt) => (
                                                <button
                                                    key={amt}
                                                    onClick={() =>
                                                        setCashInput(
                                                            String(amt)
                                                        )
                                                    }
                                                    className={`py-2 px-1 rounded-lg text-xs font-semibold transition-all ${
                                                        Number(cashInput) ===
                                                        amt
                                                            ? "bg-primary-500 text-white"
                                                            : "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200"
                                                    }`}
                                                >
                                                    {formatPrice(amt)}
                                                </button>
                                            )
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Discount Input */}
                            {promoDiscount > 0 && (
                                <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                                                Promo otomatis aktif
                                            </p>
                                            <p className="text-xs text-emerald-600/80 dark:text-emerald-400/80">
                                                Harga item sudah disesuaikan berdasarkan rule promo yang berlaku.
                                            </p>
                                        </div>
                                        <span className="text-sm font-bold text-emerald-700 dark:text-emerald-300">
                                            -{formatPrice(promoDiscount)}
                                        </span>
                                    </div>
                                </div>
                            )}

                            {selectedCustomer?.is_loyalty_member && (
                                <div className="rounded-xl border border-primary-200 bg-primary-50 p-3 dark:border-primary-900/40 dark:bg-primary-950/20">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-semibold text-primary-700 dark:text-primary-300">
                                                Loyalty Member
                                            </p>
                                            <p className="text-xs text-primary-600/80 dark:text-primary-400/80">
                                                Tier {selectedCustomer.loyalty_tier} | saldo{" "}
                                                {pricingPreview?.summary
                                                    ?.available_loyalty_points ??
                                                    0}{" "}
                                                poin
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {selectedCustomer?.is_loyalty_member && (
                                <div>
                                    <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-2">
                                        Redeem Poin
                                    </label>
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        value={redeemPointsInput}
                                        onChange={(e) =>
                                            setRedeemPointsInput(
                                                e.target.value.replace(
                                                    /[^\d]/g,
                                                    ""
                                                )
                                            )
                                        }
                                        placeholder={`Maks ${
                                            pricingPreview?.summary
                                                ?.available_loyalty_points ?? 0
                                        } poin`}
                                        className="w-full h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                                    />
                                </div>
                            )}

                            {selectedCustomer?.is_loyalty_member &&
                                (pricingPreview?.eligible_vouchers || [])
                                    .length > 0 && (
                                    <div>
                                        <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-2">
                                            Voucher Customer
                                        </label>
                                        <select
                                            value={selectedVoucherId}
                                            onChange={(e) =>
                                                setSelectedVoucherId(
                                                    e.target.value
                                                )
                                            }
                                            className="w-full h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                                        >
                                            <option value="">
                                                Tanpa voucher
                                            </option>
                                            {(
                                                pricingPreview?.eligible_vouchers ||
                                                []
                                            ).map((voucher) => (
                                                <option
                                                    key={voucher.id}
                                                    value={voucher.id}
                                                >
                                                    {voucher.code} -{" "}
                                                    {voucher.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}

                            <div>
                                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-2">
                                    Diskon Manual (Rp)
                                </label>
                                <div className="relative">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                                        Rp
                                    </span>
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        value={discountInput}
                                        onChange={(e) =>
                                            setDiscountInput(
                                                e.target.value.replace(
                                                    /[^\d]/g,
                                                    ""
                                                )
                                            )
                                        }
                                        placeholder="0"
                                        className="w-full h-10 pl-10 pr-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                                    />
                                </div>
                            </div>

                            {/* Shipping Cost Input */}
                            <div>
                                <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-2">
                                    Ongkos Kirim (Rp)
                                </label>
                                <div className="relative">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                                        Rp
                                    </span>
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        value={shippingInput}
                                        onChange={(e) =>
                                            setShippingInput(
                                                e.target.value.replace(
                                                    /[^\d]/g,
                                                    ""
                                                )
                                            )
                                        }
                                        placeholder="0"
                                        className="w-full h-10 pl-10 pr-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                                    />
                                </div>
                                {/* Quick Shipping Amounts */}
                                <div className="grid grid-cols-4 gap-2 mt-2">
                                    {[10000, 15000, 20000, 25000].map((amt) => (
                                        <button
                                            key={amt}
                                            type="button"
                                            onClick={() =>
                                                setShippingInput(String(amt))
                                            }
                                            className={`py-1.5 px-1 rounded-lg text-xs font-medium transition-all ${
                                                Number(shippingInput) === amt
                                                    ? "bg-primary-500 text-white"
                                                    : "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200"
                                            }`}
                                        >
                                            {formatPrice(amt)}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Cash Input - Only for cash */}
                            {paymentMethod === "cash" && !payLater && (
                                <div>
                                    <label className="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-2">
                                        Jumlah Bayar (Rp)
                                    </label>
                                    <div className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">
                                            Rp
                                        </span>
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            value={cashInput}
                                            onChange={(e) =>
                                                setCashInput(
                                                    e.target.value.replace(
                                                        /[^\d]/g,
                                                        ""
                                                    )
                                                )
                                            }
                                            placeholder="0"
                                            className="w-full h-10 pl-10 pr-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-base font-semibold focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Summary & Submit - Fixed at bottom */}
                    <div className="flex-shrink-0 border-t border-hairline-light dark:border-hairline-dark bg-canvas-cream dark:bg-canvas-night p-3">
                        {/* Summary Row */}
                        <div className="flex justify-between items-center mb-2 text-sm">
                            <span className="text-slate-500">Subtotal Dasar</span>
                            <span className="font-medium">
                                {formatPrice(baseSubtotal)}
                            </span>
                        </div>
                        {promoDiscount > 0 && (
                            <div className="flex justify-between items-center mb-2 text-sm">
                                <span className="text-slate-500">
                                    Promo Otomatis
                                </span>
                                <span className="text-emerald-600">
                                    -{formatPrice(promoDiscount)}
                                </span>
                            </div>
                        )}
                        {(pricingPreview?.applied_groups || []).length > 0 && (
                            <div className="mb-3 rounded-card border border-hairline-light bg-white/80 p-2 dark:border-hairline-dark dark:bg-canvas-night-elevated">
                                <div className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                    Grup Promo Aktif
                                </div>
                                <div className="space-y-1.5">
                                    {(pricingPreview?.applied_groups || []).map(
                                        (group) => (
                                            <div
                                                key={group.key}
                                                className="flex items-center justify-between text-xs"
                                            >
                                                <span className="truncate pr-3 text-slate-600 dark:text-slate-300">
                                                    {group.label}
                                                </span>
                                                <span className="font-medium text-emerald-600">
                                                    -{formatPrice(group.discount_total)}
                                                </span>
                                            </div>
                                        )
                                    )}
                                </div>
                            </div>
                        )}
                        {voucherDiscount > 0 && (
                            <div className="flex justify-between items-center mb-2 text-sm">
                                <span className="text-slate-500">Voucher</span>
                                <span className="text-ink">
                                    -{formatPrice(voucherDiscount)}
                                </span>
                            </div>
                        )}
                        {loyaltyDiscount > 0 && (
                            <div className="flex justify-between items-center mb-2 text-sm">
                                <span className="text-slate-500">
                                    Redeem Poin
                                </span>
                                <span className="text-ink">
                                    -{formatPrice(loyaltyDiscount)}
                                </span>
                            </div>
                        )}
                        {discount > 0 && (
                            <div className="flex justify-between items-center mb-2 text-sm">
                                <span className="text-slate-500">Diskon Manual</span>
                                <span className="text-danger-500">
                                    -{formatPrice(discount)}
                                </span>
                            </div>
                        )}
                        {shipping > 0 && (
                            <div className="flex justify-between items-center mb-2 text-sm">
                                <span className="text-slate-500">Ongkir</span>
                                <span className="font-medium">
                                    +{formatPrice(shipping)}
                                </span>
                            </div>
                        )}
                        <div className="flex justify-between items-center mb-3">
                            <span className="font-semibold text-ink dark:text-white">
                                Total
                            </span>
                            <span className="text-xl font-bold text-ink dark:text-primary-400">
                                {formatPrice(payable)}
                            </span>
                        </div>

                        {paymentMethod === "cash" &&
                            !payLater &&
                            cash >= payable &&
                            payable > 0 && (
                                <div className="flex justify-between items-center mb-3 p-2 rounded-lg bg-success-50 dark:bg-success-950/30">
                                    <span className="text-sm text-success-700 dark:text-success-400">
                                        Kembalian
                                    </span>
                                    <span className="font-bold text-success-600">
                                        {formatPrice(cash - payable)}
                                    </span>
                                </div>
                            )}

                        {/* Submit Button - Always visible */}
                        <button
                            onClick={handleSubmitTransaction}
                            disabled={
                                !carts.length ||
                                (payLater && !selectedCustomer?.id) ||
                                (payLater && !dueDate) ||
                                (isBankTransfer && !selectedBankAccount) ||
                                (!payLater &&
                                    paymentMethod === "cash" &&
                                    cash < payable) ||
                                isLoadingPricing ||
                                isSubmitting
                            }
                            className={`w-full h-12 rounded-full text-sm font-semibold flex items-center justify-center gap-2 transition-all ${
                                carts.length &&
                                (!payLater || (selectedCustomer?.id && dueDate)) &&
                                (!isBankTransfer || selectedBankAccount) &&
                                (paymentMethod !== "cash" || cash >= payable) &&
                                !isLoadingPricing
                                    ? "bg-ink hover:bg-shade-70 text-white"
                                    : "bg-hairline-light dark:bg-slate-800 text-slate-400 cursor-not-allowed"
                            }`}
                        >
                            {isSubmitting || isLoadingPricing ? (
                                <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                            ) : (
                                <>
                                    <IconReceipt size={18} />
                                    <span>
                                        {!carts.length
                                            ? "Keranjang Kosong"
                                            : payLater && !selectedCustomer?.id
                                            ? "Pilih Pelanggan untuk Piutang"
                                            : payLater && !dueDate
                                            ? "Isi Jatuh Tempo"
                                            : isBankTransfer &&
                                              !selectedBankAccount
                                            ? "Pilih Rekening"
                                            : paymentMethod === "cash" &&
                                              cash < payable
                                            ? `Kurang ${formatPrice(
                                                  payable - cash
                                              )}`
                                            : isLoadingPricing
                                            ? "Menghitung Promo..."
                                            : "Selesaikan Transaksi"}
                                    </span>
                                </>
                            )}
                        </button>
                    </div>
                </div>
            </div>

            {/* Numpad Modal */}
            <NumpadModal
                isOpen={numpadOpen}
                onClose={() => setNumpadOpen(false)}
                onConfirm={handleNumpadConfirm}
                title="Jumlah Bayar"
                initialValue={Number(cashInput) || 0}
                isCurrency={true}
            />

            {/* Keyboard Shortcuts Help */}
            {showShortcuts && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div
                        className="absolute inset-0 bg-slate-900/60"
                        onClick={() => setShowShortcuts(false)}
                    />
                    <div className="relative bg-white dark:bg-slate-900 rounded-2xl shadow-xl p-6 max-w-sm w-full">
                        <h3 className="text-lg font-bold text-slate-800 dark:text-white mb-4 flex items-center gap-2">
                            <IconKeyboard size={24} />
                            Keyboard Shortcuts
                        </h3>
                        <div className="space-y-3">
                            {[
                                ["F1", "Buka Numpad"],
                                ["F2", "Selesaikan Transaksi"],
                                ["F3", "Toggle Produk/Keranjang"],
                                ["F4", "Tampilkan Bantuan"],
                                ["Esc", "Tutup Modal"],
                            ].map(([key, desc]) => (
                                <div
                                    key={key}
                                    className="flex items-center justify-between"
                                >
                                    <span className="text-slate-600 dark:text-slate-400">
                                        {desc}
                                    </span>
                                    <kbd className="px-2 py-1 bg-slate-100 dark:bg-slate-800 rounded text-sm font-mono font-bold text-slate-700 dark:text-slate-300">
                                        {key}
                                    </kbd>
                                </div>
                            ))}
                        </div>
                        <button
                            onClick={() => setShowShortcuts(false)}
                            className="mt-6 w-full py-2.5 bg-primary-500 hover:bg-primary-600 text-white rounded-xl font-medium"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}

Index.layout = (page) => <POSLayout children={page} />;

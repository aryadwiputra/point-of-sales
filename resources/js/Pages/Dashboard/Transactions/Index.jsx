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
import { queueTransaction } from "@/Utils/offlineDb";
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

export default function Index({
    carts = [],
    carts_total = 0,
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
        flash,
        lowStockNotifications = [],
        activeCashierShift,
    } = usePage().props;
    const { can } = useAuthorization();
    const canOpenShift = can("cashier-shifts-open");

    // State
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedCategory, setSelectedCategory] = useState(null);
    const [isSearching, setIsSearching] = useState(false);
    const [addingProductId, setAddingProductId] = useState(null);
    const [removingItemId, setRemovingItemId] = useState(null);
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
    const normalizedSelectedCategory =
        selectedCategory === null ? null : Number(selectedCategory);
    const pricingItemsByCartId = useMemo(() => {
        const items = pricingPreview?.items || [];

        return items.reduce((accumulator, item) => {
            accumulator[item.cart_id] = item;

            return accumulator;
        }, {});
    }, [pricingPreview]);

    // Ref for search input to enable keyboard focus
    const searchInputRef = useRef(null);

    // Set default payment method
    useEffect(() => {
        setPaymentMethod(defaultPaymentGateway ?? "cash");
    }, [defaultPaymentGateway]);

    useEffect(() => {
        setPricingPreview(initialPricingPreview);
    }, [initialPricingPreview]);

    // Show flash messages
    useEffect(() => {
        if (flash?.error) toast.error(flash.error);
        if (flash?.success) toast.success(flash.success);
    }, [flash]);

    // Barcode scanner integration
    const handleBarcodeScan = useCallback(
        (barcode) => {
            const product = products.find(
                (p) => p.barcode?.toLowerCase() === barcode.toLowerCase()
            );

            if (product) {
                if (product.stock > 0) {
                    handleAddToCart(product);
                    toast.success(`${product.title} ditambahkan (barcode)`);
                } else {
                    toast.error(`${product.title} stok habis`);
                }
            } else {
                toast.error(`Produk tidak ditemukan: ${barcode}`);
            }
        },
        [products]
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
        () => Number(pricingPreview?.summary?.base_subtotal ?? carts_total ?? 0),
        [pricingPreview, carts_total]
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
    const taxTotal = useMemo(
        () => Number(pricingPreview?.summary?.tax_total ?? 0),
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
    const cash = useMemo(
        () => (isCashPayment ? Math.max(0, Number(cashInput) || 0) : payable),
        [cashInput, isCashPayment, payable]
    );
    const cartCount = useMemo(
        () => carts.reduce((total, item) => total + Number(item.qty), 0),
        [carts]
    );
    const pricingDependency = useMemo(
        () => carts.map((item) => `${item.id}:${item.qty}`).join("|"),
        [carts]
    );

    useEffect(() => {
        if (carts.length === 0) {
            setPricingPreview({
                items: [],
                summary: {
                    base_subtotal: 0,
                    promo_discount_total: 0,
                    subtotal_after_promo: 0,
                    voucher_discount_total: 0,
                    loyalty_discount_total: 0,
                    manual_discount_total: 0,
                    shipping_cost: 0,
                    tax_total: 0,
                    grand_total: 0,
                },
            });

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
        pricingDependency,
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

    // Handle add product to cart
    const handleAddToCart = async (product) => {
        if (!product?.id) return;

        setAddingProductId(product.id);

        router.post(
            route("transactions.addToCart"),
            {
                product_id: product.id,
                sell_price: product.sell_price,
                qty: 1,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`${product.title} ditambahkan`);
                    setAddingProductId(null);
                },
                onError: () => {
                    toast.error("Gagal menambahkan produk");
                    setAddingProductId(null);
                },
            }
        );
    };

    // Handle update cart quantity
    const [updatingCartId, setUpdatingCartId] = useState(null);

    const handleUpdateQty = (cartId, newQty) => {
        if (newQty < 1) return;
        setUpdatingCartId(cartId);

        router.patch(
            route("transactions.updateCart", cartId),
            { qty: newQty },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setUpdatingCartId(null);
                },
                onError: (errors) => {
                    toast.error(errors?.message || "Gagal update quantity");
                    setUpdatingCartId(null);
                },
            }
        );
    };

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
                    if (carts.length > 0 && selectedCustomer)
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
    const handleRemoveFromCart = (cartId) => {
        setRemovingItemId(cartId);

        router.delete(route("transactions.destroyCart", cartId), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Item dihapus dari keranjang");
                setRemovingItemId(null);
            },
            onError: () => {
                toast.error("Gagal menghapus item");
                setRemovingItemId(null);
            },
        });
    };

    // Handle submit transaction
    const handleSubmitTransaction = () => {
        if (carts.length === 0) {
            toast.error("Keranjang masih kosong");
            return;
        }

        if (!selectedCustomer?.id) {
            toast.error("Pilih pelanggan terlebih dahulu");
            return;
        }

        if (payLater && !dueDate) {
            toast.error("Isi tanggal jatuh tempo untuk nota barang");
            return;
        }

        if (!payLater && isCashPayment && cash < payable) {
            toast.error("Jumlah pembayaran kurang dari total");
            return;
        }

        // Validate bank transfer requires bank selection
        const isBankTransfer = paymentMethod === "bank_transfer";
        if (isBankTransfer && !selectedBankAccount) {
            toast.error("Pilih rekening bank tujuan");
            return;
        }

        setIsSubmitting(true);

        if (!navigator.onLine) {
            const payload = {
                customer_id: selectedCustomer.id,
                discount,
                redeem_points: Number(redeemPointsInput || 0),
                customer_voucher_id: selectedVoucherId || null,
                shipping_cost: shipping,
                grand_total: payable,
                cash: isCashPayment ? cash : payable,
                payment_gateway: payLater ? null : isCashPayment ? null : paymentMethod,
                pay_later: payLater,
                due_date: payLater ? dueDate : null,
                bank_account_id: isBankTransfer ? selectedBankAccount : null,
            };
            queueTransaction(payload).then(() => {
                setCarts([]);
                setPricingPreview(initialPricingPreview);
                toast.success("Transaksi disimpan offline. Akan dikirim saat online.");
            });
            setIsSubmitting(false);
            return;
        }

        router.post(
            route("transactions.store"),
            {
                customer_id: selectedCustomer.id,
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

    // Filter products including out of stock
    const allProducts = useMemo(() => {
        return products.filter((product) => {
            const matchesCategory =
                normalizedSelectedCategory === null ||
                Number(product.category_id) === normalizedSelectedCategory;
            const matchesSearch =
                !searchQuery ||
                product.title
                    .toLowerCase()
                    .includes(searchQuery.toLowerCase()) ||
                product.barcode
                    ?.toLowerCase()
                    .includes(searchQuery.toLowerCase());
            return matchesCategory && matchesSearch;
        });
    }, [products, normalizedSelectedCategory, searchQuery]);

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
                <div className="lg:hidden flex border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                    <button
                        onClick={() => setMobileView("products")}
                        className={`flex-1 flex items-center justify-center gap-2 py-3 text-sm font-medium transition-colors ${
                            mobileView === "products"
                                ? "text-primary-600 border-b-2 border-primary-500"
                                : "text-slate-500"
                        }`}
                    >
                        <IconShoppingCart size={18} />
                        <span>Produk</span>
                    </button>
                    <button
                        onClick={() => setMobileView("cart")}
                        className={`flex-1 flex items-center justify-center gap-2 py-3 text-sm font-medium transition-colors relative ${
                            mobileView === "cart"
                                ? "text-primary-600 border-b-2 border-primary-500"
                                : "text-slate-500"
                        }`}
                    >
                        <IconReceipt size={18} />
                        <span className="relative inline-flex items-center gap-1">
                            Keranjang
                            {cartCount > 0 && (
                                <span className="inline-flex items-center justify-center px-1.5 min-w-[20px] h-5 text-[11px] font-bold bg-primary-500 text-white rounded-full">
                                    {cartCount}
                                </span>
                            )}
                        </span>
                    </button>
                </div>

                {/* Left Panel - Products */}
                <div
                    className={`flex-1 bg-slate-100 dark:bg-slate-950 overflow-hidden ${
                        mobileView !== "products"
                            ? "hidden lg:flex lg:flex-col"
                            : "flex flex-col"
                    }`}
                >
                    <ProductGrid
                        products={allProducts}
                        categories={categories}
                        selectedCategory={selectedCategory}
                        onCategoryChange={(categoryId) =>
                            setSelectedCategory(
                                categoryId === null ? null : Number(categoryId)
                            )
                        }
                        searchQuery={searchQuery}
                        onSearchChange={setSearchQuery}
                        isSearching={isSearching}
                        onAddToCart={handleAddToCart}
                        addingProductId={addingProductId}
                        searchInputRef={searchInputRef}
                    />
                </div>

                {/* Right Panel - Cart & Payment */}
                <div
                    className={`w-full lg:w-[420px] xl:w-[480px] flex flex-col bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 min-h-0 overflow-hidden ${
                        mobileView !== "cart" ? "hidden lg:flex" : "flex"
                    }`}
                    style={{ height: "calc(100vh - 4rem)" }}
                >
                    {/* Customer Select - Fixed */}
                    <div className="p-3 border-b border-slate-200 dark:border-slate-800 flex-shrink-0">
                        <CustomerSelect
                            customers={customers}
                            selected={selectedCustomer}
                            onSelect={setSelectedCustomer}
                            placeholder="Pilih pelanggan..."
                            error={errors?.customer_id}
                            label="Pelanggan"
                            tierOptions={loyaltyTierOptions}
                        />
                    </div>

                    {/* Held Transactions & Alerts */}
                    {heldCarts.length > 0 && (
                        <div className="p-3 border-b border-slate-200 dark:border-slate-800">
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
                            <div className="p-3 border-b border-slate-200 dark:border-slate-800">
                                <HoldButton
                                    hasItems={carts.length > 0}
                                    onHold={handleHoldCart}
                                    isHolding={isHolding}
                                />
                            </div>
                        )}

                        <div className="p-3 border-b border-slate-200 dark:border-slate-800">
                            <div className="flex items-center justify-between mb-3">
                                <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                    <IconShoppingCart size={16} />
                                    Keranjang
                                </h3>
                                {carts.length > 0 && (
                                    <span className="px-2.5 py-0.5 text-xs font-bold bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300 rounded-full whitespace-nowrap">
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

                                            return (
                                        <div
                                            key={item.id}
                                            className="flex items-center gap-2 p-2 rounded-lg bg-slate-50 dark:bg-slate-800/50 group"
                                        >
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
                                                                × {item.qty}
                                                            </p>
                                                        )}
                                                    <p>
                                                        {formatPrice(
                                                            effectiveUnitPrice
                                                        )}{" "}
                                                        × {item.qty}
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
                                                                item.qty - 1
                                                            )
                                                        )
                                                    }
                                                    disabled={item.qty <= 1}
                                                    className="w-6 h-6 rounded flex items-center justify-center bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300 disabled:opacity-50 text-xs"
                                                >
                                                    -
                                                </button>
                                                <span className="w-6 text-center text-xs font-medium">
                                                    {item.qty}
                                                </span>
                                                <button
                                                    onClick={() =>
                                                        handleUpdateQty(
                                                            item.id,
                                                            item.qty + 1
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
                            <div className="flex items-center justify-between p-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800">
                                <div>
                                    <p className="text-sm font-semibold text-slate-800 dark:text-white">
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
                                        className={`w-11 h-6 flex items-center bg-slate-300 rounded-full p-1 transition ${
                                            payLater ? "bg-primary-500" : ""
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
                                        className="w-full h-11 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500"
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
                                            className={`p-3 rounded-xl border-2 transition-all flex items-center gap-2 ${
                                                paymentMethod === method.value && !payLater
                                                    ? "border-primary-500 bg-primary-50 dark:bg-primary-950/30"
                                                    : "border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600"
                                            } ${payLater ? "opacity-50 cursor-not-allowed" : ""}`}
                                        >
                                            <div
                                                className={`w-8 h-8 rounded-lg flex items-center justify-center ${
                                                    paymentMethod ===
                                                        method.value &&
                                                    !payLater
                                                        ? "bg-primary-500 text-white"
                                                        : "bg-slate-100 dark:bg-slate-800 text-slate-500"
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
                                                            ? "text-primary-700 dark:text-primary-300"
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
                                                        className={`p-3 rounded-xl border-2 transition-colors flex items-center gap-3 text-left ${
                                                            isActive
                                                                ? "border-primary-500 bg-primary-50 dark:bg-primary-950/30"
                                                                : "border-slate-200 dark:border-slate-700 hover:border-primary-200 dark:hover:border-primary-800"
                                                        }`}
                                                    >
                                                        <div className="w-10 h-10 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-center overflow-hidden">
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
                            {paymentMethod === "cash" && (
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
                            {paymentMethod === "cash" && (
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
                    <div className="flex-shrink-0 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/80 p-3">
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
                            <div className="mb-3 rounded-xl border border-slate-200 bg-white/70 p-2 dark:border-slate-700 dark:bg-slate-900/60">
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
                                <span className="text-primary-600">
                                    -{formatPrice(voucherDiscount)}
                                </span>
                            </div>
                        )}
                        {loyaltyDiscount > 0 && (
                            <div className="flex justify-between items-center mb-2 text-sm">
                                <span className="text-slate-500">
                                    Redeem Poin
                                </span>
                                <span className="text-primary-600">
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
                        {taxTotal > 0 && (
                            <div className="flex justify-between items-center mb-2 text-sm">
                                <span className="text-slate-500">PPN</span>
                                <span className="font-medium">
                                    +{formatPrice(taxTotal)}
                                </span>
                            </div>
                        )}
                        <div className="flex justify-between items-center mb-3">
                            <span className="font-semibold text-slate-800 dark:text-white">
                                Total
                            </span>
                            <span className="text-xl font-bold text-primary-600 dark:text-primary-400">
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
                                !selectedCustomer ||
                                (!payLater &&
                                    paymentMethod === "cash" &&
                                    cash < payable) ||
                                isLoadingPricing ||
                                isSubmitting
                            }
                            className={`w-full h-12 rounded-xl text-sm font-semibold flex items-center justify-center gap-2 transition-all ${
                                carts.length &&
                                selectedCustomer &&
                                (paymentMethod !== "cash" || cash >= payable)
                                    && !isLoadingPricing
                                    ? "bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white shadow-lg shadow-primary-500/30"
                                    : "bg-slate-200 dark:bg-slate-800 text-slate-400 cursor-not-allowed"
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
                                            : !selectedCustomer
                                            ? "Pilih Pelanggan"
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

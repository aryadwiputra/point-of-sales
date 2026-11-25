import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import { IconShoppingCart, IconMinus, IconPlus } from "@tabler/icons-react";
import toast from "react-hot-toast";
import ModifierModal from "@/Components/POS/ModifierModal";

const fmt = (v) => Number(v || 0).toLocaleString("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 });

export default function DineMenu({ table, categories, products, selfOrderEnabled, storeName }) {
    const [cart, setCart] = useState([]);
    const [activeCategory, setActiveCategory] = useState("all");
    const [notes, setNotes] = useState("");
    const [submitting, setSubmitting] = useState(false);
    const [modalProduct, setModalProduct] = useState(null);

    const optionsFor = (product) =>
        (product?.modifier_groups || []).flatMap(
            (group) => group.modifiers ?? group.options ?? []
        );

    const lineKey = (productId, modifierIds) =>
        `${productId}:${[...(modifierIds || [])].sort((a, b) => a - b).join(",")}`;

    const priceForLine = (item) => {
        const product = products.find((candidate) => candidate.id === item.product_id);
        if (!product) return 0;

        const delta = (item.modifier_ids || []).reduce((total, id) => {
            const option = optionsFor(product).find((candidate) => candidate.id === id);
            return total + Number(option?.price_delta || 0);
        }, 0);

        return (Number(product.sell_price) + delta) * Number(item.qty);
    };

    const addLine = (productId, modifierIds = [], note = "") => {
        setCart((current) => {
            const key = lineKey(productId, modifierIds);
            const index = current.findIndex(
                (item) =>
                    lineKey(item.product_id, item.modifier_ids) === key &&
                    (item.note || "") === note
            );

            if (index >= 0) {
                const next = [...current];
                next[index] = { ...next[index], qty: next[index].qty + 1 };
                return next;
            }

            return [...current, { product_id: productId, qty: 1, note, modifier_ids: modifierIds }];
        });
    };

    const addItem = (product) => {
        if (product.has_modifiers) {
            setModalProduct(product);
            return;
        }

        addLine(product.id, [], "");
    };

    const removeItem = (line) => {
        setCart((current) => {
            const key = lineKey(line.product_id, line.modifier_ids);
            const index = current.findIndex(
                (item) =>
                    lineKey(item.product_id, item.modifier_ids) === key &&
                    (item.note || "") === (line.note || "")
            );

            if (index < 0) return current;

            const next = [...current];

            if (next[index].qty > 1) {
                next[index] = { ...next[index], qty: next[index].qty - 1 };
            } else {
                next.splice(index, 1);
            }

            return next;
        });
    };

    const cartTotal = () => cart.reduce((total, item) => total + priceForLine(item), 0);

    const cartCount = () => cart.reduce((s, i) => s + i.qty, 0);

    const getQty = (productId) =>
        cart
            .filter((item) => item.product_id === productId)
            .reduce((total, item) => total + Number(item.qty), 0);

    const handleSubmit = (paymentOption) => {
        if (!selfOrderEnabled) {
            toast.error("Fitur pemesanan sementara nonaktif.");
            return;
        }
        if (cart.length === 0) {
            toast.error("Pilih minimal satu item.");
            return;
        }
        setSubmitting(true);
        router.post(
            route("dine-order.store", table.token),
            { items: cart, notes, payment_option: paymentOption },
            {
                onSuccess: () => setSubmitting(false),
                onError: () => {
                    toast.error("Gagal mengirim pesanan.");
                    setSubmitting(false);
                },
            }
        );
    };

    const filtered = activeCategory === "all" ? products : products.filter((p) => p.category_id === Number(activeCategory));

    return (
        <>
            <Head title={`Menu ${table.name} — ${storeName}`} />
            <div className="min-h-screen bg-slate-50 flex flex-col">
                <header className="bg-white border-b border-slate-200 sticky top-0 z-20">
                    <div className="max-w-lg mx-auto px-4 py-3 flex items-center justify-between">
                        <div>
                            <h1 className="font-bold text-slate-900 text-lg">{storeName}</h1>
                            <p className="text-xs text-slate-500">
                                Meja {table.name}
                                {table.area_name ? ` · ${table.area_name}` : ""}
                            </p>
                        </div>
                        <button onClick={() => window.history.back()} className="text-sm text-slate-500 hover:text-slate-700">
                            Kembali
                        </button>
                    </div>
                </header>

                <main className="flex-1 max-w-lg mx-auto w-full px-4 py-4 pb-28">
                    <div className="flex gap-2 overflow-x-auto pb-3 -mx-4 px-4 scrollbar-hide">
                        <button
                            onClick={() => setActiveCategory("all")}
                            className={`flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium transition-colors ${
                                activeCategory === "all"
                                    ? "bg-primary-500 text-white"
                                    : "bg-slate-100 text-slate-600 hover:bg-slate-200"
                            }`}
                        >
                            Semua
                        </button>
                        {categories.map((cat) => (
                            <button
                                key={cat.id}
                                onClick={() => setActiveCategory(cat.id)}
                                className={`flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium transition-colors ${
                                    activeCategory === cat.id
                                        ? "bg-primary-500 text-white"
                                        : "bg-slate-100 text-slate-600 hover:bg-slate-200"
                                }`}
                            >
                                {cat.name}
                            </button>
                        ))}
                    </div>

                    <div className="grid grid-cols-2 gap-3 mt-4">
                        {filtered.map((product) => {
                            const qty = getQty(product.id);
                            return (
                                <div key={product.id} className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                                    {product.image ? (
                                        <img src={product.image} alt={product.title} className="w-full aspect-square object-cover" loading="lazy" />
                                    ) : (
                                        <div className="w-full aspect-square bg-slate-100 flex items-center justify-center">
                                            <span className="text-slate-300 text-4xl font-bold">{product.title?.[0] ?? "?"}</span>
                                        </div>
                                    )}
                                    <div className="p-3">
                                        <h3 className="font-medium text-slate-800 text-sm line-clamp-2">{product.title}</h3>
                                        <p className="text-primary-600 font-semibold text-sm mt-1">{fmt(product.sell_price)}</p>
                                        <div className="mt-2 flex items-center justify-between">
                                            {qty === 0 ? (
                                                <button
                                                    onClick={() => addItem(product)}
                                                    className="w-full py-1.5 bg-primary-500 hover:bg-primary-600 text-white text-xs font-medium rounded-lg transition-colors"
                                                >
                                                    + Tambah
                                                </button>
                                            ) : (
                                                <div className="flex items-center gap-2 w-full">
                                                    <button
                                                        onClick={() => removeItem(cart.find((item) => item.product_id === product.id))}
                                                        className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors"
                                                    >
                                                        <IconMinus size={14} />
                                                    </button>
                                                    <span className="flex-1 text-center font-semibold text-sm">{qty}</span>
                                                    <button
                                                        onClick={() => addItem(product)}
                                                        className="p-1.5 rounded-lg bg-primary-500 hover:bg-primary-600 text-white transition-colors"
                                                    >
                                                        <IconPlus size={14} />
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </main>

                {cart.length > 0 && (
                    <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 shadow-lg z-30">
                        <div className="max-w-lg mx-auto px-4 py-3">
                            <button
                                onClick={() => document.getElementById("cart-modal")?.showModal()}
                                className="w-full flex items-center justify-between bg-primary-500 hover:bg-primary-600 text-white rounded-xl px-4 py-3 transition-colors"
                            >
                                <div className="flex items-center gap-3">
                                    <IconShoppingCart size={20} />
                                    <span className="font-medium">{cartCount()} item</span>
                                </div>
                                <span className="font-bold">{fmt(cartTotal())}</span>
                            </button>
                        </div>
                    </div>
                )}

                <dialog id="cart-modal" className="modal modal-bottom sm:modal-middle">
                    <div className="modal-box max-w-lg mx-auto">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-bold text-lg">Pesanan Anda</h3>
                            <form method="dialog">
                                <button className="btn btn-sm btn-circle btn-ghost">✕</button>
                            </form>
                        </div>

                        <div className="space-y-3 max-h-64 overflow-y-auto">
                            {cart.map((item) => {
                                return (
                                    <div key={`${item.product_id}-${(item.modifier_ids || []).join("-")}-${item.note || ""}`} className="flex items-start justify-between gap-3 py-2 border-b border-slate-100 last:border-0">
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium text-slate-900">
                                                {products.find((p) => p.id === item.product_id)?.title}
                                            </p>
                                            {(item.modifier_ids || []).length > 0 && (
                                                <p className="text-xs text-slate-500">
                                                    {optionsFor(products.find((p) => p.id === item.product_id))
                                                        .filter((option) => item.modifier_ids.includes(option.id))
                                                        .map((option) => option.name)
                                                        .join(", ")}
                                                </p>
                                            )}
                                            {item.note && (
                                                <p className="text-xs italic text-slate-500">
                                                    Catatan: {item.note}
                                                </p>
                                            )}
                                            <p className="text-xs text-slate-500">
                                                Rp{new Intl.NumberFormat("id-ID").format(priceForLine(item) / item.qty)} × {item.qty}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => removeItem(item)}
                                                className="w-7 h-7 rounded-lg border border-slate-200 text-slate-600"
                                            >
                                                −
                                            </button>
                                            <span className="text-sm font-medium w-5 text-center">
                                                {item.qty}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => addLine(item.product_id, item.modifier_ids || [], item.note || "")}
                                                className="w-7 h-7 rounded-lg border border-slate-200 text-slate-600"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="mt-4 pt-3 border-t border-slate-200">
                            <textarea
                                className="textarea textarea-bordered w-full text-sm"
                                placeholder="Catatan pesanan (opsional)"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                rows={2}
                            />
                        </div>

                        <div className="mt-4 flex flex-col gap-2">
                            <div className="flex justify-between font-semibold">
                                <span>Total</span>
                                <span>{fmt(cartTotal())}</span>
                            </div>
                            <button
                                onClick={() => {
                                    document.getElementById("cart-modal")?.close();
                                    handleSubmit("pay_at_counter");
                                }}
                                disabled={submitting}
                                className="btn bg-primary-500 hover:bg-primary-600 text-white"
                            >
                                {submitting ? "Mengirim..." : "Pesan — Bayar di Kasir"}
                            </button>
                        </div>
                    </div>
                    <form method="dialog" className="modal-backdrop">
                        <button>close</button>
                    </form>
                </dialog>

                <ModifierModal
                    show={Boolean(modalProduct)}
                    product={modalProduct}
                    unit={null}
                    onClose={() => setModalProduct(null)}
                    onConfirm={({ product, modifierIds, note }) => {
                        addLine(product.id, modifierIds, note || "");
                        setModalProduct(null);
                    }}
                />
            </div>
        </>
    );
}

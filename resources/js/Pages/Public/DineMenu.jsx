import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import { IconShoppingCart, IconMinus, IconPlus } from "@tabler/icons-react";
import toast from "react-hot-toast";

const fmt = (v) => Number(v || 0).toLocaleString("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 });

export default function DineMenu({ table, categories, products, selfOrderEnabled, storeName }) {
    const [cart, setCart] = useState([]);
    const [activeCategory, setActiveCategory] = useState("all");
    const [notes, setNotes] = useState("");
    const [submitting, setSubmitting] = useState(false);

    const addItem = (product) => {
        setCart((prev) => {
            const idx = prev.findIndex((c) => c.product_id === product.id);
            if (idx >= 0) {
                const updated = [...prev];
                updated[idx] = { ...updated[idx], qty: updated[idx].qty + 1 };
                return updated;
            }
            return [...prev, { product_id: product.id, qty: 1, note: "" }];
        });
    };

    const removeItem = (productId) => {
        setCart((prev) => {
            const idx = prev.findIndex((c) => c.product_id === productId);
            if (idx < 0) return prev;
            if (prev[idx].qty <= 1) {
                return prev.filter((c) => c.product_id !== productId);
            }
            const updated = [...prev];
            updated[idx] = { ...updated[idx], qty: updated[idx].qty - 1 };
            return updated;
        });
    };

    const cartTotal = () =>
        cart.reduce((sum, item) => {
            const p = products.find((pr) => pr.id === item.product_id);
            return sum + (p?.sell_price ?? 0) * item.qty;
        }, 0);

    const cartCount = () => cart.reduce((s, i) => s + i.qty, 0);

    const getQty = (productId) => {
        const item = cart.find((c) => c.product_id === productId);
        return item?.qty ?? 0;
    };

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
                                                        onClick={() => removeItem(product.id)}
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
                                const p = products.find((pr) => pr.id === item.product_id);
                                return (
                                    <div key={item.product_id} className="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                                        <div>
                                            <p className="font-medium text-sm">{p?.title}</p>
                                            <p className="text-xs text-slate-500">{fmt(p?.sell_price ?? 0)}</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <button onClick={() => removeItem(item.product_id)} className="btn btn-xs btn-ghost">−</button>
                                            <span className="text-sm font-medium w-6 text-center">{item.qty}</span>
                                            <button onClick={() => addItem({ id: item.product_id })} className="btn btn-xs btn-ghost">+</button>
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
            </div>
        </>
    );
}

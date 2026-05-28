import React from "react";
import { usePage } from "@inertiajs/react";
import {
    IconTrash,
    IconMinus,
    IconPlus,
    IconShoppingCart,
} from "@tabler/icons-react";
import { getProductImageUrl } from "@/Utils/imageUrl";

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

// Single Cart Item
function CartItem({ item, onUpdateQty, onRemove, isRemoving, isCompactMode }) {
    // Note: item.price from backend is already the total (sell_price * qty)
    const quantity = Number(item.qty || 0);
    const itemPrice = Number(item.price || 0);
    const unitPrice =
        Number(item.product_unit?.sell_price || item.product?.sell_price || 0) ||
        itemPrice / quantity ||
        0;
    const unitLabel = item.unit_label || item.product_unit?.label || "unit";
    const subtotal = itemPrice; // Already calculated total from backend

    return (
        <div
            className={`
            group flex gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50
            border border-transparent hover:border-slate-200 dark:hover:border-slate-700
            transition-all duration-200 animate-slide-up
            ${isRemoving ? "opacity-50 scale-95" : ""}
        `}
        >
            {!isCompactMode && (
                <div className="w-14 h-14 rounded-lg bg-slate-200 dark:bg-slate-700 overflow-hidden flex-shrink-0">
                    {item.product?.image ? (
                        <img
                            src={getProductImageUrl(item.product.image)}
                            alt={item.product.title}
                            className="w-full h-full object-cover"
                        />
                    ) : (
                        <div className="w-full h-full flex items-center justify-center">
                            <IconShoppingCart
                                size={20}
                                className="text-slate-400"
                            />
                        </div>
                    )}
                </div>
            )}

            {/* Product Info */}
            <div className="flex-1 min-w-0">
                <h4 className="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">
                    {item.product?.title || "Produk"}
                </h4>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {formatPrice(unitPrice)} x {formatQty(quantity)} {unitLabel}
                </p>
                <p className="text-sm font-semibold text-primary-600 dark:text-primary-400 mt-1">
                    {formatPrice(subtotal)}
                </p>
            </div>

            {/* Quantity Controls */}
            <div className="flex flex-col items-end justify-between">
                {/* Remove Button */}
                <button
                    onClick={() => onRemove(item.id)}
                    disabled={isRemoving}
                    className="p-1.5 rounded-lg text-slate-400 hover:text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-950/50 transition-colors opacity-0 group-hover:opacity-100"
                >
                    <IconTrash size={16} />
                </button>

                {/* Qty Stepper */}
                <div className="flex items-center gap-1">
                    <button
                        onClick={() =>
                            onUpdateQty(item.id, Math.max(1, quantity - 1))
                        }
                        disabled={quantity <= 1}
                        className="w-7 h-7 rounded-lg flex items-center justify-center bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <IconMinus size={14} />
                    </button>
                    <span className="w-8 text-center text-sm font-medium text-slate-700 dark:text-slate-300">
                        {formatQty(quantity)}
                    </span>
                    <button
                        onClick={() => onUpdateQty(item.id, quantity + 1)}
                        className="w-7 h-7 rounded-lg flex items-center justify-center bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-600 transition-colors"
                    >
                        <IconPlus size={14} />
                    </button>
                </div>
            </div>
        </div>
    );
}

// Empty Cart State
function EmptyCart() {
    return (
        <div className="flex-1 flex flex-col items-center justify-center p-6 text-center">
            <div className="w-20 h-20 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                <IconShoppingCart
                    size={32}
                    className="text-slate-400 dark:text-slate-600"
                />
            </div>
            <h3 className="text-base font-medium text-slate-600 dark:text-slate-400">
                Keranjang Kosong
            </h3>
            <p className="text-sm text-slate-400 dark:text-slate-500 mt-1">
                Klik produk untuk menambahkan
            </p>
        </div>
    );
}

// Main CartPanel Component
export default function CartPanel({
    items = [],
    onUpdateQty,
    onRemove,
    removingItemId,
    className = "",
}) {
    const { appSettings = {} } = usePage().props;
    const isCompactMode =
        appSettings.product_display_mode === "compact_list";
    const totalItems = items.reduce((sum, item) => sum + Number(item.qty || 0), 0);
    // Note: item.price from backend is already sell_price * qty
    const subtotal = items.reduce((sum, item) => sum + Number(item.price || 0), 0);

    return (
        <div className={`flex flex-col h-full ${className}`}>
            {/* Header */}
            <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-800">
                <div className="flex items-center gap-2">
                    <IconShoppingCart
                        size={20}
                        className="text-slate-600 dark:text-slate-400"
                    />
                    <h2 className="text-base font-semibold text-slate-800 dark:text-white">
                        Keranjang
                    </h2>
                </div>
                {totalItems > 0 && (
                    <span className="px-2.5 py-0.5 text-xs font-bold bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300 rounded-full">
                        {totalItems} item
                    </span>
                )}
            </div>

            {/* Cart Items */}
            {items.length > 0 ? (
                <div
                    className="flex-1 overflow-y-auto p-3 space-y-2"
                    style={{ maxHeight: "300px", minHeight: "150px" }}
                >
                    {items.map((item) => (
                        <CartItem
                            key={item.id}
                            item={item}
                            onUpdateQty={onUpdateQty}
                            onRemove={onRemove}
                            isRemoving={removingItemId === item.id}
                            isCompactMode={isCompactMode}
                        />
                    ))}
                </div>
            ) : (
                <EmptyCart />
            )}

            {/* Subtotal */}
            {items.length > 0 && (
                <div className="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50">
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-slate-600 dark:text-slate-400">
                            Subtotal
                        </span>
                        <span className="text-lg font-bold text-slate-900 dark:text-white">
                            {formatPrice(subtotal)}
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}

// Export sub-components
CartPanel.Item = CartItem;
CartPanel.Empty = EmptyCart;

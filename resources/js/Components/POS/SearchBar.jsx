import React, { useState, useEffect, useRef } from "react";
import { IconSearch, IconX, IconBarcode, IconCamera } from "@tabler/icons-react";
import { getProductImageUrl } from "@/Utils/imageUrl";
import BarcodeScanner from "./BarcodeScanner";

const formatPrice = (value = 0) =>
    Number(value || 0).toLocaleString("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    });

export default function SearchBar({
    value = "",
    onChange,
    onSearch,
    onSelect,
    suggestions = [],
    isSearching = false,
    placeholder = "Cari produk atau scan barcode...",
    autoFocus = false,
}) {
    const [isFocused, setIsFocused] = useState(false);
    const [selectedIndex, setSelectedIndex] = useState(-1);
    const [showScanner, setShowScanner] = useState(false);
    const inputRef = useRef(null);
    const listRef = useRef(null);

    const showSuggestions =
        isFocused && suggestions.length > 0 && value.length > 0;

    // Reset selection when suggestions change
    useEffect(() => {
        setSelectedIndex(-1);
    }, [suggestions]);

    // Handle keyboard navigation
    const handleKeyDown = (e) => {
        if (!showSuggestions) {
            if (e.key === "Enter") {
                onSearch?.();
            }
            return;
        }

        switch (e.key) {
            case "ArrowDown":
                e.preventDefault();
                setSelectedIndex((prev) =>
                    prev < suggestions.length - 1 ? prev + 1 : prev
                );
                break;
            case "ArrowUp":
                e.preventDefault();
                setSelectedIndex((prev) => (prev > 0 ? prev - 1 : -1));
                break;
            case "Enter":
                e.preventDefault();
                if (selectedIndex >= 0 && suggestions[selectedIndex]) {
                    onSelect?.(suggestions[selectedIndex]);
                    setIsFocused(false);
                    inputRef.current?.blur();
                } else {
                    onSearch?.();
                }
                break;
            case "Escape":
                setIsFocused(false);
                inputRef.current?.blur();
                break;
        }
    };

    // Scroll selected item into view
    useEffect(() => {
        if (listRef.current && selectedIndex >= 0) {
            const selectedItem = listRef.current.children[selectedIndex];
            if (selectedItem) {
                selectedItem.scrollIntoView({ block: "nearest" });
            }
        }
    }, [selectedIndex]);

    return (
        <div className="relative">
            {/* Search Input */}
            <div className="relative">
                <div className="absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none">
                    {isSearching ? (
                        <div className="w-5 h-5 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
                    ) : (
                        <IconSearch size={20} className="text-slate-400" />
                    )}
                </div>

                <input
                    ref={inputRef}
                    type="text"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    onFocus={() => setIsFocused(true)}
                    onBlur={() => setTimeout(() => setIsFocused(false), 200)}
                    onKeyDown={handleKeyDown}
                    placeholder={placeholder}
                    autoFocus={autoFocus}
                    className="w-full h-14 pl-12 pr-24 rounded-2xl
                        border-2 border-slate-200 dark:border-slate-700
                        bg-white dark:bg-slate-900
                        text-slate-800 dark:text-slate-200 text-lg
                        placeholder-slate-400 dark:placeholder-slate-500
                        focus:ring-4 focus:ring-primary-500/20 focus:border-primary-500 dark:focus:border-primary-500
                        transition-all"
                />

                {/* Right Side Icons */}
                <div className="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-2">
                    {value && (
                        <button
                            type="button"
                            onClick={() => {
                                onChange("");
                                inputRef.current?.focus();
                            }}
                            className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            <IconX size={18} className="text-slate-400" />
                        </button>
                    )}
                    <div className="w-px h-6 bg-slate-200 dark:bg-slate-700" />
                    <button
                        type="button"
                        onClick={() => setShowScanner(true)}
                        className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        title="Scan barcode"
                    >
                        <IconCamera size={20} className="text-slate-500 dark:text-slate-400" />
                    </button>
                </div>
            </div>

            {showScanner && (
                <BarcodeScanner
                    onScan={(barcode) => {
                        onChange(barcode);
                        setShowScanner(false);
                        setTimeout(() => onSearch?.(), 100);
                    }}
                    onClose={() => setShowScanner(false)}
                />
            )}

            {/* Suggestions Dropdown */}
            {showSuggestions && (
                <div
                    className="absolute top-full left-0 right-0 mt-2 py-2 rounded-xl
                        bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700
                        shadow-xl max-h-80 overflow-y-auto z-50 animate-slide-up"
                >
                    <ul ref={listRef}>
                        {suggestions.map((product, index) => (
                            <li key={product.id}>
                                <button
                                    type="button"
                                    onClick={() => {
                                        onSelect?.(product);
                                        setIsFocused(false);
                                    }}
                                    className={`
                                        w-full flex items-center gap-3 px-4 py-3 text-left
                                        transition-colors
                                        ${
                                            index === selectedIndex
                                                ? "bg-primary-50 dark:bg-primary-950/30"
                                                : "hover:bg-slate-50 dark:hover:bg-slate-800"
                                        }
                                    `}
                                >
                                    {/* Product Image */}
                                    <div className="w-12 h-12 rounded-lg bg-slate-100 dark:bg-slate-800 overflow-hidden flex-shrink-0">
                                        {product.image ? (
                                            <img
                                                src={getProductImageUrl(
                                                    product.image
                                                )}
                                                alt={product.title}
                                                className="w-full h-full object-cover"
                                            />
                                        ) : (
                                            <div className="w-full h-full flex items-center justify-center">
                                                <IconBarcode
                                                    size={20}
                                                    className="text-slate-400"
                                                />
                                            </div>
                                        )}
                                    </div>

                                    {/* Product Info */}
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">
                                            {product.title}
                                        </p>
                                        <p className="text-xs text-slate-500 dark:text-slate-400">
                                            {product.barcode} • Stok:{" "}
                                            {product.stock}
                                        </p>
                                    </div>

                                    {/* Price */}
                                    <div className="text-right flex-shrink-0">
                                        <p className="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                            {formatPrice(product.sell_price)}
                                        </p>
                                        {product.stock <= 0 && (
                                            <span className="text-xs text-danger-500 font-medium">
                                                Habis
                                            </span>
                                        )}
                                    </div>
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

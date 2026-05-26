import React, { useState } from "react";
import {
    IconShoppingBag,
    IconPhoto,
} from "@tabler/icons-react";
import { getProductImageUrl } from "@/Utils/imageUrl";

const formatPrice = (value = 0) =>
    Number(value || 0).toLocaleString("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    });

// Single Product Card
function ProductCard({ product, onAddToCart, isAdding }) {
    const hasStock = product.stock > 0;
    const lowStock = product.stock > 0 && product.stock <= 5;
    const promoBadge = product.pricing_badge;
    const units = product.units?.length
        ? product.units
        : [
              {
                  id: null,
                  label: "pcs",
                  conversion_qty: 1,
                  sell_price: product.sell_price,
                  barcode: product.barcode,
                  is_base_unit: true,
              },
          ];
    const baseUnit = units.find((unit) => unit.is_base_unit) || units[0];
    const [selectedUnitId, setSelectedUnitId] = useState(baseUnit?.id ?? "");
    const selectedUnit =
        units.find((unit) => String(unit.id ?? "") === String(selectedUnitId)) ||
        baseUnit;
    const promoPrice = Number(promoBadge?.promo_price || 0);
    const basePrice = Number(
        promoBadge?.base_price || selectedUnit?.sell_price || product.sell_price || 0
    );
    const showPromo = promoBadge && promoPrice > 0 && promoPrice < basePrice;
    const showBadge = Boolean(promoBadge?.label);
    const unitPrice = Number(selectedUnit?.sell_price || product.sell_price || 0);
    const availableQty = Math.floor(
        Number(product.stock || 0) / Math.max(0.001, Number(selectedUnit?.conversion_qty || 1))
    );

    return (
        <div
            className={`
                group relative flex flex-col bg-white dark:bg-canvas-night-elevated
                rounded-card border border-hairline-light dark:border-hairline-dark shadow-paper
                overflow-hidden transition-all duration-200
                ${
                    hasStock
                        ? "hover:border-shade-30 dark:hover:border-primary-700 hover:-translate-y-0.5 active:scale-[0.98] cursor-pointer"
                        : "opacity-60 cursor-not-allowed"
                }
            `}
        >
            {/* Product Image */}
            <div className="relative aspect-square bg-canvas-cream dark:bg-canvas-night overflow-hidden">
                {product.image ? (
                    <img
                        src={getProductImageUrl(product.image)}
                        alt={product.title}
                        className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                        loading="lazy"
                    />
                ) : (
                    <div className="w-full h-full flex items-center justify-center">
                        <IconPhoto
                            size={32}
                            className="text-slate-300 dark:text-slate-600"
                        />
                    </div>
                )}

                {/* Stock Badge */}
                {lowStock && (
                    <span className="absolute top-2 right-2 px-2 py-0.5 text-xs font-medium bg-warning-100 text-warning-700 dark:bg-warning-900/50 dark:text-warning-400 rounded-full">
                        Sisa {product.stock} {baseUnit?.label || ""}
                    </span>
                )}

                {showBadge && (
                    <span className="absolute left-2 top-2 max-w-[70%] truncate rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-semibold text-white shadow-lg">
                        {promoBadge.label}
                    </span>
                )}

                {/* Out of Stock Overlay */}
                {!hasStock && (
                    <div className="absolute inset-0 bg-slate-900/60 flex items-center justify-center">
                        <span className="px-3 py-1 bg-danger-500 text-white text-xs font-semibold rounded-full">
                            Habis
                        </span>
                    </div>
                )}

            </div>

            {/* Product Info */}
            <div className="flex-1 p-3 flex flex-col justify-between min-h-[112px]">
                <h3 className="text-sm font-medium text-ink dark:text-slate-200 line-clamp-2 leading-tight">
                    {product.title}
                </h3>
                <div className="mt-2">
                    {units.length > 1 && (
                        <select
                            value={selectedUnitId}
                            onChange={(event) =>
                                setSelectedUnitId(event.target.value)
                            }
                            className="mb-2 h-8 w-full rounded-md border border-hairline-light bg-white px-2 text-xs text-shade-70 focus:border-ink focus:ring-4 focus:ring-aloe-100/70 dark:border-hairline-dark dark:bg-canvas-night dark:text-slate-200"
                        >
                            {units.map((unit) => (
                                <option key={unit.id ?? unit.label} value={unit.id ?? ""}>
                                    {unit.label} - {formatPrice(unit.sell_price)}
                                </option>
                            ))}
                        </select>
                    )}
                    {showPromo && (
                        <p className="text-xs text-slate-400 line-through">
                            {formatPrice(basePrice)}
                        </p>
                    )}
                    <p className="text-base font-bold text-ink dark:text-primary-400">
                        {formatPrice(showPromo ? promoPrice : unitPrice)}
                        <span className="ml-1 text-xs font-medium text-slate-500">
                            / {selectedUnit?.label || "unit"}
                        </span>
                    </p>
                    <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                        Tersedia {availableQty} {selectedUnit?.label || "unit"}
                    </p>
                    {showBadge && !showPromo && (
                        <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                            Promo tersedia
                        </p>
                    )}
                    <button
                        type="button"
                        onClick={() =>
                            hasStock &&
                            onAddToCart(product, selectedUnit)
                        }
                        disabled={!hasStock || isAdding || availableQty < 1}
                        className="mt-2 inline-flex h-9 w-full items-center justify-center rounded-full bg-ink px-3 text-xs font-semibold text-white transition-colors hover:bg-shade-70 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500 dark:disabled:bg-slate-700"
                    >
                        {isAdding ? "Menambah..." : "+ Tambah"}
                    </button>
                </div>
            </div>

        </div>
    );
}

// Category Tab Button
function CategoryTab({ category, isActive, onClick }) {
    return (
        <button
            onClick={onClick}
            className={`
                px-4 py-2.5 rounded-full text-sm font-medium whitespace-nowrap
                transition-all duration-200 min-h-touch
                ${
                    isActive
                        ? "bg-ink text-white"
                        : "bg-white dark:bg-canvas-night-elevated text-shade-60 dark:text-slate-400 hover:bg-aloe-100 dark:hover:bg-canvas-night border border-hairline-light dark:border-hairline-dark"
                }
            `}
        >
            {category.name}
        </button>
    );
}

// Search Input
function SearchInput({
    value,
    onChange,
    onSearch,
    isSearching,
    placeholder,
    inputRef,
}) {
    return (
        <div className="relative">
            <input
                ref={inputRef}
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        onSearch?.(e.currentTarget.value);
                    }
                }}
                placeholder={
                    placeholder ||
                    "Cari produk atau scan barcode..."
                }
                className="w-full h-12 pl-4 pr-12 rounded-md border border-hairline-light dark:border-hairline-dark
                    bg-white dark:bg-canvas-night-elevated text-ink dark:text-slate-200
                    placeholder-slate-400 dark:placeholder-slate-500
                    focus:ring-4 focus:ring-aloe-100/70 focus:border-ink dark:focus:border-slate-300
                    transition-all text-base"
                disabled={isSearching}
            />
            <div className="absolute right-3 top-1/2 -translate-y-1/2">
                {isSearching ? (
                    <div className="w-5 h-5 border-2 border-ink border-t-transparent rounded-full animate-spin dark:border-white" />
                ) : (
                    <IconShoppingBag size={20} className="text-slate-400" />
                )}
            </div>
        </div>
    );
}

// Main ProductGrid Component
export default function ProductGrid({
    products = [],
    categories = [],
    selectedCategory,
    onCategoryChange,
    searchQuery,
    onSearchChange,
    onSearch,
    isSearching,
    onAddToCart,
    addingProductId,
    searchInputRef,
}) {
    const normalizedSelectedCategory =
        selectedCategory === null ? null : Number(selectedCategory);
    const query = searchQuery.toLowerCase();

    // Filter products by category and search
    const filteredProducts = products.filter((product) => {
        const matchesCategory =
            normalizedSelectedCategory === null ||
            Number(product.category_id) === normalizedSelectedCategory;
        const matchesSearch =
            !searchQuery ||
            product.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            product.barcode?.toLowerCase().includes(query) ||
            product.units?.some(
                (unit) =>
                    unit.label?.toLowerCase().includes(query) ||
                    unit.barcode?.toLowerCase().includes(query)
            );
        return matchesCategory && matchesSearch;
    });

    return (
        <div className="h-full flex flex-col">
            {/* Search Bar */}
            <div className="p-4 border-b border-hairline-light dark:border-hairline-dark">
                <SearchInput
                    value={searchQuery}
                    onChange={onSearchChange}
                    onSearch={onSearch}
                    isSearching={isSearching}
                    placeholder="Cari produk atau scan barcode..."
                    inputRef={searchInputRef}
                />
            </div>

            {/* Category Tabs */}
            <div className="px-4 py-3 border-b border-hairline-light dark:border-hairline-dark overflow-x-auto scrollbar-hide">
                <div className="flex gap-2">
                    <CategoryTab
                        category={{ id: null, name: "Semua" }}
                        isActive={normalizedSelectedCategory === null}
                        onClick={() => onCategoryChange(null)}
                    />
                    {categories.map((category) => (
                        <CategoryTab
                            key={category.id}
                            category={category}
                            isActive={
                                normalizedSelectedCategory ===
                                Number(category.id)
                            }
                            onClick={() => onCategoryChange(Number(category.id))}
                        />
                    ))}
                </div>
            </div>

            {/* Products Grid */}
            <div className="flex-1 overflow-y-auto p-4 scrollbar-thin">
                {filteredProducts.length > 0 ? (
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
                        {filteredProducts.map((product) => (
                            <ProductCard
                                key={product.id}
                                product={product}
                                onAddToCart={onAddToCart}
                                isAdding={addingProductId === product.id}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="h-full flex flex-col items-center justify-center text-slate-400 dark:text-slate-600">
                        <IconShoppingBag
                            size={48}
                            strokeWidth={1.5}
                            className="mb-3"
                        />
                        <p className="text-sm">
                            {searchQuery
                                ? "Produk tidak ditemukan"
                                : "Tidak ada produk"}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}

// Export sub-components
ProductGrid.Card = ProductCard;
ProductGrid.CategoryTab = CategoryTab;
ProductGrid.SearchInput = SearchInput;

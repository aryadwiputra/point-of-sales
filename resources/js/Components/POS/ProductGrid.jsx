import React from "react";
import {
    IconShoppingBag,
    IconPhoto,
    IconMinus,
    IconPlus,
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
    const promoPrice = Number(promoBadge?.promo_price || 0);
    const basePrice = Number(promoBadge?.base_price || product.sell_price || 0);
    const showPromo = promoBadge && promoPrice > 0 && promoPrice < basePrice;
    const showBadge = Boolean(promoBadge?.label);
    const multiUnits = (product.units || []).filter(
        (u) => !u.is_base
    );
    const [selectedUnitId, setSelectedUnitId] = React.useState(null);
    const selectedUnit =
        multiUnits.find((u) => u.unit_id === selectedUnitId) || null;
    const displayPrice = selectedUnit
        ? selectedUnit.sell_price
        : showPromo
          ? promoPrice
          : product.sell_price;

    return (
        <div
            className={`
                group relative flex flex-col bg-white dark:bg-slate-900
                rounded-2xl border border-slate-200 dark:border-slate-800
                overflow-hidden transition-all duration-200
                ${
                    hasStock
                        ? "hover:border-primary-300 dark:hover:border-primary-700 hover:shadow-lg hover:-translate-y-0.5 active:scale-[0.98]"
                        : "opacity-60"
                }
            `}
        >
            {/* Product Image — click adds base unit */}
            <button
                type="button"
                onClick={() => hasStock && onAddToCart(product, selectedUnit)}
                disabled={!hasStock || isAdding}
                className={`block text-left ${
                    hasStock ? "cursor-pointer" : "cursor-not-allowed"
                }`}
            >
            {/* Product Image */}
            <div className="relative aspect-square bg-slate-100 dark:bg-slate-800 overflow-hidden">
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
                        Sisa {product.stock}
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

                {/* Hover Add Indicator (centered on image) */}
                {hasStock && (
                    <div className="absolute inset-0 bg-primary-500/10 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none flex items-center justify-center">
                        <div className="bg-primary-500 text-white px-4 py-2 rounded-full text-sm font-semibold shadow-lg">
                            + Tambah
                        </div>
                    </div>
                )}
            </div>
            </button>

            {/* Product Info */}
            <div className="flex-1 p-3 flex flex-col justify-between min-h-[80px]">
                <h3 className="text-sm font-medium text-slate-800 dark:text-slate-200 line-clamp-2 leading-tight">
                    {product.title}
                </h3>
                <div className="mt-2">
                    {showPromo && !selectedUnit && (
                        <p className="text-xs text-slate-400 line-through">
                            {formatPrice(basePrice)}
                        </p>
                    )}
                    <p className="text-base font-bold text-primary-600 dark:text-primary-400">
                        {formatPrice(displayPrice)}
                        {selectedUnit && (
                            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                                {" "}/{selectedUnit.code}
                            </span>
                        )}
                    </p>
                    {showBadge && !showPromo && !selectedUnit && (
                        <p className="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                            Promo tersedia
                        </p>
                    )}
                    {multiUnits.length > 0 && (
                        <div className="mt-1.5 flex flex-wrap gap-1">
                            <button
                                type="button"
                                onClick={() => setSelectedUnitId(null)}
                                className={`px-1.5 py-0.5 rounded-md text-[10px] font-medium border transition-colors ${
                                    selectedUnit === null
                                        ? "bg-primary-500 text-white border-primary-500"
                                        : "border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                                }`}
                            >
                                Dasar
                            </button>
                            {multiUnits.map((u) => (
                                <button
                                    key={u.unit_id}
                                    type="button"
                                    onClick={() => setSelectedUnitId(u.unit_id)}
                                    className={`px-1.5 py-0.5 rounded-md text-[10px] font-medium border transition-colors ${
                                        selectedUnitId === u.unit_id
                                            ? "bg-primary-500 text-white border-primary-500"
                                            : "border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                                    }`}
                                >
                                    {u.code}
                                </button>
                            ))}
                        </div>
                    )}
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
                px-4 py-2.5 rounded-xl text-sm font-medium whitespace-nowrap
                transition-all duration-200 min-h-touch
                ${
                    isActive
                        ? "bg-primary-500 text-white shadow-md shadow-primary-500/30"
                        : "bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700"
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
                onKeyDown={(e) => e.key === "Enter" && onSearch?.()}
                placeholder={
                    placeholder ||
                    "Cari produk atau scan barcode... (/ untuk fokus)"
                }
                className="w-full h-12 pl-4 pr-12 rounded-xl border border-slate-200 dark:border-slate-700
                    bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200
                    placeholder-slate-400 dark:placeholder-slate-500
                    focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 dark:focus:border-primary-500
                    transition-all text-base"
                disabled={isSearching}
            />
            <div className="absolute right-3 top-1/2 -translate-y-1/2">
                {isSearching ? (
                    <div className="w-5 h-5 border-2 border-primary-500 border-t-transparent rounded-full animate-spin" />
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

    // Filter products by category and search
    const filteredProducts = products.filter((product) => {
        const matchesCategory =
            normalizedSelectedCategory === null ||
            Number(product.category_id) === normalizedSelectedCategory;
        const matchesSearch =
            !searchQuery ||
            product.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
            product.barcode?.toLowerCase().includes(searchQuery.toLowerCase());
        return matchesCategory && matchesSearch;
    });

    return (
        <div className="h-full flex flex-col">
            {/* Search Bar */}
            <div className="p-4 border-b border-slate-200 dark:border-slate-800">
                <SearchInput
                    value={searchQuery}
                    onChange={onSearchChange}
                    onSearch={onSearch}
                    isSearching={isSearching}
                    placeholder="Cari produk atau scan barcode... (tekan / untuk fokus)"
                    inputRef={searchInputRef}
                />
            </div>

            {/* Category Tabs */}
            <div className="px-4 py-3 border-b border-slate-200 dark:border-slate-800 overflow-x-auto scrollbar-hide">
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

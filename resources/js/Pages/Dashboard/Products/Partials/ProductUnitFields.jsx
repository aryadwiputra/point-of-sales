import React from "react";
import Input from "@/Components/Dashboard/Input";
import {
    IconBarcode,
    IconCircleCheck,
    IconCurrencyDollar,
    IconPlus,
    IconScale,
    IconTrash,
} from "@tabler/icons-react";

export const createProductUnit = (overrides = {}) => ({
    id: null,
    label: "",
    conversion_qty: "",
    is_base_unit: false,
    buy_price: "",
    sell_price: "",
    barcode: "",
    ...overrides,
});

export const productUnitsFromProduct = (product) => {
    if (product?.units?.length) {
        return product.units.map((unit) =>
            createProductUnit({
                id: unit.id,
                label: unit.label || "",
                conversion_qty: unit.conversion_qty || "1",
                is_base_unit: Boolean(unit.is_base_unit),
                buy_price: unit.buy_price || "",
                sell_price: unit.sell_price || "",
                barcode: unit.barcode || "",
            })
        );
    }

    return [
        createProductUnit({
            label: "pcs",
            conversion_qty: "1",
            is_base_unit: true,
            buy_price: product?.buy_price || "",
            sell_price: product?.sell_price || "",
            barcode: product?.barcode || "",
        }),
    ];
};

export default function ProductUnitFields({ data, setData, errors = {} }) {
    const units = data.product_units || [];

    const errorFor = (index, field) => errors[`product_units.${index}.${field}`];

    const setUnits = (nextUnits) => {
        setData("product_units", nextUnits);
    };

    const updateUnit = (index, field, value) => {
        const nextUnits = units.map((unit, unitIndex) =>
            unitIndex === index ? { ...unit, [field]: value } : unit
        );

        setUnits(nextUnits);
    };

    const setBaseUnit = (index) => {
        setUnits(
            units.map((unit, unitIndex) => ({
                ...unit,
                is_base_unit: unitIndex === index,
                conversion_qty: unitIndex === index ? "1" : unit.conversion_qty,
            }))
        );
    };

    const addUnit = () => {
        setUnits([...units, createProductUnit()]);
    };

    const removeUnit = (index) => {
        if (units.length <= 1) {
            return;
        }

        const removedBaseUnit = units[index]?.is_base_unit;
        const nextUnits = units.filter((_, unitIndex) => unitIndex !== index);

        if (removedBaseUnit && nextUnits.length > 0) {
            nextUnits[0] = {
                ...nextUnits[0],
                is_base_unit: true,
                conversion_qty: "1",
            };
        }

        setUnits(nextUnits);
    };

    const formatCurrency = (value) =>
        Number(value || 0).toLocaleString("id-ID", {
            style: "currency",
            currency: "IDR",
            minimumFractionDigits: 0,
        });

    return (
        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                    <IconScale size={18} />
                    Satuan & Harga
                </h3>
                <button
                    type="button"
                    onClick={addUnit}
                    className="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-primary-200 bg-primary-50 text-sm font-medium text-primary-700 hover:bg-primary-100 dark:border-primary-900 dark:bg-primary-950/40 dark:text-primary-300"
                >
                    <IconPlus size={16} />
                    Tambah Satuan
                </button>
            </div>

            {errors.product_units && (
                <div className="mb-3 rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-600 dark:border-danger-900 dark:bg-danger-950/30 dark:text-danger-400">
                    {errors.product_units}
                </div>
            )}

            <div className="space-y-4">
                {units.map((unit, index) => {
                    const profit = Number(unit.sell_price || 0) - Number(unit.buy_price || 0);

                    return (
                        <div
                            key={index}
                            className={`rounded-xl border p-4 ${
                                unit.is_base_unit
                                    ? "border-primary-300 bg-primary-50/70 dark:border-primary-900 dark:bg-primary-950/20"
                                    : "border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50"
                            }`}
                        >
                            <div className="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <label className="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                                    <input
                                        type="radio"
                                        name="base_product_unit"
                                        checked={unit.is_base_unit}
                                        onChange={() => setBaseUnit(index)}
                                        className="h-4 w-4 border-slate-300 text-primary-500 focus:ring-primary-500"
                                    />
                                    <IconCircleCheck size={16} />
                                    Satuan Dasar
                                </label>

                                {units.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => removeUnit(index)}
                                        className="inline-flex items-center justify-center gap-2 rounded-xl border border-danger-200 bg-white px-3 py-2 text-sm font-medium text-danger-600 hover:bg-danger-50 dark:border-danger-900 dark:bg-slate-900 dark:hover:bg-danger-950/30"
                                    >
                                        <IconTrash size={16} />
                                        Hapus
                                    </button>
                                )}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                                <Input
                                    type="text"
                                    label="Label Satuan"
                                    value={unit.label}
                                    onChange={(e) =>
                                        updateUnit(index, "label", e.target.value)
                                    }
                                    errors={errorFor(index, "label")}
                                    placeholder="pcs, dus, kg"
                                />
                                <Input
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    label="Konversi"
                                    value={unit.conversion_qty}
                                    onChange={(e) =>
                                        updateUnit(index, "conversion_qty", e.target.value)
                                    }
                                    errors={errorFor(index, "conversion_qty")}
                                    placeholder="1"
                                    disabled={unit.is_base_unit}
                                />
                                <Input
                                    type="number"
                                    min="0"
                                    label="Harga Beli"
                                    value={unit.buy_price}
                                    onChange={(e) =>
                                        updateUnit(index, "buy_price", e.target.value)
                                    }
                                    errors={errorFor(index, "buy_price")}
                                    placeholder="0"
                                    icon={<IconCurrencyDollar size={16} />}
                                />
                                <Input
                                    type="number"
                                    min="0"
                                    label="Harga Jual"
                                    value={unit.sell_price}
                                    onChange={(e) =>
                                        updateUnit(index, "sell_price", e.target.value)
                                    }
                                    errors={errorFor(index, "sell_price")}
                                    placeholder="0"
                                    icon={<IconCurrencyDollar size={16} />}
                                />
                                <Input
                                    type="text"
                                    label="Barcode"
                                    value={unit.barcode}
                                    onChange={(e) =>
                                        updateUnit(index, "barcode", e.target.value)
                                    }
                                    errors={errorFor(index, "barcode")}
                                    placeholder="Kode barcode"
                                    icon={<IconBarcode size={16} />}
                                />
                            </div>

                            {unit.buy_price > 0 && unit.sell_price > 0 && (
                                <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                                    <span className="rounded-full bg-white px-3 py-1 font-medium dark:bg-slate-900">
                                        Profit {formatCurrency(profit)}
                                    </span>
                                    <span className="rounded-full bg-white px-3 py-1 font-medium dark:bg-slate-900">
                                        Margin{" "}
                                        {unit.buy_price > 0
                                            ? ((profit / Number(unit.buy_price)) * 100).toFixed(1)
                                            : "0.0"}
                                        %
                                    </span>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

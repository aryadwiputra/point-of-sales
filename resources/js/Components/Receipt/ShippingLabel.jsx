import React from "react";
import {
    IconMapPin,
    IconPhone,
    IconUser,
    IconPackage,
    IconCalendar,
    IconInvoice,
} from "@tabler/icons-react";

export default function ShippingLabel({ transaction, store = {} }) {
    const formatPrice = (price = 0) =>
        Number(price || 0).toLocaleString("id-ID", {
            style: "currency",
            currency: "IDR",
            minimumFractionDigits: 0,
        });

    const formatDate = (value) => {
        if (!value) return "-";
        const d = new Date(value);
        return d.toLocaleDateString("id-ID", {
            day: "2-digit",
            month: "short",
            year: "numeric",
        });
    };

    const SimpleBarcode = ({ value }) => {
        const bars = (value || "").split("").map((char, idx) => {
            const weight = (char.charCodeAt(0) + idx * 17) % 4;
            return 2 + weight;
        });

        return (
            <div className="flex items-end gap-[1px] mt-1 justify-center sm:justify-end">
                {bars.map((w, i) => (
                    <span
                        key={i}
                        style={{ width: `${w}px` }}
                        className="h-8 bg-black block"
                    />
                ))}
            </div>
        );
    };

    const storeName = store?.name || "Bihaju POS";
    const storeInitial = storeName?.[0] || "T";
    const storeLogo = store?.logo;
    const customer = transaction?.customer || {};
    const region = [
        customer.village_name,
        customer.district_name,
        customer.regency_name,
        customer.province_name,
    ]
        .filter(Boolean)
        .join(", ");

    return (
        <div className="w-full flex justify-center py-4 sm:py-0">
            <style>
                {`
                    @media print {
                        @page {
                            size: 150mm 100mm;
                            margin: 0;
                        }
                        body {
                            margin: 0;
                            padding: 0;
                            -webkit-print-color-adjust: exact;
                        }
                        .shipping-label-container {
                            box-shadow: none !important;
                            border: 1px solid #e2e8f0 !important;
                            width: 150mm !important;
                            height: 100mm !important;
                            margin: 0 !important;
                            border-radius: 0 !important;
                        }
                    }
                `}
            </style>

            <div
                className="shipping-label-container bg-white border-2 border-slate-300 rounded-2xl p-6 shadow-sm relative overflow-hidden flex flex-col justify-between"
                style={{
                    width: "150mm",
                    minHeight: "100mm",
                }}
            >
                {/* Decorative Side Bar (Commerce Style) */}
                <div className="absolute left-0 top-0 bottom-0 w-2 bg-primary-600 print:hidden" />

                <div>
                    {/* Header Section */}
                    <div className="grid grid-cols-[1fr,auto] gap-4 border-b-2 border-dashed border-slate-200 pb-4 mb-4">
                        <div className="flex items-center gap-4">
                            <div className="w-16 h-16 bg-slate-50 rounded-xl flex items-center justify-center p-2 border border-slate-100 flex-shrink-0">
                                {storeLogo ? (
                                    <img src={storeLogo} alt={storeName} className="max-w-full max-h-full object-contain" />
                                ) : (
                                    <span className="text-2xl font-black text-primary-600">{storeInitial}</span>
                                )}
                            </div>
                            <div className="min-w-0">
                                <h2 className="text-xl font-bold text-slate-900 leading-tight truncate">{storeName}</h2>
                                <p className="text-xs text-slate-500 flex items-center gap-1 mt-1">
                                    <IconPhone size={14} /> {store.phone || "-"}
                                </p>
                            </div>
                        </div>

                        <div className="text-right border-l pl-4 border-slate-200">
                            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">No. Invoice</span>
                            <p className="text-xl font-black text-primary-600 tabular-nums">{transaction?.invoice}</p>
                            <p className="text-xs text-slate-500 font-medium">{formatDate(transaction?.created_at)}</p>
                        </div>
                    </div>

                    {/* Address Grid */}
                    <div className="grid grid-cols-2 gap-6">
                        {/* Left Side: Penerima */}
                        <div className="space-y-3">
                            <div className="flex items-center gap-2 text-primary-600">
                                <IconUser size={18} />
                                <span className="text-xs font-bold uppercase tracking-wider">Penerima</span>
                            </div>
                            <div className="pl-1">
                                <h3 className="text-lg font-bold text-slate-900">{customer.name || "Pelanggan Umum"}</h3>
                                <p className="text-sm font-semibold text-slate-700 mt-1">{customer.phone || ""}</p>
                                <div className="flex gap-2 mt-2">
                                    <IconMapPin size={16} className="text-slate-400 shrink-0 mt-0.5" />
                                    <p className="text-xs text-slate-600 leading-relaxed italic uppercase">
                                        {customer.address || "Ambil di Toko"}
                                    </p>
                                </div>
                                {region && (
                                    <p className="text-[11px] text-slate-500 mt-1 uppercase">
                                        {region}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Right Side: Order Summary */}
                        <div className="bg-slate-50 rounded-xl p-4 border border-slate-100">
                            <div className="flex items-center gap-2 text-slate-500 mb-3">
                                <IconPackage size={18} />
                                <span className="text-xs font-bold uppercase tracking-wider">Isi Paket</span>
                            </div>
                            <div className="space-y-2">
                                <div className="text-[11px] text-slate-600 line-clamp-3 font-medium leading-relaxed">
                                    {transaction?.details?.map(item => `${item.product?.title} (x${item.qty})`).join(", ")}
                                </div>
                                <div className="pt-2 border-t border-slate-200 flex justify-between items-center">
                                    <span className="text-[10px] font-bold text-slate-400 uppercase">Total Bayar</span>
                                    <span className="text-sm font-black text-slate-900">{formatPrice(transaction?.grand_total)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Footer Barcode */}
                <div className="flex justify-between items-end mt-4 pt-4 border-t-2 border-slate-100">
                    <div className="text-[10px] text-slate-400 font-medium italic">
                        Dicetak pada: {new Date().toLocaleString('id-ID')}
                    </div>
                    <div className="flex flex-col items-end">
                        <SimpleBarcode value={transaction?.invoice} />
                        <span className="text-[10px] font-bold text-slate-800 tracking-[3px] mt-1 mr-1 uppercase">
                            {transaction?.invoice}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}

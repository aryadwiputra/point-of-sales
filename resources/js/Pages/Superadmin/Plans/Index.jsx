import React, { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import SuperadminLayout from '@/Layouts/SuperadminLayout';
import {
    IconPackages,
    IconEdit,
    IconCheck,
    IconX,
    IconDeviceFloppy,
    IconPlus,
} from '@tabler/icons-react';
import { normalizeFeature, formatFeature } from '@/Utils/planFeatures';
import { describeFee } from '@/Utils/transactionFee';

const FEE_METHOD_OPTIONS = [
    { value: 'cash', label: 'Tunai' },
    { value: 'bank_transfer', label: 'Transfer Bank' },
    { value: 'qris', label: 'QRIS' },
    { value: 'midtrans', label: 'Midtrans' },
    { value: 'xendit', label: 'Xendit' },
    { value: 'pay_later', label: 'Bayar Belakangan' },
];

const inputClass =
    'w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white';

export default function PlansIndex({ plans }) {
    const [editingPlan, setEditingPlan] = useState(null);
    const [featureInput, setFeatureInput] = useState('');

    const form = useForm({
        name: '',
        price_monthly: 0,
        price_yearly: 0,
        max_outlets: 1,
        max_users: 1,
        max_products: 100,
        max_customers: 500,
        features: [],
        is_active: true,
        billing_type: 'subscription',
        fee_type: 'percent',
        fee_value: 1.5,
        fee_min: 500,
        fee_max: '',
        fee_rounding: 'none',
        fee_methods: [],
    });

    const openEdit = (plan) => {
        setEditingPlan(plan);
        setFeatureInput('');
        form.setData({
            name: plan.name,
            price_monthly: plan.price_monthly,
            price_yearly: plan.price_yearly,
            max_outlets: plan.max_outlets,
            max_users: plan.max_users,
            max_products: plan.max_products,
            max_customers: plan.max_customers,
            features: Array.isArray(plan.features) ? [...plan.features] : [],
            is_active: plan.is_active,
            billing_type: plan.billing_type || 'subscription',
            fee_type: plan.fee_type || 'percent',
            fee_value: plan.fee_value ?? 0,
            fee_min: plan.fee_min ?? 0,
            fee_max: plan.fee_max ?? '',
            fee_rounding: plan.fee_rounding || 'none',
            fee_methods: Array.isArray(plan.fee_methods) ? [...plan.fee_methods] : [],
        });
    };

    const toggleFeeMethod = (value) => {
        const current = form.data.fee_methods;
        form.setData(
            'fee_methods',
            current.includes(value)
                ? current.filter((m) => m !== value)
                : [...current, value]
        );
    };

    const addFeature = () => {
        const value = normalizeFeature(featureInput);
        if (!value) return;
        if (form.data.features.includes(value)) {
            setFeatureInput('');
            return;
        }
        form.setData('features', [...form.data.features, value]);
        setFeatureInput('');
    };

    const removeFeature = (index) => {
        form.setData('features', form.data.features.filter((_, i) => i !== index));
    };

    const handleFeatureKeyDown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addFeature();
        } else if (e.key === 'Backspace' && !featureInput && form.data.features.length > 0) {
            removeFeature(form.data.features.length - 1);
        }
    };

    const submitEdit = (e) => {
        e.preventDefault();
        form.put(route('superadmin.plans.update', editingPlan.id), {
            preserveScroll: true,
            onSuccess: () => setEditingPlan(null),
        });
    };

    const formatRupiah = (val) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(val || 0);
    };

    return (
        <SuperadminLayout>
            <Head title="Manajemen Paket Langganan & Batasan Kuota" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Paket Langganan & Batasan Kuota</h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Konfigurasi harga, batas sumber daya (cabang, kasir, produk), dan fitur per jenjang.</p>
                </div>

                {/* Plans Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
                    {plans.map((plan) => (
                        <div key={plan.id} className="p-6 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 flex flex-col justify-between shadow-sm">
                            <div>
                                <div className="flex items-center justify-between">
                                    <h2 className="text-xl font-bold text-slate-900 dark:text-white">{plan.name}</h2>
                                    <span className="text-xs px-2.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 font-mono">
                                        {plan.tenants_count} Penyewa
                                    </span>
                                </div>
                                <div className="mt-4">
                                    {plan.billing_type === 'per_transaction' ? (
                                        <>
                                            <div className="text-2xl font-extrabold text-slate-900 dark:text-white">
                                                Rp0
                                                <span className="text-sm font-medium text-slate-500 dark:text-slate-400"> / bulan</span>
                                            </div>
                                            <div className="text-xs font-semibold text-warning-600 dark:text-warning-400 mt-0.5">
                                                {describeFee(plan)}
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <div className="text-2xl font-extrabold text-slate-900 dark:text-white">{formatRupiah(plan.price_monthly)}</div>
                                            <div className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{formatRupiah(plan.price_yearly)} / tahun</div>
                                        </>
                                    )}
                                </div>

                                <div className="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800/80 space-y-2.5 text-sm text-slate-700 dark:text-slate-300">
                                    <div className="flex justify-between">
                                        <span className="text-slate-500 dark:text-slate-400">Maks. Cabang:</span>
                                        <span className="font-semibold text-slate-900 dark:text-white">{plan.max_outlets}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-500 dark:text-slate-400">Maks. Staff/Kasir:</span>
                                        <span className="font-semibold text-slate-900 dark:text-white">{plan.max_users}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-500 dark:text-slate-400">Maks. Master Produk:</span>
                                        <span className="font-semibold text-slate-900 dark:text-white">{plan.max_products}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-500 dark:text-slate-400">Maks. Pelanggan:</span>
                                        <span className="font-semibold text-slate-900 dark:text-white">{plan.max_customers}</span>
                                    </div>
                                </div>

                                {plan.features && plan.features.length > 0 && (
                                    <div className="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/80">
                                        <span className="text-xs text-slate-500 dark:text-slate-400 font-medium uppercase tracking-wider">Fitur Unggulan:</span>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {plan.features.map((f, i) => (
                                                <span key={i} className="text-[11px] px-2 py-0.5 bg-slate-100 border border-slate-200 rounded text-slate-700 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300">
                                                    {formatFeature(f)}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800/80">
                                <button
                                    onClick={() => openEdit(plan)}
                                    className="w-full flex items-center justify-center space-x-1.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 text-sm font-medium rounded-xl transition"
                                >
                                    <IconEdit size={16} />
                                    <span>Edit Kuota & Harga</span>
                                </button>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Edit Modal */}
                {editingPlan && (
                    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto p-6 space-y-4 shadow-xl">
                            <div className="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                                <h3 className="font-bold text-slate-900 dark:text-white text-lg">Edit Paket: {editingPlan.name}</h3>
                                <button onClick={() => setEditingPlan(null)} className="text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                                    <IconX size={18} />
                                </button>
                            </div>

                            <form onSubmit={submitEdit} className="space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Nama Paket</label>
                                    <input
                                        type="text"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white"
                                        required
                                    />
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Harga Bulanan (Rp)</label>
                                        <input
                                            type="number"
                                            value={form.data.price_monthly}
                                            onChange={(e) => form.setData('price_monthly', e.target.value)}
                                            className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Harga Tahunan (Rp)</label>
                                        <input
                                            type="number"
                                            value={form.data.price_yearly}
                                            onChange={(e) => form.setData('price_yearly', e.target.value)}
                                            className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white"
                                            required
                                        />
                                    </div>
                                </div>

                                {/* Billing & biaya transaksi (tier pay-per-transaksi) */}
                                <div className="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40 p-3 space-y-3">
                                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400">Tipe Billing</label>
                                    <select
                                        value={form.data.billing_type}
                                        onChange={(e) => form.setData('billing_type', e.target.value)}
                                        className={inputClass}
                                    >
                                        <option value="subscription">Langganan (bulanan / tahunan)</option>
                                        <option value="per_transaction">Bayar Per Transaksi (tanpa langganan)</option>
                                    </select>

                                    {form.data.billing_type === 'per_transaction' && (
                                        <>
                                            <div className="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Tipe Biaya</label>
                                                    <select
                                                        value={form.data.fee_type}
                                                        onChange={(e) => form.setData('fee_type', e.target.value)}
                                                        className={inputClass}
                                                    >
                                                        <option value="percent">Persentase (%)</option>
                                                        <option value="fixed">Nominal (Rp)</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">
                                                        Nilai Biaya {form.data.fee_type === 'percent' ? '(%)' : '(Rp)'}
                                                    </label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={form.data.fee_value}
                                                        onChange={(e) => form.setData('fee_value', e.target.value)}
                                                        className={inputClass}
                                                        required
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-3 gap-3">
                                                <div>
                                                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Minimum (Rp)</label>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        value={form.data.fee_min}
                                                        onChange={(e) => form.setData('fee_min', e.target.value)}
                                                        className={inputClass}
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Maksimum (Rp)</label>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        placeholder="Tanpa batas"
                                                        value={form.data.fee_max}
                                                        onChange={(e) => form.setData('fee_max', e.target.value)}
                                                        className={inputClass}
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Pembulatan</label>
                                                    <select
                                                        value={form.data.fee_rounding}
                                                        onChange={(e) => form.setData('fee_rounding', e.target.value)}
                                                        className={inputClass}
                                                    >
                                                        <option value="none">Standar</option>
                                                        <option value="up">Ke atas</option>
                                                        <option value="down">Ke bawah</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div>
                                                <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Berlaku untuk Metode Bayar</label>
                                                <div className="flex flex-wrap gap-2">
                                                    {FEE_METHOD_OPTIONS.map((method) => {
                                                        const active = form.data.fee_methods.includes(method.value);
                                                        return (
                                                            <button
                                                                key={method.value}
                                                                type="button"
                                                                onClick={() => toggleFeeMethod(method.value)}
                                                                className={`px-2.5 py-1.5 rounded-lg text-xs font-medium border transition ${
                                                                    active
                                                                        ? 'bg-primary-500 border-primary-500 text-white'
                                                                        : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'
                                                                }`}
                                                            >
                                                                {method.label}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                                <p className="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500">
                                                    Kosongkan semua = semua metode kena biaya.
                                                </p>
                                            </div>
                                        </>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Maks. Cabang</label>
                                        <input
                                            type="number"
                                            value={form.data.max_outlets}
                                            onChange={(e) => form.setData('max_outlets', e.target.value)}
                                            className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Maks. Staff</label>
                                        <input
                                            type="number"
                                            value={form.data.max_users}
                                            onChange={(e) => form.setData('max_users', e.target.value)}
                                            className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white"
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Maks. Produk</label>
                                        <input
                                            type="number"
                                            value={form.data.max_products}
                                            onChange={(e) => form.setData('max_products', e.target.value)}
                                            className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Maks. Pelanggan</label>
                                        <input
                                            type="number"
                                            value={form.data.max_customers}
                                            onChange={(e) => form.setData('max_customers', e.target.value)}
                                            className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-white"
                                            required
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">Fitur Unggulan</label>
                                    <div className="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-2">
                                        {form.data.features.length > 0 && (
                                            <div className="flex flex-wrap gap-1.5 mb-2">
                                                {form.data.features.map((f, i) => (
                                                    <span
                                                        key={`${f}-${i}`}
                                                        className="inline-flex items-center gap-1 text-[11px] px-2 py-1 bg-indigo-100 border border-indigo-200 text-indigo-700 rounded-lg dark:bg-indigo-500/10 dark:border-indigo-500/20 dark:text-indigo-400"
                                                    >
                                                        {formatFeature(f)}
                                                        <button
                                                            type="button"
                                                            onClick={() => removeFeature(i)}
                                                            className="hover:text-indigo-900 dark:hover:text-indigo-200"
                                                            aria-label={`Hapus ${f}`}
                                                        >
                                                            <IconX size={12} />
                                                        </button>
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="text"
                                                value={featureInput}
                                                onChange={(e) => setFeatureInput(e.target.value)}
                                                onKeyDown={handleFeatureKeyDown}
                                                placeholder="Ketik fitur (mis. Multi Outlet)"
                                                className="flex-1 min-w-0 px-2 py-1.5 bg-transparent border-0 text-sm text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-0"
                                            />
                                            <button
                                                type="button"
                                                onClick={addFeature}
                                                disabled={!featureInput.trim()}
                                                className="shrink-0 inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 disabled:opacity-50"
                                            >
                                                <IconPlus size={14} />
                                                Tambah
                                            </button>
                                        </div>
                                    </div>
                                    <p className="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                                        Tampil sebagai chip di kartu paket. Tekan Backspace saat kolom kosong untuk menghapus chip terakhir.
                                    </p>
                                </div>

                                <div className="flex justify-end space-x-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                                    <button
                                        type="button"
                                        onClick={() => setEditingPlan(null)}
                                        className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 text-sm font-medium rounded-xl"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-xl flex items-center space-x-1.5"
                                    >
                                        <IconDeviceFloppy size={16} />
                                        <span>Simpan Perubahan</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </SuperadminLayout>
    );
}

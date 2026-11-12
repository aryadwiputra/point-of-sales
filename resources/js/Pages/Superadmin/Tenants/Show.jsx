import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import SuperadminLayout from '@/Layouts/SuperadminLayout';
import {
    IconArrowLeft,
    IconBuildingStore,
    IconUser,
    IconPackages,
    IconCalendarTime,
    IconLock,
    IconCircleCheck,
    IconAlertCircle,
    IconCurrencyDollar
} from '@tabler/icons-react';

export default function TenantShow({ tenant, stats, plans }) {
    // Extend Subscription Form
    const extendForm = useForm({
        days: 30,
    });

    const submitExtend = (e) => {
        e.preventDefault();
        extendForm.post(route('superadmin.tenants.extend', tenant.id), {
            preserveScroll: true,
        });
    };

    // Change Plan Form
    const planForm = useForm({
        plan_id: tenant.plan_id || '',
    });

    const submitPlan = (e) => {
        e.preventDefault();
        planForm.patch(route('superadmin.tenants.plan', tenant.id), {
            preserveScroll: true,
        });
    };

    // Change Status Form
    const statusForm = useForm({
        status: tenant.status,
    });

    const submitStatus = (e) => {
        e.preventDefault();
        statusForm.patch(route('superadmin.tenants.status', tenant.id), {
            preserveScroll: true,
        });
    };

    // Reset Owner Password Form
    const passwordForm = useForm({
        password: '',
    });

    const submitPassword = (e) => {
        e.preventDefault();
        passwordForm.post(route('superadmin.tenants.reset-password', tenant.id), {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    };

    const formatDate = (dateString) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    };

    return (
        <SuperadminLayout>
            <Head title={`Detail Tenant - ${tenant.name}`} />

            <div className="space-y-6">
                {/* Back Link & Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center space-x-3">
                        <Link
                            href={route('superadmin.tenants.index')}
                            className="p-2 bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 rounded-xl transition"
                        >
                            <IconArrowLeft size={18} />
                        </Link>
                        <div>
                            <div className="flex items-center space-x-2">
                                <h1 className="text-2xl font-bold text-slate-900 dark:text-white">{tenant.name}</h1>
                                <span className="text-xs px-2.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 font-mono">
                                    {tenant.subdomain}
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Terdaftar sejak: {formatDate(tenant.created_at)}</p>
                        </div>
                    </div>
                </div>

                {/* Resource Stats Grid */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div className="p-4 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80">
                        <div className="text-xs text-slate-500 dark:text-slate-400">Total Cabang (Outlets)</div>
                        <div className="text-xl font-bold text-slate-900 dark:text-slate-100 mt-1">{stats.outlets_count} <span className="text-xs text-slate-500 font-normal">/ {tenant.plan?.max_outlets}</span></div>
                    </div>
                    <div className="p-4 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80">
                        <div className="text-xs text-slate-500 dark:text-slate-400">Total Staff / Kasir</div>
                        <div className="text-xl font-bold text-slate-900 dark:text-slate-100 mt-1">{stats.users_count} <span className="text-xs text-slate-500 font-normal">/ {tenant.plan?.max_users}</span></div>
                    </div>
                    <div className="p-4 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80">
                        <div className="text-xs text-slate-500 dark:text-slate-400">Total Master Produk</div>
                        <div className="text-xl font-bold text-slate-900 dark:text-slate-100 mt-1">{stats.products_count} <span className="text-xs text-slate-500 font-normal">/ {tenant.plan?.max_products}</span></div>
                    </div>
                    <div className="p-4 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80">
                        <div className="text-xs text-slate-500 dark:text-slate-400">Total Pelanggan</div>
                        <div className="text-xl font-bold text-slate-900 dark:text-slate-100 mt-1">{stats.customers_count} <span className="text-xs text-slate-500 font-normal">/ {tenant.plan?.max_customers}</span></div>
                    </div>
                </div>

                {/* Two Column Layout: Plan/Subscription & Account Actions */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Panel 1: Subscription & Plan Management */}
                    <div className="p-6 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 space-y-6">
                        <div className="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 pb-3">
                            <IconCalendarTime className="text-indigo-600 dark:text-indigo-400" size={20} />
                            <h2 className="font-semibold text-slate-900 dark:text-slate-100">Langganan & Paket</h2>
                        </div>

                        {/* Status Change */}
                        <form onSubmit={submitStatus} className="space-y-3">
                            <label className="block text-xs font-medium text-slate-500 dark:text-slate-400">Status Akun</label>
                            <div className="flex gap-2">
                                <select
                                    value={statusForm.data.status}
                                    onChange={(e) => statusForm.setData('status', e.target.value)}
                                    className="flex-1 px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-700 dark:text-slate-200"
                                >
                                    <option value="active">Active</option>
                                    <option value="trial">Trial</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="expired">Expired</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <button
                                    type="submit"
                                    disabled={statusForm.processing}
                                    className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 text-sm font-medium rounded-xl transition"
                                >
                                    Simpan Status
                                </button>
                            </div>
                        </form>

                        {/* Plan Change */}
                        <form onSubmit={submitPlan} className="space-y-3">
                            <label className="block text-xs font-medium text-slate-500 dark:text-slate-400">Paket Langganan</label>
                            <div className="flex gap-2">
                                <select
                                    value={planForm.data.plan_id}
                                    onChange={(e) => planForm.setData('plan_id', e.target.value)}
                                    className="flex-1 px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-700 dark:text-slate-200"
                                >
                                    {plans.map((p) => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                                <button
                                    type="submit"
                                    disabled={planForm.processing}
                                    className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-xl transition"
                                >
                                    Ganti Paket
                                </button>
                            </div>
                        </form>

                        {/* Extend Subscription */}
                        <form onSubmit={submitExtend} className="space-y-3 pt-3 border-t border-slate-200 dark:border-slate-800/80">
                            <div className="flex items-center justify-between">
                                <label className="block text-xs font-medium text-slate-500 dark:text-slate-400">Perpanjang Masa Aktif</label>
                                <span className="text-xs text-indigo-600 dark:text-indigo-400">
                                    Berlaku s/d: <strong className="text-slate-900 dark:text-slate-200">{formatDate(tenant.subscription_ends_at)}</strong>
                                </span>
                            </div>
                            <div className="flex gap-2">
                                <select
                                    value={extendForm.data.days}
                                    onChange={(e) => extendForm.setData('days', Number(e.target.value))}
                                    className="flex-1 px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-700 dark:text-slate-200"
                                >
                                    <option value={7}>+ 7 Hari</option>
                                    <option value={30}>+ 30 Hari (1 Bulan)</option>
                                    <option value={90}>+ 90 Hari (3 Bulan)</option>
                                    <option value={180}>+ 180 Hari (6 Bulan)</option>
                                    <option value={365}>+ 365 Hari (1 Tahun)</option>
                                </select>
                                <button
                                    type="submit"
                                    disabled={extendForm.processing}
                                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium rounded-xl transition"
                                >
                                    Perpanjang
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Panel 2: Owner Credentials & Reset Password */}
                    <div className="p-6 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 space-y-6">
                        <div className="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 pb-3">
                            <IconUser className="text-indigo-600 dark:text-indigo-400" size={20} />
                            <h2 className="font-semibold text-slate-900 dark:text-slate-100">Pemilik Bisnis (Owner)</h2>
                        </div>

                        <div className="space-y-2 text-sm text-slate-700 dark:text-slate-300">
                            <div>Nama: <strong className="text-slate-900 dark:text-white">{tenant.owner?.name || '-'}</strong></div>
                            <div>Email Login: <strong className="text-slate-900 dark:text-white">{tenant.owner?.email || '-'}</strong></div>
                        </div>

                        {/* Reset Password */}
                        <form onSubmit={submitPassword} className="space-y-3 pt-3 border-t border-slate-200 dark:border-slate-800/80">
                            <label className="block text-xs font-medium text-slate-500 dark:text-slate-400">Reset Password Owner</label>
                            <div className="space-y-2">
                                <input
                                    type="password"
                                    placeholder="Password baru (min. 8 karakter)..."
                                    value={passwordForm.data.password}
                                    onChange={(e) => passwordForm.setData('password', e.target.value)}
                                    className="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-slate-100"
                                    required
                                />
                                {passwordForm.errors.password && (
                                    <div className="text-xs text-rose-500 dark:text-rose-400">{passwordForm.errors.password}</div>
                                )}
                                <button
                                    type="submit"
                                    disabled={passwordForm.processing}
                                    className="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 text-sm font-medium rounded-xl transition"
                                >
                                    Perbarui Password Owner
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {/* Subscriptions History Table */}
                <div className="p-6 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80">
                    <h2 className="text-base font-semibold text-slate-900 dark:text-slate-200 mb-4">Riwayat Penagihan & Langganan</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                            <thead className="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className="px-4 py-2.5">Siklus</th>
                                    <th className="px-4 py-2.5">Nominal</th>
                                    <th className="px-4 py-2.5">Metode</th>
                                    <th className="px-4 py-2.5">Status</th>
                                    <th className="px-4 py-2.5">Masa Berlaku</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800/60">
                                {tenant.subscriptions.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-6 text-center text-slate-500 dark:text-slate-400">Belum ada riwayat transaksi langganan.</td>
                                    </tr>
                                ) : (
                                    tenant.subscriptions.map((sub) => (
                                        <tr key={sub.id}>
                                            <td className="px-4 py-3 capitalize">{sub.billing_cycle}</td>
                                            <td className="px-4 py-3 font-semibold text-slate-900 dark:text-white">Rp {Number(sub.amount).toLocaleString('id-ID')}</td>
                                            <td className="px-4 py-3">{sub.payment_method || '-'}</td>
                                            <td className="px-4 py-3">
                                                <span className="px-2 py-0.5 rounded text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                                                    {sub.payment_status}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                                {formatDate(sub.starts_at)} - {formatDate(sub.ends_at)}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </SuperadminLayout>
    );
}

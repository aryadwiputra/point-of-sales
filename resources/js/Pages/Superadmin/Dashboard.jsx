import React from 'react';
import { Head, Link } from '@inertiajs/react';
import SuperadminLayout from '@/Layouts/SuperadminLayout';
import {
    IconBuildingStore,
    IconCurrencyDollar,
    IconUsers,
    IconAlertTriangle,
    IconClock,
    IconCircleCheck,
    IconArrowUpRight
} from '@tabler/icons-react';

export default function Dashboard({ metrics, plans, recentTenants, recentSubscriptions }) {
    const formatRupiah = (val) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0
        }).format(val || 0);
    };

    const metricCards = [
        {
            title: 'Estimasi MRR',
            value: formatRupiah(metrics.mrr),
            desc: 'Pendapatan bulanan berulang',
            icon: IconCurrencyDollar,
            color: 'text-emerald-600 bg-emerald-50 border-emerald-200 dark:text-emerald-400 dark:bg-emerald-500/10 dark:border-emerald-500/20',
        },
        {
            title: 'Penyewa Aktif',
            value: metrics.activeTenants,
            desc: `Dari total ${metrics.totalTenants} tenant`,
            icon: IconBuildingStore,
            color: 'text-indigo-600 bg-indigo-50 border-indigo-200 dark:text-indigo-400 dark:bg-indigo-500/10 dark:border-indigo-500/20',
        },
        {
            title: 'Masa Trial',
            value: metrics.trialTenants,
            desc: 'Calon pelanggan baru',
            icon: IconClock,
            color: 'text-amber-600 bg-amber-50 border-amber-200 dark:text-amber-400 dark:bg-amber-500/10 dark:border-amber-500/20',
        },
        {
            title: 'Total Akun Pengguna',
            value: metrics.totalPlatformUsers,
            desc: 'Pengguna terdaftar platform',
            icon: IconUsers,
            color: 'text-cyan-600 bg-cyan-50 border-cyan-200 dark:text-cyan-400 dark:bg-cyan-500/10 dark:border-cyan-500/20',
        },
    ];

    const getStatusBadge = (status) => {
        const styles = {
            active: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
            trial: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
            suspended: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
            expired: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/20',
            cancelled: 'bg-zinc-100 text-zinc-600 border-zinc-200 dark:bg-zinc-500/10 dark:text-zinc-400 dark:border-zinc-500/20',
        };
        return (
            <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${styles[status] || styles.expired}`}>
                {status?.toUpperCase()}
            </span>
        );
    };

    return (
        <SuperadminLayout>
            <Head title="Platform Superadmin Dashboard" />

            <div className="space-y-8">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Ringkasan Platform SaaS</h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Pemantauan metrik pendapatan, aktivitas penyewa, dan utilisasi paket.</p>
                </div>

                {/* Metric Cards Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {metricCards.map((card, idx) => {
                        const Icon = card.icon;
                        return (
                            <div key={idx} className="p-5 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 shadow-sm flex flex-col justify-between">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{card.title}</span>
                                    <div className={`p-2 rounded-xl border ${card.color}`}>
                                        <Icon size={18} />
                                    </div>
                                </div>
                                <div className="mt-4">
                                    <div className="text-2xl font-bold text-slate-900 dark:text-slate-100">{card.value}</div>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">{card.desc}</p>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Plans Overview Cards */}
                <div>
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-base font-semibold text-slate-900 dark:text-slate-200">Distribusi Paket Langganan</h2>
                        <Link href={route('superadmin.plans.index')} className="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 font-medium flex items-center space-x-1">
                            <span>Kelola Paket</span>
                            <IconArrowUpRight size={14} />
                        </Link>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {plans.map((plan) => (
                            <div key={plan.id} className="p-5 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80">
                                <div className="flex items-center justify-between">
                                    <h3 className="font-bold text-slate-900 dark:text-slate-100 text-lg">{plan.name}</h3>
                                    <span className="text-xs px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 font-mono">
                                        {plan.tenants_count} Tenant
                                    </span>
                                </div>
                                <div className="mt-3 text-sm text-slate-700 dark:text-slate-300">
                                    <span className="text-lg font-extrabold text-slate-900 dark:text-white">{formatRupiah(plan.price_monthly)}</span>
                                    <span className="text-xs text-slate-500 dark:text-slate-400"> / bulan</span>
                                </div>
                                <div className="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800/80 text-xs text-slate-600 dark:text-slate-400 grid grid-cols-2 gap-2">
                                    <div>Maks Cabang: <span className="font-semibold text-slate-900 dark:text-slate-200">{plan.max_outlets}</span></div>
                                    <div>Maks Kasir: <span className="font-semibold text-slate-900 dark:text-slate-200">{plan.max_users}</span></div>
                                    <div>Maks Produk: <span className="font-semibold text-slate-900 dark:text-slate-200">{plan.max_products}</span></div>
                                    <div>Maks Pelanggan: <span className="font-semibold text-slate-900 dark:text-slate-200">{plan.max_customers}</span></div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Recent Tenants Table */}
                <div className="p-6 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80">
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <h2 className="text-base font-semibold text-slate-900 dark:text-slate-200">Penyewa Terbaru</h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">Daftar toko/usaha yang baru mendaftar pada platform.</p>
                        </div>
                        <Link href={route('superadmin.tenants.index')} className="text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 font-medium flex items-center space-x-1">
                            <span>Lihat Semua</span>
                            <IconArrowUpRight size={14} />
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                            <thead className="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className="px-4 py-3">Nama Toko & Subdomain</th>
                                    <th className="px-4 py-3">Owner</th>
                                    <th className="px-4 py-3">Paket</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800/60">
                                {recentTenants.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Belum ada tenant terdaftar.</td>
                                    </tr>
                                ) : (
                                    recentTenants.map((tenant) => (
                                        <tr key={tenant.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-slate-900 dark:text-slate-100">{tenant.name}</div>
                                                <div className="text-xs text-indigo-600 dark:text-indigo-400 font-mono">{tenant.subdomain}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="text-slate-900 dark:text-slate-200">{tenant.owner?.name || '-'}</div>
                                                <div className="text-xs text-slate-500 dark:text-slate-400">{tenant.owner?.email || '-'}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="text-xs font-medium text-slate-900 dark:text-slate-200">{tenant.plan?.name || '-'}</span>
                                            </td>
                                            <td className="px-4 py-3">
                                                {getStatusBadge(tenant.status)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={route('superadmin.tenants.show', tenant.id)}
                                                    className="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 rounded-lg transition"
                                                >
                                                    Detail
                                                </Link>
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

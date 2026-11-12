import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import SuperadminLayout from '@/Layouts/SuperadminLayout';
import {
    IconSearch,
    IconReceipt,
    IconCurrencyDollar,
    IconChartBar,
    IconBuildingStore,
    IconTrendingUp,
} from '@tabler/icons-react';

export default function TransactionsIndex({ transactions, summary, monthly, topTenants, tenants, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [tenantId, setTenantId] = useState(filters.tenant_id || '');
    const [paymentStatus, setPaymentStatus] = useState(filters.payment_status || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

    const formatRupiah = (val) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val || 0);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('superadmin.transactions.index'), {
            search: search || undefined,
            tenant_id: tenantId || undefined,
            payment_status: paymentStatus || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }, { preserveState: true });
    };

    const handleReset = () => {
        router.get(route('superadmin.transactions.index'));
    };

    const maxMonthlyRevenue = Math.max(...monthly.map((m) => m.revenue_total), 1);

    const summaryCards = [
        {
            title: 'Total Transaksi',
            value: summary.transactions_count.toLocaleString('id-ID'),
            desc: 'Sesuai filter aktif',
            icon: IconReceipt,
            color: 'text-indigo-600 bg-indigo-50 border-indigo-200 dark:text-indigo-400 dark:bg-indigo-500/10 dark:border-indigo-500/20',
        },
        {
            title: 'Total Omzet',
            value: formatRupiah(summary.revenue_total),
            desc: 'Akumulasi grand total',
            icon: IconCurrencyDollar,
            color: 'text-emerald-600 bg-emerald-50 border-emerald-200 dark:text-emerald-400 dark:bg-emerald-500/10 dark:border-emerald-500/20',
        },
        {
            title: 'Rata-rata / Transaksi',
            value: formatRupiah(summary.average_transaction),
            desc: 'Nilai keranjang rata-rata',
            icon: IconChartBar,
            color: 'text-amber-600 bg-amber-50 border-amber-200 dark:text-amber-400 dark:bg-amber-500/10 dark:border-amber-500/20',
        },
        {
            title: 'Tenant Bertransaksi',
            value: summary.tenants_transacting.toLocaleString('id-ID'),
            desc: 'Penyewa dengan transaksi',
            icon: IconBuildingStore,
            color: 'text-cyan-600 bg-cyan-50 border-cyan-200 dark:text-cyan-400 dark:bg-cyan-500/10 dark:border-cyan-500/20',
        },
    ];

    const getStatusBadge = (status) => {
        const styles = {
            paid: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
            unpaid: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
            pending: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
            failed: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
            expired: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/20',
            refunded: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
        };

        return (
            <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${styles[status] || styles.expired}`}>
                {(status || 'unknown').toUpperCase()}
            </span>
        );
    };

    return (
        <SuperadminLayout>
            <Head title="Monitoring Transaksi Platform" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Monitoring Transaksi Platform</h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Pantau aktivitas penjualan lintas tenant secara real-time.</p>
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    {summaryCards.map((card) => (
                        <div key={card.title} className="p-5 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80">
                            <div className="flex items-center justify-between">
                                <div>
                                    <div className="text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">{card.title}</div>
                                    <div className="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{card.value}</div>
                                    <div className="text-xs text-slate-500 dark:text-slate-500 mt-1">{card.desc}</div>
                                </div>
                                <div className={`p-3 rounded-xl border ${card.color}`}>
                                    <card.icon size={22} />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Trend + top tenants */}
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <div className="xl:col-span-2 p-5 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80">
                        <div className="flex items-center justify-between mb-5">
                            <div>
                                <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Tren Omzet 6 Bulan Terakhir</h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400">Seluruh tenant.</p>
                            </div>
                            <IconTrendingUp size={20} className="text-indigo-600 dark:text-indigo-400" />
                        </div>

                        <div className="flex items-end justify-between gap-3 h-44">
                            {monthly.map((month) => {
                                const height = Math.max(4, Math.round((month.revenue_total / maxMonthlyRevenue) * 100));

                                return (
                                    <div key={month.month} className="flex-1 flex flex-col items-center justify-end h-full group">
                                        <div className="text-[10px] text-slate-500 dark:text-slate-400 opacity-0 group-hover:opacity-100 transition mb-1">
                                            {formatRupiah(month.revenue_total)}
                                        </div>
                                        <div
                                            className="w-full max-w-[46px] rounded-t-lg bg-gradient-to-t from-indigo-600 to-cyan-500 transition-all"
                                            style={{ height: `${height}%` }}
                                            title={`${month.label}: ${formatRupiah(month.revenue_total)} (${month.transactions_count} transaksi)`}
                                        />
                                        <div className="text-[10px] text-slate-500 mt-2 text-center">{month.label}</div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="p-5 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80">
                        <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Top 5 Tenant</h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mb-4">Berdasarkan omzet sepanjang waktu.</p>

                        {topTenants.length === 0 ? (
                            <div className="text-sm text-slate-500 dark:text-slate-400 py-6 text-center">Belum ada transaksi.</div>
                        ) : (
                            <div className="space-y-3">
                                {topTenants.map((tenant, index) => (
                                    <div key={tenant.tenant_id} className="flex items-center justify-between gap-3">
                                        <div className="flex items-center space-x-3 min-w-0">
                                            <span className="w-6 h-6 shrink-0 rounded-lg bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 text-xs font-bold flex items-center justify-center">
                                                {index + 1}
                                            </span>
                                            <div className="min-w-0">
                                                <div className="text-sm text-slate-900 dark:text-slate-100 truncate">{tenant.name}</div>
                                                <div className="text-[11px] text-slate-500 truncate">
                                                    {tenant.transactions_count} transaksi
                                                </div>
                                            </div>
                                        </div>
                                        <div className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                            {formatRupiah(tenant.revenue_total)}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <form onSubmit={handleFilter} className="p-4 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 flex flex-col xl:flex-row gap-3">
                    <div className="flex-1 relative">
                        <IconSearch size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Cari nomor invoice atau referensi pembayaran..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
                        />
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <select
                            value={tenantId}
                            onChange={(e) => setTenantId(e.target.value)}
                            className="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:border-indigo-500"
                        >
                            <option value="">Semua Tenant</option>
                            {tenants.map((tenant) => (
                                <option key={tenant.id} value={tenant.id}>{tenant.name}</option>
                            ))}
                        </select>

                        <select
                            value={paymentStatus}
                            onChange={(e) => setPaymentStatus(e.target.value)}
                            className="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:border-indigo-500"
                        >
                            <option value="">Semua Status</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                            <option value="expired">Expired</option>
                            <option value="refunded">Refunded</option>
                        </select>

                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:border-indigo-500"
                        />
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:border-indigo-500"
                        />

                        <button
                            type="submit"
                            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl text-sm transition"
                        >
                            Filter
                        </button>
                        <button
                            type="button"
                            onClick={handleReset}
                            className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 font-medium rounded-xl text-sm transition"
                        >
                            Reset
                        </button>
                    </div>
                </form>

                {/* Table */}
                <div className="rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                            <thead className="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className="px-4 py-3">Invoice</th>
                                    <th className="px-4 py-3">Tenant</th>
                                    <th className="px-4 py-3">Kasir / Cabang</th>
                                    <th className="px-4 py-3">Metode</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Grand Total</th>
                                    <th className="px-4 py-3">Waktu</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800/60">
                                {transactions.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                            Tidak ada transaksi yang cocok dengan kriteria filter.
                                        </td>
                                    </tr>
                                ) : (
                                    transactions.data.map((transaction) => (
                                        <tr key={transaction.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                                            <td className="px-4 py-3.5">
                                                <div className="font-mono text-xs text-indigo-600 dark:text-indigo-300">{transaction.invoice}</div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="text-slate-900 dark:text-slate-100 font-medium">{transaction.tenant?.name || '-'}</div>
                                                <div className="text-[11px] text-slate-500 font-mono">{transaction.tenant?.subdomain || '-'}</div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="text-slate-900 dark:text-slate-200">{transaction.cashier?.name || '-'}</div>
                                                <div className="text-[11px] text-slate-500">{transaction.warehouse?.name || '-'}</div>
                                            </td>
                                            <td className="px-4 py-3.5 text-xs uppercase text-slate-700 dark:text-slate-300">
                                                {transaction.payment_method || '-'}
                                            </td>
                                            <td className="px-4 py-3.5">{getStatusBadge(transaction.payment_status)}</td>
                                            <td className="px-4 py-3.5 text-right font-semibold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                                {formatRupiah(transaction.grand_total)}
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                                {transaction.created_at
                                                    ? new Date(transaction.created_at).toLocaleString('id-ID', {
                                                        dateStyle: 'medium',
                                                        timeStyle: 'short',
                                                    })
                                                    : '-'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {transactions.links && transactions.links.length > 3 && (
                        <div className="px-4 py-3 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <div>
                                Menampilkan {transactions.from || 0} - {transactions.to || 0} dari {transactions.total} transaksi
                            </div>
                            <div className="flex space-x-1">
                                {transactions.links.map((link, i) => (
                                    <Link
                                        key={i}
                                        href={link.url || '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-2.5 py-1 rounded-lg text-xs transition ${
                                            link.active
                                                ? 'bg-indigo-600 text-white font-medium'
                                                : link.url
                                                    ? 'hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300'
                                                    : 'text-slate-400 dark:text-slate-600 cursor-not-allowed'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </SuperadminLayout>
    );
}

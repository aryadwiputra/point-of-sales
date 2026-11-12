import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import SuperadminLayout from '@/Layouts/SuperadminLayout';
import {
    IconSearch,
    IconFilter,
    IconBuildingStore,
    IconCircleCheck,
    IconClock,
    IconBan,
    IconExternalLink,
    IconChevronLeft,
    IconChevronRight
} from '@tabler/icons-react';

export default function TenantsIndex({ tenants, plans, filters }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [planId, setPlanId] = useState(filters.plan_id || '');

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('superadmin.tenants.index'), {
            search: search || undefined,
            status: status || undefined,
            plan_id: planId || undefined,
        }, { preserveState: true });
    };

    const handleQuickStatus = (tenant, newStatus) => {
        if (!confirm(`Ubah status tenant "${tenant.name}" menjadi "${newStatus}"?`)) return;
        router.patch(route('superadmin.tenants.status', tenant.id), {
            status: newStatus,
        }, { preserveScroll: true });
    };

    const getStatusBadge = (s) => {
        const styles = {
            active: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20',
            trial: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20',
            suspended: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:border-rose-500/20',
            expired: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/20',
            cancelled: 'bg-zinc-100 text-zinc-600 border-zinc-200 dark:bg-zinc-500/10 dark:text-zinc-400 dark:border-zinc-500/20',
        };
        return (
            <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${styles[s] || styles.expired}`}>
                {s?.toUpperCase()}
            </span>
        );
    };

    return (
        <SuperadminLayout>
            <Head title="Manajemen Penyewa (Tenants)" />

            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Daftar Penyewa Toko (Tenants)</h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Kelola seluruh instans bisnis penyewa, status akun, dan langganan.</p>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <form onSubmit={handleFilter} className="p-4 rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 flex flex-col md:flex-row gap-3">
                    <div className="flex-1 relative">
                        <IconSearch size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Cari nama toko, subdomain, atau email owner..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition"
                        />
                    </div>

                    <div className="flex flex-wrap sm:flex-nowrap gap-3">
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:border-indigo-500"
                        >
                            <option value="">Semua Status</option>
                            <option value="active">Active</option>
                            <option value="trial">Trial</option>
                            <option value="suspended">Suspended</option>
                            <option value="expired">Expired</option>
                            <option value="cancelled">Cancelled</option>
                        </select>

                        <select
                            value={planId}
                            onChange={(e) => setPlanId(e.target.value)}
                            className="px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:border-indigo-500"
                        >
                            <option value="">Semua Paket</option>
                            {plans.map((p) => (
                                <option key={p.id} value={p.id}>{p.name}</option>
                            ))}
                        </select>

                        <button
                            type="submit"
                            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-xl text-sm transition"
                        >
                            Filter
                        </button>
                    </div>
                </form>

                {/* Table */}
                <div className="rounded-2xl bg-white dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 overflow-hidden shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                            <thead className="bg-slate-50 dark:bg-slate-900 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider border-b border-slate-200 dark:border-slate-800">
                                <tr>
                                    <th className="px-4 py-3">Bisnis & Subdomain</th>
                                    <th className="px-4 py-3">Owner</th>
                                    <th className="px-4 py-3">Paket</th>
                                    <th className="px-4 py-3">Resource</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800/60">
                                {tenants.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                            Tidak ada tenant yang cocok dengan kriteria pencarian.
                                        </td>
                                    </tr>
                                ) : (
                                    tenants.data.map((t) => (
                                        <tr key={t.id} className="hover:bg-slate-50 dark:hover:bg-slate-900/40 transition">
                                            <td className="px-4 py-3.5">
                                                <div className="font-semibold text-slate-900 dark:text-slate-100 flex items-center space-x-1.5">
                                                    <span>{t.name}</span>
                                                </div>
                                                <div className="text-xs text-indigo-600 dark:text-indigo-400 font-mono mt-0.5">
                                                    {t.subdomain}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <div className="text-slate-900 dark:text-slate-200">{t.owner?.name || '-'}</div>
                                                <div className="text-xs text-slate-500 dark:text-slate-400">{t.owner?.email || '-'}</div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                <span className="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700">
                                                    {t.plan?.name || '-'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3.5 text-xs text-slate-500 dark:text-slate-400 space-y-0.5">
                                                <div>Cabang: <span className="text-slate-900 dark:text-slate-200 font-medium">{t.outlets_count}</span></div>
                                                <div>Staff: <span className="text-slate-900 dark:text-slate-200 font-medium">{t.users_count}</span></div>
                                                <div>Produk: <span className="text-slate-900 dark:text-slate-200 font-medium">{t.products_count}</span></div>
                                            </td>
                                            <td className="px-4 py-3.5">
                                                {getStatusBadge(t.status)}
                                            </td>
                                            <td className="px-4 py-3.5 text-right space-x-2">
                                                {t.status === 'active' ? (
                                                    <button
                                                        onClick={() => handleQuickStatus(t, 'suspended')}
                                                        className="px-2.5 py-1 text-xs font-medium text-rose-600 hover:text-rose-700 hover:bg-rose-50 dark:text-rose-400 dark:hover:text-rose-300 dark:hover:bg-rose-500/10 rounded-lg transition"
                                                        title="Suspend tenant"
                                                    >
                                                        Suspend
                                                    </button>
                                                ) : (
                                                    <button
                                                        onClick={() => handleQuickStatus(t, 'active')}
                                                        className="px-2.5 py-1 text-xs font-medium text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:text-emerald-300 dark:hover:bg-emerald-500/10 rounded-lg transition"
                                                        title="Aktifkan tenant"
                                                    >
                                                        Aktifkan
                                                    </button>
                                                )}

                                                <Link
                                                    href={route('superadmin.tenants.show', t.id)}
                                                    className="inline-flex items-center px-2.5 py-1 text-xs font-medium bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 dark:bg-indigo-600/20 dark:hover:bg-indigo-600/30 dark:text-indigo-300 dark:border-indigo-500/30 rounded-lg transition"
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

                    {/* Pagination */}
                    {tenants.links && tenants.links.length > 3 && (
                        <div className="px-4 py-3 bg-slate-50 dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                            <div>Menampilkan {tenants.from || 0} - {tenants.to || 0} dari {tenants.total} tenant</div>
                            <div className="flex space-x-1">
                                {tenants.links.map((link, i) => (
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

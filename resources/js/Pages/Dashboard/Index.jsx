import Widget from "@/Components/Dashboard/Widget";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head } from "@inertiajs/react";
import { useEffect, useMemo, useRef } from "react";
import Chart from "chart.js/auto";
import {
    IconBox,
    IconCategory,
    IconMoneybag,
    IconUsers,
    IconCoin,
    IconReceipt,
} from "@tabler/icons-react";

const formatCurrency = (value = 0) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(value);

export default function Dashboard({
    totalCategories,
    totalProducts,
    totalTransactions,
    totalUsers,
    revenueTrend,
    totalRevenue,
    totalProfit,
    averageOrder,
    todayTransactions,
    topProducts = [],
    recentTransactions = [],
    topCustomers = [],
}) {
    const chartRef = useRef(null);
    const chartInstance = useRef(null);

    const chartData = useMemo(() => revenueTrend ?? [], [revenueTrend]);

    useEffect(() => {
        if (!chartRef.current) return;

        if (chartInstance.current) {
            chartInstance.current.destroy();
            chartInstance.current = null;
        }

        if (!chartData.length) {
            return;
        }

        const labels = chartData.map((item) => item.label);
        const totals = chartData.map((item) => item.total);

        chartInstance.current = new Chart(chartRef.current, {
            type: "line",
            data: {
                labels,
                datasets: [
                    {
                        label: "Pendapatan",
                        data: totals,
                        borderColor: "#4f46e5",
                        backgroundColor: "rgba(79,70,229,0.2)",
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: "#4f46e5",
                    },
                ],
            },
            options: {
                plugins: {
                    legend: {
                        display: false,
                    },
                },
                scales: {
                    y: {
                        ticks: {
                            callback: (value) =>
                                new Intl.NumberFormat("id-ID", {
                                    style: "currency",
                                    currency: "IDR",
                                    maximumFractionDigits: 0,
                                }).format(value),
                        },
                        grid: {
                            color: "rgba(148, 163, 184, 0.2)",
                        },
                    },
                    x: {
                        grid: {
                            display: false,
                        },
                    },
                },
            },
        });

        return () => {
            chartInstance.current?.destroy();
        };
    }, [chartData]);

    return (
        <>
            <Head title="Dashboard" />

            <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <Widget
                    title="Kategori"
                    subtitle="Total Kategori"
                    color="bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    icon={<IconCategory size={20} strokeWidth={1.5} />}
                    total={totalCategories}
                />

                <Widget
                    title="Produk"
                    subtitle="Total Produk"
                    color="bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    icon={<IconBox size={20} strokeWidth={1.5} />}
                    total={totalProducts}
                />

                <Widget
                    title="Transaksi"
                    subtitle="Total Transaksi"
                    color="bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    icon={<IconMoneybag size={20} strokeWidth={1.5} />}
                    total={totalTransactions}
                />

                <Widget
                    title="Pengguna"
                    subtitle="Total Pengguna"
                    color="bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                    icon={<IconUsers size={20} strokeWidth={1.5} />}
                    total={totalUsers}
                />
            </div>

            <div className="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <p className="text-sm text-gray-500">Total Pendapatan</p>
                    <p className="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                        {formatCurrency(totalRevenue)}
                    </p>
                    <p className="text-xs text-gray-400 flex items-center gap-1 mt-2">
                        <IconCoin size={16} /> Akumulasi seluruh transaksi
                    </p>
                </div>
                <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <p className="text-sm text-gray-500">Total Profit</p>
                    <p className="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                        {formatCurrency(totalProfit)}
                    </p>
                    <p className="text-xs text-gray-400 flex items-center gap-1 mt-2">
                        <IconMoneybag size={16} /> Profit bersih tercatat
                    </p>
                </div>
                <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <p className="text-sm text-gray-500">Rata-Rata Order</p>
                    <p className="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                        {formatCurrency(averageOrder)}
                    </p>
                    <p className="text-xs text-gray-400 flex items-center gap-1 mt-2">
                        <IconReceipt size={16} /> Per transaksi
                    </p>
                </div>
                <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
                    <p className="text-sm text-gray-500">Transaksi Hari Ini</p>
                    <p className="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                        {todayTransactions}
                    </p>
                    <p className="text-xs text-gray-400 flex items-center gap-1 mt-2">
                        <IconUsers size={16} /> Update harian
                    </p>
                </div>
            </div>

            <div className="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-900 dark:bg-gray-950">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                Tren Pendapatan
                            </h3>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                12 data transaksi terakhir
                            </p>
                        </div>
                    </div>
                    <div className="mt-4">
                        {chartData.length ? (
                            <canvas ref={chartRef} height={200}></canvas>
                        ) : (
                            <div className="flex h-40 items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                                Belum ada data pendapatan untuk ditampilkan.
                            </div>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-900 dark:bg-gray-950">
                    <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Top Produk Terlaris
                    </h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        Berdasarkan total penjualan sepanjang waktu
                    </p>
                    {topProducts.length ? (
                        <ul className="space-y-3">
                            {topProducts.map((product, index) => (
                                <li
                                    key={index}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <div>
                                        <p className="font-medium text-gray-900 dark:text-white">
                                            {product.name}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {product.qty} item
                                        </p>
                                    </div>
                                    <p className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                        {formatCurrency(product.total)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="flex h-40 items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                            Belum ada data produk.
                        </div>
                    )}
                </div>
            </div>

            <div className="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-900 dark:bg-gray-950">
                    <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Transaksi Terbaru
                    </h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        5 transaksi terakhir
                    </p>
                    {recentTransactions.length ? (
                        <div className="space-y-3">
                            {recentTransactions.map((trx, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <div>
                                        <p className="font-semibold text-gray-900 dark:text-white">
                                            {trx.invoice}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {trx.date} • {trx.customer}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            Kasir: {trx.cashier}
                                        </p>
                                    </div>
                                    <p className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                        {formatCurrency(trx.total)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="flex h-40 items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                            Belum ada transaksi.
                        </div>
                    )}
                </div>

                <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-900 dark:bg-gray-950">
                    <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                        Pelanggan Terbaik
                    </h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">
                        Berdasarkan nilai pembelian
                    </p>
                    {topCustomers.length ? (
                        <ul className="space-y-3">
                            {topCustomers.map((customer, index) => (
                                <li
                                    key={index}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <div>
                                        <p className="font-medium text-gray-900 dark:text-white">
                                            {customer.name}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {customer.orders} transaksi
                                        </p>
                                    </div>
                                    <p className="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                        {formatCurrency(customer.total)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="flex h-40 items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                            Belum ada data pelanggan.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page) => <DashboardLayout children={page} />;

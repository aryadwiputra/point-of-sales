import DashboardLayout from "@/Layouts/DashboardLayout";
import InputSelect from "@/Components/Dashboard/InputSelect";
import Table from "@/Components/Dashboard/Table";
import { Head, router } from "@inertiajs/react";
import { useEffect, useMemo, useRef, useState } from "react";
import { loadChart } from "@/Utils/charts";
import {
    IconChartBar,
    IconClock,
    IconCoin,
    IconDatabaseOff,
    IconFilter,
    IconPackage,
    IconReceipt2,
    IconSearch,
    IconTrendingDown,
    IconTrendingUp,
    IconUsers,
    IconX,
} from "@tabler/icons-react";

const defaultFilters = {
    start_date: "",
    end_date: "",
    cashier_id: "",
    customer_id: "",
    category_id: "",
};

const formatCurrency = (value = 0) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(value);

const formatPercentage = (value = 0) =>
    new Intl.NumberFormat("id-ID", {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);

const formatDateTime = (value) =>
    value
        ? new Intl.DateTimeFormat("id-ID", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(value))
        : "-";

const coverageStatusConfig = {
    critical: {
        label: "Kritis",
        className:
            "bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300",
    },
    low: {
        label: "Rendah",
        className:
            "bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300",
    },
    healthy: {
        label: "Sehat",
        className:
            "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300",
    },
    no_movement: {
        label: "Tidak Bergerak",
        className:
            "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300",
    },
};

const promoStatusConfig = {
    active: {
        label: "Aktif",
        className:
            "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300",
    },
    scheduled: {
        label: "Terjadwal",
        className:
            "bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300",
    },
    expired: {
        label: "Berakhir",
        className:
            "bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300",
    },
    inactive: {
        label: "Nonaktif",
        className:
            "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300",
    },
};

const promoKindLabel = {
    standard_discount: "Discount",
    qty_break: "Grosir",
    bundle_price: "Bundle",
    buy_x_get_y: "BXGY",
};

const crmCampaignTypeLabel = {
    promo_broadcast: "Promo Broadcast",
    invoice_share: "Invoice Share",
    due_date_reminder: "Due Reminder",
    repeat_order_reminder: "Repeat Order",
};

function SummaryCard({ title, value, description, icon: Icon, gradient }) {
    return (
        <div
            className={`relative overflow-hidden rounded-2xl p-5 bg-gradient-to-br ${gradient} text-white shadow-lg`}
        >
            <div className="relative z-10">
                <div className="mb-2 flex items-center gap-2">
                    <div className="rounded-xl bg-white/20 p-2">
                        <Icon size={18} />
                    </div>
                    <span className="text-sm font-medium opacity-90">
                        {title}
                    </span>
                </div>
                <p className="text-2xl font-bold">{value}</p>
                <p className="mt-1 text-sm opacity-80">{description}</p>
            </div>
        </div>
    );
}

function EmptyState({ message }) {
    return (
        <div className="flex h-40 items-center justify-center">
            <div className="text-center">
                <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                    <IconDatabaseOff size={24} className="text-slate-400" />
                </div>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                    {message}
                </p>
            </div>
        </div>
    );
}

function ChartCard({ title, subtitle, chartRef, hasData }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div className="mb-4">
                <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                    {title}
                </h2>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                    {subtitle}
                </p>
            </div>
            {hasData ? (
                <div className="h-72">
                    <canvas ref={chartRef} />
                </div>
            ) : (
                <EmptyState message="Belum ada data untuk periode ini." />
            )}
        </div>
    );
}

export default function Insights({
    filters,
    cashiers,
    customers,
    categories,
    summary,
    salesByHour,
    salesByDay,
    topSellingProducts,
    lowPerformingProducts,
    marginByProduct,
    marginByCategory,
    cashierPerformance,
    repeatCustomerMetrics,
    stockCoverage,
    promoMonitor,
    loyaltyPerformance,
    crmOperations,
}) {
    const [showFilters, setShowFilters] = useState(false);
    const [marginView, setMarginView] = useState("product");
    const [filterData, setFilterData] = useState({
        ...defaultFilters,
        ...filters,
    });

    const [selectedCashier, setSelectedCashier] = useState(null);
    const [selectedCustomer, setSelectedCustomer] = useState(null);
    const [selectedCategory, setSelectedCategory] = useState(null);

    const salesHourChartRef = useRef(null);
    const salesHourChart = useRef(null);
    const salesDayChartRef = useRef(null);
    const salesDayChart = useRef(null);

    useEffect(() => {
        setFilterData({
            ...defaultFilters,
            ...filters,
        });
        setSelectedCashier(
            cashiers.find((item) => String(item.id) === String(filters.cashier_id || "")) || null
        );
        setSelectedCustomer(
            customers.find((item) => String(item.id) === String(filters.customer_id || "")) || null
        );
        setSelectedCategory(
            categories.find((item) => String(item.id) === String(filters.category_id || "")) || null
        );
    }, [filters, cashiers, customers, categories]);

    const hasActiveFilters =
        filterData.start_date ||
        filterData.end_date ||
        filterData.cashier_id ||
        filterData.customer_id ||
        filterData.category_id;

    const hourChartData = useMemo(
        () => salesByHour.filter((item) => item.orders_count > 0 || item.revenue_total > 0),
        [salesByHour]
    );
    const dayChartData = useMemo(() => salesByDay, [salesByDay]);

    useEffect(() => {
        let cancelled = false;

        if (salesHourChart.current) {
            salesHourChart.current.destroy();
            salesHourChart.current = null;
        }
        if (!salesHourChartRef.current || !hourChartData.length) {
            return;
        }

        const canvas = salesHourChartRef.current;

        const init = async () => {
        const Chart = await loadChart();
        if (cancelled) return;

        salesHourChart.current = new Chart(canvas, {
            type: "bar",
            data: {
                labels: hourChartData.map((item) => item.label),
                datasets: [
                    {
                        label: "Omzet",
                        data: hourChartData.map((item) => item.revenue_total),
                        backgroundColor: "#3b82f6",
                        borderRadius: 8,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                },
            },
        });
        };

        init();

        return () => {
            cancelled = true;
            salesHourChart.current?.destroy();
            salesHourChart.current = null;
        };
    }, [hourChartData]);

    useEffect(() => {
        let cancelled = false;

        if (salesDayChart.current) {
            salesDayChart.current.destroy();
            salesDayChart.current = null;
        }
        if (!salesDayChartRef.current || !dayChartData.length) {
            return;
        }

        const canvas = salesDayChartRef.current;

        const init = async () => {
        const Chart = await loadChart();
        if (cancelled) return;

        salesDayChart.current = new Chart(canvas, {
            type: "line",
            data: {
                labels: dayChartData.map((item) => item.label),
                datasets: [
                    {
                        label: "Omzet",
                        data: dayChartData.map((item) => item.revenue_total),
                        borderColor: "#10b981",
                        backgroundColor: "rgba(16, 185, 129, 0.15)",
                        fill: true,
                        tension: 0.35,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                },
            },
        });
        };

        init();

        return () => {
            cancelled = true;
            salesDayChart.current?.destroy();
            salesDayChart.current = null;
        };
    }, [dayChartData]);

    const handleChange = (field, value) =>
        setFilterData((prev) => ({ ...prev, [field]: value }));

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route("reports.insights.index"), filterData, {
            preserveState: true,
            preserveScroll: true,
        });
        setShowFilters(false);
    };

    const resetFilters = () => {
        setFilterData(defaultFilters);
        setSelectedCashier(null);
        setSelectedCustomer(null);
        setSelectedCategory(null);
        router.get(route("reports.insights.index"), defaultFilters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const marginRows =
        marginView === "product" ? marginByProduct : marginByCategory;
    const repeatSummary = repeatCustomerMetrics?.summary || {};
    const topRepeatCustomers = repeatCustomerMetrics?.top_customers || [];
    const stockCoverageSummary = stockCoverage?.summary || {};
    const stockCoverageProducts = stockCoverage?.products || [];
    const promoSummary = promoMonitor?.summary || {};
    const promoActiveRules = promoMonitor?.active_rules || [];
    const promoScheduledRules = promoMonitor?.scheduled_rules || [];
    const promoRecentAudits = promoMonitor?.recent_audits || [];
    const loyaltySummary = loyaltyPerformance?.summary || {};
    const loyaltyTopMembers = loyaltyPerformance?.top_members || [];
    const crmSummary = crmOperations?.summary || {};
    const crmRecentCampaigns = crmOperations?.recent_campaigns || [];

    return (
        <>
            <Head title="Advanced Sales Insights" />

            <div className="space-y-6">
                <div
                    data-tour="reports-header"
                    className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold text-slate-900 dark:text-white">
                            <IconChartBar
                                size={28}
                                className="text-primary-500"
                            />
                            Advanced Sales Insights
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Insight operasional penjualan, margin, produk, dan
                            performa kasir dalam satu dashboard.
                        </p>
                    </div>
                    <button
                        onClick={() => setShowFilters((value) => !value)}
                        className={`inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-medium transition-colors ${
                            showFilters || hasActiveFilters
                                ? "border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-800 dark:bg-primary-950/50 dark:text-primary-400"
                                : "border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                        }`}
                    >
                        <IconFilter size={18} />
                        Filter
                    </button>
                </div>

                <div data-tour="reports-summary" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        title="Pendapatan"
                        value={formatCurrency(summary?.revenue_total ?? 0)}
                        description={`${summary?.orders_count ?? 0} transaksi`}
                        icon={IconReceipt2}
                        gradient="from-primary-500 to-primary-700"
                    />
                    <SummaryCard
                        title="Profit"
                        value={formatCurrency(summary?.profit_total ?? 0)}
                        description={`Rata-rata ${formatCurrency(summary?.average_order ?? 0)}`}
                        icon={IconCoin}
                        gradient="from-emerald-500 to-emerald-700"
                    />
                    <SummaryCard
                        title="Item Terjual"
                        value={(summary?.items_sold ?? 0).toLocaleString("id-ID")}
                        description={`Diskon manual ${formatCurrency(summary?.manual_discount_total ?? 0)}`}
                        icon={IconPackage}
                        gradient="from-amber-500 to-amber-700"
                    />
                    <SummaryCard
                        title="Kasir Aktif di Filter"
                        value={cashierPerformance.length.toLocaleString("id-ID")}
                        description="Leaderboard performa kasir"
                        icon={IconUsers}
                        gradient="from-fuchsia-500 to-fuchsia-700"
                    />
                </div>

                {showFilters && (
                    <div
                        data-tour="reports-filters"
                        className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <form onSubmit={applyFilters}>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Tanggal Mulai
                                    </label>
                                    <input
                                        type="date"
                                        value={filterData.start_date}
                                        onChange={(event) =>
                                            handleChange("start_date", event.target.value)
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    />
                                </div>
                                <div>
                                    <label className="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                        Tanggal Akhir
                                    </label>
                                    <input
                                        type="date"
                                        value={filterData.end_date}
                                        onChange={(event) =>
                                            handleChange("end_date", event.target.value)
                                        }
                                        className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                    />
                                </div>
                                <InputSelect
                                    label="Kasir"
                                    data={cashiers}
                                    selected={selectedCashier}
                                    setSelected={(value) => {
                                        setSelectedCashier(value);
                                        handleChange("cashier_id", value ? String(value.id) : "");
                                    }}
                                    placeholder="Semua kasir"
                                    searchable
                                />
                                <InputSelect
                                    label="Pelanggan"
                                    data={customers}
                                    selected={selectedCustomer}
                                    setSelected={(value) => {
                                        setSelectedCustomer(value);
                                        handleChange("customer_id", value ? String(value.id) : "");
                                    }}
                                    placeholder="Semua pelanggan"
                                    searchable
                                />
                                <InputSelect
                                    label="Kategori"
                                    data={categories}
                                    selected={selectedCategory}
                                    setSelected={(value) => {
                                        setSelectedCategory(value);
                                        handleChange("category_id", value ? String(value.id) : "");
                                    }}
                                    placeholder="Semua kategori"
                                    searchable
                                />
                            </div>
                            <div className="mt-4 flex justify-end gap-2">
                                {hasActiveFilters && (
                                    <button
                                        type="button"
                                        onClick={resetFilters}
                                        className="rounded-xl border border-slate-200 px-4 py-2.5 text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                    >
                                        <IconX size={18} />
                                    </button>
                                )}
                                <button
                                    type="submit"
                                    className="inline-flex items-center gap-2 rounded-xl bg-primary-500 px-6 py-2.5 font-medium text-white transition-colors hover:bg-primary-600"
                                >
                                    <IconSearch size={18} />
                                    Terapkan
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                <div data-tour="reports-table" className="grid gap-6 xl:grid-cols-2">
                    <ChartCard
                        title="Sales by Hour"
                        subtitle="Pola omzet per jam dari transaksi yang lolos filter."
                        chartRef={salesHourChartRef}
                        hasData={hourChartData.length > 0}
                    />
                    <ChartCard
                        title="Sales by Day"
                        subtitle="Tren omzet harian pada periode aktif."
                        chartRef={salesDayChartRef}
                        hasData={dayChartData.length > 0}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        title="Customer Aktif"
                        value={(repeatSummary.active_customers ?? 0).toLocaleString("id-ID")}
                        description={`${repeatSummary.new_customers ?? 0} pelanggan baru`}
                        icon={IconUsers}
                        gradient="from-sky-500 to-sky-700"
                    />
                    <SummaryCard
                        title="Repeat Rate"
                        value={`${formatPercentage(repeatSummary.repeat_rate ?? 0)}%`}
                        description={`${repeatSummary.repeat_customers ?? 0} pelanggan repeat`}
                        icon={IconTrendingUp}
                        gradient="from-violet-500 to-violet-700"
                    />
                    <SummaryCard
                        title="Member Revenue Share"
                        value={`${formatPercentage(repeatSummary.member_revenue_share ?? 0)}%`}
                        description={formatCurrency(
                            repeatSummary.member_revenue_total ?? 0
                        )}
                        icon={IconCoin}
                        gradient="from-teal-500 to-teal-700"
                    />
                    <SummaryCard
                        title="Stok Perlu Perhatian"
                        value={(
                            (stockCoverageSummary.critical ?? 0) +
                            (stockCoverageSummary.low ?? 0)
                        ).toLocaleString("id-ID")}
                        description={`${stockCoverageSummary.window_days ?? 0} hari jendela analisa`}
                        icon={IconClock}
                        gradient="from-rose-500 to-rose-700"
                    />
                </div>

                <Table.Card title="Top Selling Products">
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Produk</Table.Th>
                                <Table.Th>Kategori</Table.Th>
                                <Table.Th className="text-right">Qty</Table.Th>
                                <Table.Th className="text-right">Omzet</Table.Th>
                                <Table.Th className="text-right">Profit</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {topSellingProducts.length > 0 ? (
                                topSellingProducts.map((item) => (
                                    <tr key={item.product_id}>
                                        <Table.Td>
                                            <div>
                                                <p className="font-semibold text-slate-900 dark:text-slate-100">
                                                    {item.product_title}
                                                </p>
                                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                                    {item.product_sku || "-"}
                                                </p>
                                            </div>
                                        </Table.Td>
                                        <Table.Td>{item.category_name || "-"}</Table.Td>
                                        <Table.Td className="text-right">{item.qty_sold}</Table.Td>
                                        <Table.Td className="text-right">{formatCurrency(item.revenue_total)}</Table.Td>
                                        <Table.Td className="text-right">{formatCurrency(item.profit_total)}</Table.Td>
                                    </tr>
                                ))
                            ) : (
                                <Table.Empty colSpan={5} message="Belum ada data top selling pada periode ini." />
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                <Table.Card title="Low Performing Products">
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Produk</Table.Th>
                                <Table.Th>Stok</Table.Th>
                                <Table.Th className="text-right">Qty Sold</Table.Th>
                                <Table.Th className="text-right">Omzet</Table.Th>
                                <Table.Th>Last Sold</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {lowPerformingProducts.length > 0 ? (
                                lowPerformingProducts.map((item) => (
                                    <tr key={item.product_id}>
                                        <Table.Td>
                                            <div>
                                                <p className="font-semibold text-slate-900 dark:text-slate-100">
                                                    {item.product_title}
                                                </p>
                                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                                    {item.category_name || "-"}
                                                </p>
                                            </div>
                                        </Table.Td>
                                        <Table.Td>{item.current_stock}</Table.Td>
                                        <Table.Td className="text-right">{item.qty_sold}</Table.Td>
                                        <Table.Td className="text-right">{formatCurrency(item.revenue_total)}</Table.Td>
                                        <Table.Td>{formatDateTime(item.last_sold_at)}</Table.Td>
                                    </tr>
                                ))
                            ) : (
                                <Table.Empty colSpan={5} message="Belum ada data low performing pada periode ini." />
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                <div className="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex flex-col gap-3 border-b border-slate-100 p-5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900 dark:text-white">
                                Margin per Produk / Kategori
                            </h2>
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Perbandingan omzet, profit, dan margin kotor.
                            </p>
                        </div>
                        <div className="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                            <button
                                type="button"
                                onClick={() => setMarginView("product")}
                                className={`rounded-lg px-3 py-2 text-sm font-medium ${marginView === "product" ? "bg-white text-primary-600 shadow dark:bg-slate-900" : "text-slate-600 dark:text-slate-300"}`}
                            >
                                Per Produk
                            </button>
                            <button
                                type="button"
                                onClick={() => setMarginView("category")}
                                className={`rounded-lg px-3 py-2 text-sm font-medium ${marginView === "category" ? "bg-white text-primary-600 shadow dark:bg-slate-900" : "text-slate-600 dark:text-slate-300"}`}
                            >
                                Per Kategori
                            </button>
                        </div>
                    </div>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>{marginView === "product" ? "Produk" : "Kategori"}</Table.Th>
                                <Table.Th className="text-right">Qty</Table.Th>
                                <Table.Th className="text-right">Omzet</Table.Th>
                                <Table.Th className="text-right">Profit</Table.Th>
                                <Table.Th className="text-right">Margin %</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {marginRows.length > 0 ? (
                                marginRows.map((item, index) => (
                                    <tr key={`${marginView}-${index}`}>
                                        <Table.Td>
                                            {marginView === "product"
                                                ? item.product_title
                                                : item.category_name}
                                        </Table.Td>
                                        <Table.Td className="text-right">{item.qty_sold}</Table.Td>
                                        <Table.Td className="text-right">{formatCurrency(item.revenue_total)}</Table.Td>
                                        <Table.Td className="text-right">{formatCurrency(item.profit_total)}</Table.Td>
                                        <Table.Td className="text-right">{item.margin_percentage}%</Table.Td>
                                    </tr>
                                ))
                            ) : (
                                <Table.Empty colSpan={5} message="Belum ada data margin pada periode ini." />
                            )}
                        </Table.Tbody>
                    </Table>
                </div>

                <Table.Card title="Cashier Performance">
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Kasir</Table.Th>
                                <Table.Th className="text-right">Transaksi</Table.Th>
                                <Table.Th className="text-right">Items Sold</Table.Th>
                                <Table.Th className="text-right">Omzet</Table.Th>
                                <Table.Th className="text-right">Profit</Table.Th>
                                <Table.Th className="text-right">Avg Basket</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {cashierPerformance.length > 0 ? (
                                cashierPerformance.map((item) => (
                                    <tr key={item.cashier_id}>
                                        <Table.Td>{item.cashier_name}</Table.Td>
                                        <Table.Td className="text-right">{item.orders_count}</Table.Td>
                                        <Table.Td className="text-right">{item.items_sold}</Table.Td>
                                        <Table.Td className="text-right">{formatCurrency(item.revenue_total)}</Table.Td>
                                        <Table.Td className="text-right">{formatCurrency(item.profit_total)}</Table.Td>
                                        <Table.Td className="text-right">{formatCurrency(item.average_basket)}</Table.Td>
                                    </tr>
                                ))
                            ) : (
                                <Table.Empty colSpan={6} message="Belum ada data performa kasir pada periode ini." />
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                <Table.Card title="Repeat Customer Metrics">
                    <div className="mb-4 grid gap-3 md:grid-cols-3">
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Repeat Revenue
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {formatCurrency(
                                    repeatSummary.repeat_revenue_total ?? 0
                                )}
                            </p>
                        </div>
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Revenue Member
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {formatCurrency(
                                    repeatSummary.member_revenue_total ?? 0
                                )}
                            </p>
                        </div>
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Revenue Non-Member
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {formatCurrency(
                                    repeatSummary.non_member_revenue_total ?? 0
                                )}
                            </p>
                        </div>
                    </div>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Pelanggan</Table.Th>
                                <Table.Th>Status</Table.Th>
                                <Table.Th className="text-right">Transaksi</Table.Th>
                                <Table.Th className="text-right">Omzet</Table.Th>
                                <Table.Th className="text-right">Avg Basket</Table.Th>
                                <Table.Th>Last Purchase</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {topRepeatCustomers.length > 0 ? (
                                topRepeatCustomers.map((item) => (
                                    <tr key={item.customer_id}>
                                        <Table.Td>
                                            <div>
                                                <p className="font-semibold text-slate-900 dark:text-slate-100">
                                                    {item.customer_name}
                                                </p>
                                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                                    {item.loyalty_tier
                                                        ? item.loyalty_tier
                                                              .replace("_", " ")
                                                              .toUpperCase()
                                                        : "Non-member"}
                                                </p>
                                            </div>
                                        </Table.Td>
                                        <Table.Td>
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                    item.is_loyalty_member
                                                        ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                                                        : "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                }`}
                                            >
                                                {item.is_loyalty_member
                                                    ? "Member"
                                                    : "Non-member"}
                                            </span>
                                        </Table.Td>
                                        <Table.Td className="text-right">
                                            {item.orders_count}
                                        </Table.Td>
                                        <Table.Td className="text-right">
                                            {formatCurrency(item.revenue_total)}
                                        </Table.Td>
                                        <Table.Td className="text-right">
                                            {formatCurrency(item.average_basket)}
                                        </Table.Td>
                                        <Table.Td>
                                            {formatDateTime(item.last_purchase_at)}
                                        </Table.Td>
                                    </tr>
                                ))
                            ) : (
                                <Table.Empty
                                    colSpan={6}
                                    message="Belum ada pelanggan repeat pada periode ini."
                                />
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                <Table.Card title="Stock Coverage Analysis">
                    <div className="mb-4 grid gap-3 md:grid-cols-4">
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Stok Kritis
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {(stockCoverageSummary.critical ?? 0).toLocaleString("id-ID")}
                            </p>
                        </div>
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Stok Rendah
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {(stockCoverageSummary.low ?? 0).toLocaleString("id-ID")}
                            </p>
                        </div>
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Stok Sehat
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {(stockCoverageSummary.healthy ?? 0).toLocaleString("id-ID")}
                            </p>
                        </div>
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Tidak Bergerak
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {(stockCoverageSummary.no_movement ?? 0).toLocaleString("id-ID")}
                            </p>
                        </div>
                    </div>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Produk</Table.Th>
                                <Table.Th>Status</Table.Th>
                                <Table.Th className="text-right">Stok</Table.Th>
                                <Table.Th className="text-right">Qty Sold</Table.Th>
                                <Table.Th className="text-right">Avg / Hari</Table.Th>
                                <Table.Th className="text-right">Coverage</Table.Th>
                                <Table.Th>Last Sold</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {stockCoverageProducts.length > 0 ? (
                                stockCoverageProducts.map((item) => {
                                    const status =
                                        coverageStatusConfig[
                                            item.coverage_status
                                        ] || coverageStatusConfig.no_movement;

                                    return (
                                        <tr key={item.product_id}>
                                            <Table.Td>
                                                <div>
                                                    <p className="font-semibold text-slate-900 dark:text-slate-100">
                                                        {item.product_title}
                                                    </p>
                                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                                        {item.category_name ||
                                                            "-"}
                                                    </p>
                                                </div>
                                            </Table.Td>
                                            <Table.Td>
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${status.className}`}
                                                >
                                                    {status.label}
                                                </span>
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {item.current_stock}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {item.qty_sold}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {formatPercentage(
                                                    item.average_daily_qty
                                                )}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {item.coverage_days === null
                                                    ? "-"
                                                    : `${formatPercentage(
                                                          item.coverage_days
                                                      )} hari`}
                                            </Table.Td>
                                            <Table.Td>
                                                {formatDateTime(
                                                    item.last_sold_at
                                                )}
                                            </Table.Td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <Table.Empty
                                    colSpan={7}
                                    message="Belum ada data stock coverage pada periode ini."
                                />
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Table.Card title="Promo Active Monitor">
                        <div className="mb-4 grid gap-3 md:grid-cols-2">
                            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Promo Aktif
                                </p>
                                <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                    {(promoSummary.active ?? 0).toLocaleString("id-ID")}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Promo Terjadwal
                                </p>
                                <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                    {(promoSummary.scheduled ?? 0).toLocaleString("id-ID")}
                                </p>
                            </div>
                        </div>
                        <div className="mb-4 flex flex-wrap gap-2">
                            {Object.entries(promoSummary.by_kind || {}).map(
                                ([key, count]) => (
                                    <span
                                        key={key}
                                        className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        {promoKindLabel[key] || key}:{" "}
                                        {Number(count).toLocaleString("id-ID")}
                                    </span>
                                )
                            )}
                        </div>
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th>Rule</Table.Th>
                                    <Table.Th>Tipe</Table.Th>
                                    <Table.Th>Status</Table.Th>
                                    <Table.Th>Periode</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {[...promoActiveRules, ...promoScheduledRules]
                                    .slice(0, 8)
                                    .length > 0 ? (
                                    [...promoActiveRules, ...promoScheduledRules]
                                        .slice(0, 8)
                                        .map((item) => {
                                            const status =
                                                promoStatusConfig[
                                                    item.status_label
                                                ] ||
                                                promoStatusConfig.inactive;

                                            return (
                                                <tr key={`${item.status_label}-${item.id}`}>
                                                    <Table.Td>
                                                        <div>
                                                            <p className="font-semibold text-slate-900 dark:text-slate-100">
                                                                {item.name}
                                                            </p>
                                                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                                                {item.product_title ||
                                                                    item.category_name ||
                                                                    item.target_type}
                                                            </p>
                                                        </div>
                                                    </Table.Td>
                                                    <Table.Td>
                                                        {promoKindLabel[
                                                            item.kind
                                                        ] || item.kind}
                                                    </Table.Td>
                                                    <Table.Td>
                                                        <span
                                                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${status.className}`}
                                                        >
                                                            {status.label}
                                                        </span>
                                                    </Table.Td>
                                                    <Table.Td>
                                                        <div className="text-sm text-slate-600 dark:text-slate-300">
                                                            <div>
                                                                {formatDateTime(
                                                                    item.starts_at
                                                                )}
                                                            </div>
                                                            <div>
                                                                {formatDateTime(
                                                                    item.ends_at
                                                                )}
                                                            </div>
                                                        </div>
                                                    </Table.Td>
                                                </tr>
                                            );
                                        })
                                ) : (
                                    <Table.Empty
                                        colSpan={4}
                                        message="Belum ada promo aktif atau terjadwal."
                                    />
                                )}
                            </Table.Tbody>
                        </Table>
                        <div className="mt-4 space-y-2">
                            <h3 className="text-sm font-semibold text-slate-900 dark:text-white">
                                Audit Promo Terbaru
                            </h3>
                            {promoRecentAudits.length > 0 ? (
                                promoRecentAudits.map((audit) => (
                                    <div
                                        key={audit.id}
                                        className="rounded-2xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60"
                                    >
                                        <p className="font-medium text-slate-800 dark:text-slate-100">
                                            {audit.description}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            {audit.event} •{" "}
                                            {formatDateTime(audit.created_at)}
                                        </p>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Belum ada audit promo terbaru.
                                </p>
                            )}
                        </div>
                    </Table.Card>

                    <Table.Card title="Loyalty Performance Summary">
                        <div className="mb-4 grid gap-3 md:grid-cols-2">
                            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Total Member
                                </p>
                                <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                    {(loyaltySummary.total_members ?? 0).toLocaleString("id-ID")}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Saldo Poin
                                </p>
                                <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                    {(loyaltySummary.points_balance_total ?? 0).toLocaleString("id-ID")}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Poin Earned
                                </p>
                                <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                    {(loyaltySummary.points_earned ?? 0).toLocaleString("id-ID")}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Poin Redeemed
                                </p>
                                <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                    {(loyaltySummary.points_redeemed ?? 0).toLocaleString("id-ID")}
                                </p>
                            </div>
                        </div>
                        <div className="mb-4 flex flex-wrap gap-2">
                            {Object.entries(
                                loyaltySummary.tier_distribution || {}
                            ).map(([tier, count]) => (
                                <span
                                    key={tier}
                                    className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {tier.toUpperCase()}:{" "}
                                    {Number(count).toLocaleString("id-ID")}
                                </span>
                            ))}
                        </div>
                        <div className="mb-4 grid gap-3 md:grid-cols-3">
                            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Voucher Aktif
                                </p>
                                <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                    {(
                                        loyaltySummary.voucher_summary
                                            ?.active ?? 0
                                    ).toLocaleString("id-ID")}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Voucher Digunakan
                                </p>
                                <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                    {(
                                        loyaltySummary.voucher_summary?.used ??
                                        0
                                    ).toLocaleString("id-ID")}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    Nominal Voucher
                                </p>
                                <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                    {formatCurrency(
                                        loyaltySummary.voucher_discount_total ??
                                            0
                                    )}
                                </p>
                            </div>
                        </div>
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th>Member</Table.Th>
                                    <Table.Th>Tier</Table.Th>
                                    <Table.Th className="text-right">Poin</Table.Th>
                                    <Table.Th className="text-right">Total Belanja</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {loyaltyTopMembers.length > 0 ? (
                                    loyaltyTopMembers.map((member) => (
                                        <tr key={member.id}>
                                            <Table.Td>{member.name}</Table.Td>
                                            <Table.Td>
                                                {(member.loyalty_tier || "-")
                                                    .replace("_", " ")
                                                    .toUpperCase()}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {member.loyalty_points.toLocaleString(
                                                    "id-ID"
                                                )}
                                            </Table.Td>
                                            <Table.Td className="text-right">
                                                {formatCurrency(
                                                    member.loyalty_total_spent
                                                )}
                                            </Table.Td>
                                        </tr>
                                    ))
                                ) : (
                                    <Table.Empty
                                        colSpan={4}
                                        message="Belum ada member loyalty."
                                    />
                                )}
                            </Table.Tbody>
                        </Table>
                    </Table.Card>
                </div>

                <Table.Card title="CRM Operational Snapshot">
                    <div className="mb-4 grid gap-3 md:grid-cols-4">
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Segment Aktif
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {(crmSummary.segments_active ?? 0).toLocaleString("id-ID")}
                            </p>
                        </div>
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Campaign Draft/Ready
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {(
                                    (crmSummary.campaigns_draft ?? 0) +
                                    (crmSummary.campaigns_ready ?? 0)
                                ).toLocaleString("id-ID")}
                            </p>
                        </div>
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Queue Ready
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {(
                                    crmSummary.queue_ready_to_send ?? 0
                                ).toLocaleString("id-ID")}
                            </p>
                        </div>
                        <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-800/60">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Queue Sent
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900 dark:text-white">
                                {(crmSummary.queue_sent ?? 0).toLocaleString(
                                    "id-ID"
                                )}
                            </p>
                        </div>
                    </div>
                    <div className="mb-4 flex flex-wrap gap-2">
                        <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            Manual Segment:{" "}
                            {Number(
                                crmSummary.segments_manual ?? 0
                            ).toLocaleString("id-ID")}
                        </span>
                        <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            Auto Segment:{" "}
                            {Number(
                                crmSummary.segments_auto ?? 0
                            ).toLocaleString("id-ID")}
                        </span>
                        <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            Memberships:{" "}
                            {Number(
                                crmSummary.memberships_total ?? 0
                            ).toLocaleString("id-ID")}
                        </span>
                        <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                            Campaign Processed:{" "}
                            {Number(
                                crmSummary.campaigns_processed ?? 0
                            ).toLocaleString("id-ID")}
                        </span>
                    </div>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Campaign</Table.Th>
                                <Table.Th>Tipe</Table.Th>
                                <Table.Th>Status</Table.Th>
                                <Table.Th className="text-right">Target</Table.Th>
                                <Table.Th>Diproses</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {crmRecentCampaigns.length > 0 ? (
                                crmRecentCampaigns.map((campaign) => (
                                    <tr key={campaign.id}>
                                        <Table.Td>{campaign.name}</Table.Td>
                                        <Table.Td>
                                            {crmCampaignTypeLabel[
                                                campaign.type
                                            ] || campaign.type}
                                        </Table.Td>
                                        <Table.Td>{campaign.status}</Table.Td>
                                        <Table.Td className="text-right">
                                            {campaign.logs_count}
                                        </Table.Td>
                                        <Table.Td>
                                            {formatDateTime(
                                                campaign.processed_at ||
                                                    campaign.created_at
                                            )}
                                        </Table.Td>
                                    </tr>
                                ))
                            ) : (
                                <Table.Empty
                                    colSpan={5}
                                    message="Belum ada campaign CRM terbaru."
                                />
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>
            </div>
        </>
    );
}

Insights.layout = (page) => <DashboardLayout children={page} />;

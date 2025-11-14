import React, { useEffect, useMemo, useState } from "react";
import { Head, router } from "@inertiajs/react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import Card from "@/Components/Dashboard/Card";
import Input from "@/Components/Dashboard/Input";
import Button from "@/Components/Dashboard/Button";
import InputSelect from "@/Components/Dashboard/InputSelect";
import Table from "@/Components/Dashboard/Table";
import Pagination from "@/Components/Dashboard/Pagination";
import {
    IconArrowDownRight,
    IconArrowUpRight,
    IconCoin,
    IconDatabaseOff,
    IconPercentage,
    IconReceipt,
} from "@tabler/icons-react";

const defaultFilters = {
    start_date: "",
    end_date: "",
    invoice: "",
    cashier_id: "",
    customer_id: "",
};

const formatCurrency = (value = 0) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        minimumFractionDigits: 0,
    }).format(value);

const SummaryStat = ({ title, value, description, icon, accent }) => (
    <div className="rounded-lg border bg-white p-4 dark:border-gray-900 dark:bg-gray-950">
        <div className="flex items-center justify-between">
            <div>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {title}
                </p>
                <p className="text-2xl font-semibold text-gray-900 dark:text-white">
                    {value}
                </p>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                    {description}
                </p>
            </div>
            <div
                className={`rounded-full ${accent} p-3 text-indigo-600 dark:text-indigo-200`}
            >
                {icon}
            </div>
        </div>
    </div>
);

const ProfitReport = ({
    transactions,
    summary,
    filters,
    cashiers,
    customers,
}) => {
    const [filterData, setFilterData] = useState({
        ...defaultFilters,
        ...filters,
    });

    const [selectedCashier, setSelectedCashier] = useState(null);
    const [selectedCustomer, setSelectedCustomer] = useState(null);

    useEffect(() => {
        setFilterData({ ...defaultFilters, ...filters });
        setSelectedCashier(
            cashiers.find((item) => String(item.id) === filters.cashier_id) ||
                null
        );
        setSelectedCustomer(
            customers.find((item) => String(item.id) === filters.customer_id) ||
                null
        );
    }, [filters, cashiers, customers]);

    const handleChange = (field, value) => {
        setFilterData((prev) => ({
            ...prev,
            [field]: value,
        }));
    };

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(route("reports.profits.index"), filterData, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const resetFilters = () => {
        setFilterData(defaultFilters);
        setSelectedCashier(null);
        setSelectedCustomer(null);

        router.get(route("reports.profits.index"), defaultFilters, {
            replace: true,
            preserveScroll: true,
        });
    };

    const rows = transactions?.data ?? [];
    const links = transactions?.links ?? [];
    const currentPage = transactions?.current_page ?? 1;
    const perPage = transactions?.per_page
        ? Number(transactions?.per_page)
        : rows.length || 1;

    const stats = {
        profit_total: summary?.profit_total ?? 0,
        revenue_total: summary?.revenue_total ?? 0,
        orders_count: summary?.orders_count ?? 0,
        items_sold: summary?.items_sold ?? 0,
        average_profit: summary?.average_profit ?? 0,
        margin: summary?.margin ?? 0,
        best_invoice: summary?.best_invoice ?? "-",
        best_profit: summary?.best_profit ?? 0,
    };

    return (
        <>
            <Head title="Laporan Keuntungan" />

            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <SummaryStat
                        title="Total Profit"
                        value={formatCurrency(stats.profit_total)}
                        description="Akumulasi bersih"
                        icon={<IconCoin size={22} />}
                        accent="bg-emerald-50 dark:bg-emerald-900/40"
                    />
                    <SummaryStat
                        title="Rata-rata Profit"
                        value={formatCurrency(stats.average_profit)}
                        description={`${stats.orders_count} transaksi`}
                        icon={<IconArrowUpRight size={22} />}
                        accent="bg-indigo-50 dark:bg-indigo-900/40"
                    />
                    <SummaryStat
                        title="Margin Kotor"
                        value={`${stats.margin}%`}
                        description="Profit vs penjualan"
                        icon={<IconPercentage size={22} />}
                        accent="bg-amber-50 dark:bg-amber-900/40"
                    />
                    <SummaryStat
                        title="Transaksi Terbaik"
                        value={stats.best_invoice}
                        description={formatCurrency(stats.best_profit)}
                        icon={<IconReceipt size={22} />}
                        accent="bg-rose-50 dark:bg-rose-900/40"
                    />
                </div>

                <Card
                    title="Filter Data"
                    form={applyFilters}
                    footer={
                        <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                label="Reset"
                                className="border bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900"
                                onClick={resetFilters}
                            />
                            <Button
                                type="submit"
                                label="Terapkan Filter"
                                className="border bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900"
                                icon={<IconArrowDownRight size={18} />}
                            />
                        </div>
                    }
                >
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Input
                            type="date"
                            label="Mulai"
                            value={filterData.start_date}
                            onChange={(event) =>
                                handleChange("start_date", event.target.value)
                            }
                        />
                        <Input
                            type="date"
                            label="Selesai"
                            value={filterData.end_date}
                            onChange={(event) =>
                                handleChange("end_date", event.target.value)
                            }
                        />
                        <Input
                            type="text"
                            label="Nomor Invoice"
                            placeholder="Cari invoice"
                            value={filterData.invoice}
                            onChange={(event) =>
                                handleChange("invoice", event.target.value)
                            }
                        />
                        <InputSelect
                            label="Kasir"
                            data={cashiers}
                            selected={selectedCashier}
                            setSelected={(value) => {
                                setSelectedCashier(value);
                                handleChange(
                                    "cashier_id",
                                    value ? String(value.id) : ""
                                );
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
                                handleChange(
                                    "customer_id",
                                    value ? String(value.id) : ""
                                );
                            }}
                            placeholder="Semua pelanggan"
                            searchable
                        />
                    </div>
                </Card>

                <Table.Card title="Detail Keuntungan">
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th className="w-16 text-center">
                                    No
                                </Table.Th>
                                <Table.Th>Invoice</Table.Th>
                                <Table.Th>Tanggal</Table.Th>
                                <Table.Th>Kasir</Table.Th>
                                <Table.Th>Pelanggan</Table.Th>
                                <Table.Th className="text-center">
                                    Item
                                </Table.Th>
                                <Table.Th className="text-right">
                                    Penjualan
                                </Table.Th>
                                <Table.Th className="text-right">
                                    Profit
                                </Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {rows.length ? (
                                rows.map((transaction, index) => (
                                    <tr key={transaction.id}>
                                        <Table.Td className="text-center">
                                            {index +
                                                1 +
                                                (currentPage - 1) * perPage}
                                        </Table.Td>
                                        <Table.Td className="font-semibold text-gray-900 dark:text-gray-100">
                                            {transaction.invoice}
                                        </Table.Td>
                                        <Table.Td>
                                            {transaction.created_at}
                                        </Table.Td>
                                        <Table.Td>
                                            {transaction.cashier?.name ?? "-"}
                                        </Table.Td>
                                        <Table.Td>
                                            {transaction.customer?.name ?? "-"}
                                        </Table.Td>
                                        <Table.Td className="text-center">
                                            {transaction.total_items ?? 0}
                                        </Table.Td>
                                        <Table.Td className="text-right">
                                            {formatCurrency(
                                                transaction.grand_total ?? 0
                                            )}
                                        </Table.Td>
                                        <Table.Td className="text-right text-emerald-500 font-semibold">
                                            {formatCurrency(
                                                transaction.total_profit ?? 0
                                            )}
                                        </Table.Td>
                                    </tr>
                                ))
                            ) : (
                                <Table.Empty
                                    colSpan={8}
                                    message={
                                        <div className="text-gray-500">
                                            <IconDatabaseOff
                                                size={30}
                                                className="mx-auto mb-2 text-gray-400"
                                            />
                                            Tidak ada transaksi sesuai filter.
                                        </div>
                                    }
                                />
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                {links.length > 0 && <Pagination links={links} />}
            </div>
        </>
    );
};

ProfitReport.layout = (page) => <DashboardLayout children={page} />;

export default ProfitReport;

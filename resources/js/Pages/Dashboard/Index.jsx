import Card from "@/Components/Dashboard/Card";
import Table from "@/Components/Dashboard/Table";
import Widget from "@/Components/Dashboard/Widget";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head } from "@inertiajs/react";
import {
    IconBox,
    IconCategory,
    IconMoneybag,
    IconUsers,
} from "@tabler/icons-react";

export default function Dashboard({
    totalCategories,
    totalProducts,
    totalTransactions,
    totalUsers,
}) {
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
        </>
    );
}

Dashboard.layout = (page) => <DashboardLayout children={page} />;

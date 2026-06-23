import React, { useState } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, usePage, Link, router } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import {
    IconCirclePlus,
    IconDatabaseOff,
    IconPencilCog,
    IconTrash,
    IconLayoutGrid,
    IconList,
    IconUser,
    IconPhone,
    IconMapPin,
    IconUpload,
    IconDownload,
} from "@tabler/icons-react";
import Search from "@/Components/Dashboard/Search";
import Table from "@/Components/Dashboard/Table";
import Pagination from "@/Components/Dashboard/Pagination";
import { useAuthorization } from "@/Utils/authorization";
import toast from "react-hot-toast";

// Customer Card for Grid View
function CustomerCard({ customer, canUpdate, canDelete }) {
    return (
        <div className="group bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5 hover:shadow-lg hover:border-slate-300 dark:hover:border-slate-700 transition-all duration-200">
            {/* Avatar & Name */}
            <div className="flex items-start justify-between mb-4">
                <div className="flex items-center gap-3">
                    {customer.avatar ? (
                        <img
                            src={customer.avatar}
                            alt={customer.name}
                            className="w-12 h-12 rounded-full object-cover border border-slate-200 dark:border-slate-700 flex-shrink-0"
                        />
                    ) : (
                        <div className="w-12 h-12 rounded-full bg-gradient-to-br from-accent-400 to-accent-600 flex items-center justify-center text-white text-lg font-semibold flex-shrink-0">
                            {customer.name.charAt(0).toUpperCase()}
                        </div>
                    )}
                    <div>
                        <h3 className="text-base font-semibold text-slate-800 dark:text-slate-200">
                            <Link
                                href={route("customers.show", customer.id)}
                                className="hover:text-primary-600"
                            >
                                {customer.name}
                            </Link>
                        </h3>
                        <div className="mt-1 flex flex-wrap gap-1">
                            <span className="inline-flex rounded-full bg-primary-100 px-2 py-0.5 text-[11px] font-semibold text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">
                                {customer.is_loyalty_member
                                    ? customer.loyalty_tier
                                    : "non-member"}
                            </span>
                            <span className="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {customer.loyalty_points || 0} poin
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Contact Info */}
            <div className="space-y-2 mb-4">
                {customer.no_telp && (
                    <div className="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                        <IconPhone size={16} />
                        <span>{customer.no_telp}</span>
                    </div>
                )}
                {customer.address && (
                    <div className="flex items-start gap-2 text-sm text-slate-500 dark:text-slate-400">
                        <IconMapPin
                            size={16}
                            className="flex-shrink-0 mt-0.5"
                        />
                        <span className="line-clamp-2">{customer.address}</span>
                    </div>
                )}
            </div>

            {/* Actions */}
            {(canUpdate || canDelete) && (
                <div className="flex gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    {canUpdate && (
                        <Link
                            href={route("customers.edit", customer.id)}
                            className="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg bg-warning-100 text-warning-600 hover:bg-warning-200 dark:bg-warning-900/50 dark:text-warning-400 text-sm font-medium transition-colors"
                        >
                            <IconPencilCog size={16} />
                            <span>Edit</span>
                        </Link>
                    )}
                    {canDelete && (
                        <Button
                            type={"delete"}
                            icon={<IconTrash size={16} />}
                            className={
                                "flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg bg-danger-100 text-danger-600 hover:bg-danger-200 dark:bg-danger-900/50 dark:text-danger-400 text-sm font-medium"
                            }
                            url={route("customers.destroy", customer.id)}
                            label="Hapus"
                        />
                    )}
                </div>
            )}
        </div>
    );
}

export default function Index({ customers }) {
    const { can } = useAuthorization();
    const [viewMode, setViewMode] = useState("grid");
    const canCreateCustomers = can("customers-create");
    const canEditCustomers = can("customers-edit");
    const canDeleteCustomers = can("customers-delete");

    return (
        <>
            <Head title="Pelanggan" />

            {/* Header */}
            <div className="mb-6">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
                            Pelanggan
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            {customers.total || customers.data?.length || 0}{" "}
                            pelanggan terdaftar
                        </p>
                    </div>
                    {canCreateCustomers && (
                        <div className="flex items-center gap-2">
                            <a
                                href={route("export.customers")}
                                className="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            >
                                <IconDownload size={18} />
                                Export
                            </a>
                            <button
                                type="button"
                                onClick={() => document.getElementById("import-customers-input")?.click()}
                                className="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            >
                                <IconUpload size={18} />
                                Import
                            </button>
                            <input id="import-customers-input" type="file" accept=".xlsx,.xls,.csv" className="hidden"
                                onChange={function(e) {
                                    const file = e.target.files && e.target.files[0];
                                    if (file) router.post(route("import.customers"), { file });
                                }}
                            />
                            <Button
                            type={"link"}
                            icon={
                                <IconCirclePlus
                                    size={18}
                                    strokeWidth={1.5}
                                />
                            }
                            className={
                                "bg-primary-500 hover:bg-primary-600 text-white shadow-lg shadow-primary-500/30"
                            }
                            label={"Tambah Pelanggan"}
                            href={route("customers.create")}
                        />
                        </div>
                    )}
                </div>
            </div>

            {/* Toolbar */}
            <div className="mb-4 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                <div className="w-full sm:w-80">
                    <Search
                        url={route("customers.index")}
                        placeholder="Cari pelanggan..."
                    />
                </div>
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => setViewMode("grid")}
                        className={`p-2.5 rounded-lg transition-colors ${
                            viewMode === "grid"
                                ? "bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400"
                                : "text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                        }`}
                        title="Grid View"
                    >
                        <IconLayoutGrid size={20} />
                    </button>
                    <button
                        onClick={() => setViewMode("list")}
                        className={`p-2.5 rounded-lg transition-colors ${
                            viewMode === "list"
                                ? "bg-primary-100 text-primary-600 dark:bg-primary-900/50 dark:text-primary-400"
                                : "text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                        }`}
                        title="List View"
                    >
                        <IconList size={20} />
                    </button>
                </div>
            </div>

            {/* Content */}
            {customers.data.length > 0 ? (
                viewMode === "grid" ? (
                    /* Grid View */
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        {customers.data.map((customer) => (
                            <CustomerCard
                                key={customer.id}
                                customer={customer}
                                canUpdate={canEditCustomers}
                                canDelete={canDeleteCustomers}
                            />
                        ))}
                    </div>
                ) : (
                    /* List View */
                    <Table.Card title={"Data Pelanggan"}>
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th className="w-10">No</Table.Th>
                                    <Table.Th>Pelanggan</Table.Th>
                                    <Table.Th>Loyalty</Table.Th>
                                    <Table.Th>No. Telepon</Table.Th>
                                    <Table.Th>Alamat</Table.Th>
                                    <Table.Th></Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {customers.data.map((customer, i) => (
                                    <tr
                                        className="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                        key={customer.id}
                                    >
                                        <Table.Td className="text-center">
                                            {++i +
                                                (customers.current_page - 1) *
                                                    customers.per_page}
                                        </Table.Td>
                                        <Table.Td>
                                            <div className="flex items-center gap-3">
                                                {customer.avatar ? (
                                                    <img
                                                        src={customer.avatar}
                                                        alt={customer.name}
                                                        className="w-10 h-10 rounded-full object-cover border border-slate-200 dark:border-slate-700 flex-shrink-0"
                                                    />
                                                ) : (
                                                    <div className="w-10 h-10 rounded-full bg-gradient-to-br from-accent-400 to-accent-600 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                                        {customer.name
                                                            .charAt(0)
                                                            .toUpperCase()}
                                                    </div>
                                                )}
                                                <p className="text-sm font-medium text-slate-800 dark:text-slate-200">
                                                    <Link
                                                        href={route(
                                                            "customers.show",
                                                            customer.id
                                                        )}
                                                        className="hover:text-primary-600"
                                                    >
                                                        {customer.name}
                                                    </Link>
                                                </p>
                                            </div>
                                        </Table.Td>
                                        <Table.Td>
                                            <div className="flex flex-col">
                                                <span className="text-xs font-semibold text-primary-600 dark:text-primary-300">
                                                    {customer.is_loyalty_member
                                                        ? customer.loyalty_tier
                                                        : "non-member"}
                                                </span>
                                                <span className="text-xs text-slate-500 dark:text-slate-400">
                                                    {customer.loyalty_points ||
                                                        0}{" "}
                                                    poin
                                                </span>
                                            </div>
                                        </Table.Td>
                                        <Table.Td>
                                            <span className="text-sm text-slate-600 dark:text-slate-400">
                                                {customer.no_telp || "-"}
                                            </span>
                                        </Table.Td>
                                        <Table.Td>
                                            <p className="text-sm text-slate-500 dark:text-slate-400 line-clamp-1">
                                                {customer.address || "-"}
                                            </p>
                                        </Table.Td>
                                        <Table.Td>
                                            <div className="flex gap-2">
                                                {canEditCustomers && (
                                                    <Button
                                                        type={"edit"}
                                                        icon={
                                                            <IconPencilCog
                                                                size={16}
                                                                strokeWidth={
                                                                    1.5
                                                                }
                                                            />
                                                        }
                                                        className={
                                                            "border bg-warning-100 border-warning-200 text-warning-600 hover:bg-warning-200 dark:bg-warning-900/50 dark:border-warning-800 dark:text-warning-400"
                                                        }
                                                        href={route(
                                                            "customers.edit",
                                                            customer.id
                                                        )}
                                                    />
                                                )}
                                                {canDeleteCustomers && (
                                                    <Button
                                                        type={"delete"}
                                                        icon={
                                                            <IconTrash
                                                                size={16}
                                                                strokeWidth={
                                                                    1.5
                                                                }
                                                            />
                                                        }
                                                        className={
                                                            "border bg-danger-100 border-danger-200 text-danger-600 hover:bg-danger-200 dark:bg-danger-900/50 dark:border-danger-800 dark:text-danger-400"
                                                        }
                                                        url={route(
                                                            "customers.destroy",
                                                            customer.id
                                                        )}
                                                    />
                                                )}
                                            </div>
                                        </Table.Td>
                                    </tr>
                                ))}
                            </Table.Tbody>
                        </Table>
                    </Table.Card>
                )
            ) : (
                /* Empty State */
                <div className="flex flex-col items-center justify-center py-16 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800">
                    <div className="w-16 h-16 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                        <IconDatabaseOff
                            size={32}
                            className="text-slate-400"
                            strokeWidth={1.5}
                        />
                    </div>
                    <h3 className="text-lg font-medium text-slate-800 dark:text-slate-200 mb-1">
                        Belum Ada Pelanggan
                    </h3>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
                        Tambahkan pelanggan pertama Anda.
                    </p>
                    <Button
                        type={"link"}
                        icon={<IconCirclePlus size={18} />}
                        className={
                            "bg-primary-500 hover:bg-primary-600 text-white"
                        }
                        label={"Tambah Pelanggan"}
                        href={route("customers.create")}
                    />
                </div>
            )}

            {customers.last_page !== 1 && (
                <Pagination links={customers.links} />
            )}
        </>
    );
}

Index.layout = (page) => <DashboardLayout children={page} />;

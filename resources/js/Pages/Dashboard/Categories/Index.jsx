import React, { useState } from "react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import { Head, usePage, Link } from "@inertiajs/react";
import Button from "@/Components/Dashboard/Button";
import {
    IconCirclePlus,
    IconDatabaseOff,
    IconPencilCog,
    IconTrash,
    IconLayoutGrid,
    IconList,
    IconCategory,
    IconPhoto,
} from "@tabler/icons-react";
import Search from "@/Components/Dashboard/Search";
import Table from "@/Components/Dashboard/Table";
import Pagination from "@/Components/Dashboard/Pagination";
import { useAuthorization } from "@/Utils/authorization";

// Category Card for Grid View
function CategoryCard({ category, canUpdate, canDelete }) {
    return (
        <div className="group bg-white dark:bg-canvas-night-elevated rounded-card border border-hairline-light dark:border-hairline-dark overflow-hidden shadow-paper hover:border-shade-30 dark:hover:border-slate-700 transition-all duration-200">
            {/* Category Image */}
            <div className="relative aspect-[3/2] bg-canvas-cream dark:bg-canvas-night overflow-hidden">
                {category.image ? (
                    <img
                        src={category.image}
                        alt={category.name}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        loading="lazy"
                    />
                ) : (
                    <div className="w-full h-full flex items-center justify-center">
                        <IconCategory
                            size={48}
                            className="text-slate-300 dark:text-slate-600"
                            strokeWidth={1}
                        />
                    </div>
                )}

                {/* Action Buttons Overlay */}
                {(canUpdate || canDelete) && (
                    <div className="absolute inset-0 bg-slate-900/0 group-hover:bg-slate-900/40 transition-all flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100">
                        {canUpdate && (
                            <Link
                                href={route("categories.edit", category.id)}
                                className="p-2.5 rounded-full bg-white text-warning-600 hover:bg-warning-50 shadow-lg transition-colors"
                            >
                                <IconPencilCog size={18} />
                            </Link>
                        )}
                        {canDelete && (
                            <Button
                                type={"delete"}
                                icon={<IconTrash size={18} />}
                                className={
                                    "p-2.5 rounded-full bg-white text-danger-600 hover:bg-danger-50 shadow-lg"
                                }
                                url={route("categories.destroy", category.id)}
                            />
                        )}
                    </div>
                )}
            </div>

            {/* Category Info */}
            <div className="p-4">
                <h3 className="text-base font-semibold text-ink dark:text-slate-200 mb-1">
                    {category.name}
                </h3>
                {category.description && (
                    <p className="text-sm text-slate-500 dark:text-slate-400 line-clamp-2">
                        {category.description}
                    </p>
                )}
            </div>
        </div>
    );
}

export default function Index({ categories }) {
    const { can } = useAuthorization();
    const [viewMode, setViewMode] = useState("grid");
    const canCreateCategories = can("categories-create");
    const canEditCategories = can("categories-edit");
    const canDeleteCategories = can("categories-delete");

    return (
        <>
            <Head title="Kategori" />

            {/* Header */}
            <div className="mb-6">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-ink dark:text-white">
                            Kategori
                        </h1>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            {categories.total || categories.data?.length || 0}{" "}
                            kategori terdaftar
                        </p>
                    </div>
                    {canCreateCategories && (
                        <Button
                            type={"link"}
                            icon={
                                <IconCirclePlus
                                    size={18}
                                    strokeWidth={1.5}
                                />
                            }
                            className={
                                "bg-ink hover:bg-shade-70 text-white"
                            }
                            label={"Tambah Kategori"}
                            href={route("categories.create")}
                        />
                    )}
                </div>
            </div>

            {/* Toolbar */}
            <div className="mb-4 flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                <div className="w-full sm:w-80">
                    <Search
                        url={route("categories.index")}
                        placeholder="Cari kategori..."
                    />
                </div>
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => setViewMode("grid")}
                        className={`p-2.5 rounded-lg transition-colors ${
                            viewMode === "grid"
                                ? "bg-aloe-100 text-ink dark:bg-primary-900/50 dark:text-primary-400"
                                : "text-slate-400 hover:bg-canvas-cream dark:hover:bg-slate-800"
                        }`}
                        title="Grid View"
                    >
                        <IconLayoutGrid size={20} />
                    </button>
                    <button
                        onClick={() => setViewMode("list")}
                        className={`p-2.5 rounded-lg transition-colors ${
                            viewMode === "list"
                                ? "bg-aloe-100 text-ink dark:bg-primary-900/50 dark:text-primary-400"
                                : "text-slate-400 hover:bg-canvas-cream dark:hover:bg-slate-800"
                        }`}
                        title="List View"
                    >
                        <IconList size={20} />
                    </button>
                </div>
            </div>

            {/* Content */}
            {categories.data.length > 0 ? (
                viewMode === "grid" ? (
                    /* Grid View */
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                        {categories.data.map((category) => (
                            <CategoryCard
                                key={category.id}
                                category={category}
                                canUpdate={canEditCategories}
                                canDelete={canDeleteCategories}
                            />
                        ))}
                    </div>
                ) : (
                    /* List View */
                    <Table.Card title={"Data Kategori"}>
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th className="w-10">No</Table.Th>
                                    <Table.Th>Kategori</Table.Th>
                                    <Table.Th>Deskripsi</Table.Th>
                                    <Table.Th></Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {categories.data.map((category, i) => (
                                    <tr
                                        className="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                        key={category.id}
                                    >
                                        <Table.Td className="text-center">
                                            {++i +
                                                (categories.current_page - 1) *
                                                    categories.per_page}
                                        </Table.Td>
                                        <Table.Td>
                                            <div className="flex items-center gap-3">
                                                <div className="w-12 h-12 rounded-xl bg-slate-100 dark:bg-slate-800 overflow-hidden flex-shrink-0">
                                                    {category.image ? (
                                                        <img
                                                            src={category.image}
                                                            alt={category.name}
                                                            className="w-full h-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="w-full h-full flex items-center justify-center">
                                                            <IconCategory
                                                                size={20}
                                                                className="text-slate-400"
                                                            />
                                                        </div>
                                                    )}
                                                </div>
                                                <p className="text-sm font-medium text-slate-800 dark:text-slate-200">
                                                    {category.name}
                                                </p>
                                            </div>
                                        </Table.Td>
                                        <Table.Td>
                                            <p className="text-sm text-slate-500 dark:text-slate-400 line-clamp-2">
                                                {category.description || "-"}
                                            </p>
                                        </Table.Td>
                                        <Table.Td>
                                            <div className="flex gap-2">
                                                {canEditCategories && (
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
                                                            "categories.edit",
                                                            category.id
                                                        )}
                                                    />
                                                )}
                                                {canDeleteCategories && (
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
                                                            "categories.destroy",
                                                            category.id
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
                <div className="flex flex-col items-center justify-center py-16 bg-white dark:bg-canvas-night-elevated rounded-card border border-hairline-light dark:border-hairline-dark shadow-paper">
                    <div className="w-16 h-16 rounded-full bg-aloe-100 dark:bg-hairline-dark flex items-center justify-center mb-4">
                        <IconDatabaseOff
                            size={32}
                            className="text-slate-400"
                            strokeWidth={1.5}
                        />
                    </div>
                    <h3 className="text-lg font-medium text-ink dark:text-slate-200 mb-1">
                        Belum Ada Kategori
                    </h3>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
                        Tambahkan kategori pertama Anda.
                    </p>
                    <Button
                        type={"link"}
                        icon={<IconCirclePlus size={18} />}
                        className={
                            "bg-ink hover:bg-shade-70 text-white"
                        }
                        label={"Tambah Kategori"}
                        href={route("categories.create")}
                    />
                </div>
            )}

            {categories.last_page !== 1 && (
                <Pagination links={categories.links} />
            )}
        </>
    );
}

Index.layout = (page) => <DashboardLayout children={page} />;

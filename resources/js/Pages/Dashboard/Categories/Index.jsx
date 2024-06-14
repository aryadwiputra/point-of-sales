import React from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import { Head, usePage } from '@inertiajs/react'
import Button from '@/Components/Dashboard/Button'
import { IconCirclePlus, IconDatabaseOff, IconPencilCog, IconTrash } from '@tabler/icons-react'
import Search from '@/Components/Dashboard/Search'
import Table from '@/Components/Dashboard/Table'
import Pagination from '@/Components/Dashboard/Pagination'

export default function Index({ categories }) {
    const { roles, permissions, errors, } = usePage().props;

    return (
        <>
            <Head title='Kategori' />
            <div className='mb-2'>
                <div className='flex justify-between items-center gap-2'>
                    <Button
                        type={'link'}
                        icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
                        className={'border bg-white text-gray-700 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200'}
                        label={'Tambah Data Kategori'}
                        href={route('categories.create')}
                    />
                    <div className='w-full md:w-4/12'>
                        <Search
                            url={route('categories.index')}
                            placeholder='Cari data berdasarkan nama kategori...'
                        />
                    </div>
                </div>
            </div>
            <Table.Card title={'Data Kategori'}>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th className='w-10'>No</Table.Th>
                            <Table.Th className='w-40'>Nama</Table.Th>
                            <Table.Th className='w-40'>Gambar</Table.Th>
                            <Table.Th></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {categories.data.length ?
                            categories.data.map((category, i) => (
                                <tr className='hover:bg-gray-100 dark:hover:bg-gray-900' key={i}>
                                    <Table.Td className='text-center'>
                                        {++i + (categories.current_page - 1) * categories.per_page}
                                    </Table.Td>
                                    <Table.Td>{category.name}</Table.Td>
                                    <Table.Td>
                                        <img
                                            src={category.image}
                                            alt={category.name}
                                            className='w-24 h-24 object-cover'
                                        />
                                    </Table.Td>
                                    <Table.Td>
                                    </Table.Td>
                                </tr>
                            )) :
                            <Table.Empty colSpan={4} message={
                                <>
                                    <div className='flex justify-center items-center text-center mb-2'>
                                        <IconDatabaseOff size={24} strokeWidth={1.5} className='text-gray-500 dark:text-white' />
                                    </div>
                                    <span className='text-gray-500'>Data kategori</span> <span className='text-rose-500 underline underline-offset-2'>tidak ditemukan.</span>
                                </>
                            } />
                        }
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {categories.last_page !== 1 && (<Pagination links={categories.links} />)}
        </>
    )
}

Index.layout = page => <DashboardLayout children={page} />

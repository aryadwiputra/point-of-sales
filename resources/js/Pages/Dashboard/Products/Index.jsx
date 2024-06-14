import React from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import { Head, usePage } from '@inertiajs/react'
import Button from '@/Components/Dashboard/Button'
import { IconCirclePlus, IconDatabaseOff, IconPencilCog, IconTrash } from '@tabler/icons-react'
import Search from '@/Components/Dashboard/Search'
import Table from '@/Components/Dashboard/Table'
import Pagination from '@/Components/Dashboard/Pagination'
import Barcode from '@/Components/Dashboard/Barcode'

export default function Index({ products }) {
    const { roles, permissions, errors, } = usePage().props;

    return (
        <>
            <Head title='Produk' />
            <div className='mb-2'>
                <div className='flex justify-between items-center gap-2'>
                    <Button
                        type={'link'}
                        icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
                        className={'border bg-white text-gray-700 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200'}
                        label={'Tambah Data Produk'}
                        href={route('products.create')}
                    />
                    <div className='w-full md:w-4/12'>
                        <Search
                            url={route('products.index')}
                            placeholder='Cari data berdasarkan nama produk...'
                        />
                    </div>
                </div>
            </div>
            <Table.Card title={'Data Produk'}>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th className='w-10'>No</Table.Th>
                            <Table.Th >Kode</Table.Th>
                            <Table.Th >Kategori</Table.Th>
                            <Table.Th>Nama</Table.Th>
                            <Table.Th>Harga Beli</Table.Th>
                            <Table.Th>Harga Jual</Table.Th>
                            <Table.Th>Stok</Table.Th>
                            <Table.Th></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {products.data.length ?
                            products.data.map((product, i) => (
                                <tr className='hover:bg-gray-100 dark:hover:bg-gray-900' key={i}>
                                    <Table.Td className='text-center'>
                                        {++i + (products.current_page - 1) * products.per_page}
                                    </Table.Td>
                                    <Table.Td>
                                        <Barcode
                                            value={product.barcode}
                                            format={'CODE39'}
                                            width={2}
                                            height={20}
                                            lineColor={'#000'}
                                        />
                                    </Table.Td>
                                    <Table.Td>{product.category.name}</Table.Td>
                                    <Table.Td>{product.description}</Table.Td>
                                    <Table.Td>{product.buy_price}</Table.Td>
                                    <Table.Td>{product.sell_price}</Table.Td>
                                    <Table.Td>{product.stock}</Table.Td>
                                    <Table.Td>
                                        <div className='flex gap-2'>
                                            <Button
                                                type={'edit'}
                                                icon={<IconPencilCog size={16} strokeWidth={1.5} />}
                                                className={'border bg-orange-100 border-orange-300 text-orange-500 hover:bg-orange-200 dark:bg-orange-950 dark:border-orange-800 dark:text-gray-300  dark:hover:bg-orange-900'}
                                                href={route('products.edit', product.id)}
                                            />
                                            <Button
                                                type={'delete'}
                                                icon={<IconTrash size={16} strokeWidth={1.5} />}
                                                className={'border bg-rose-100 border-rose-300 text-rose-500 hover:bg-rose-200 dark:bg-rose-950 dark:border-rose-800 dark:text-gray-300  dark:hover:bg-rose-900'}
                                                url={route('products.destroy', product.id)}
                                            />
                                        </div>
                                    </Table.Td>
                                </tr>
                            )) :
                            <Table.Empty colSpan={7} message={
                                <>
                                    <div className='flex justify-center items-center text-center mb-2'>
                                        <IconDatabaseOff size={24} strokeWidth={1.5} className='text-gray-500 dark:text-white' />
                                    </div>
                                    <span className='text-gray-500'>Data Produk</span> <span className='text-rose-500 underline underline-offset-2'>tidak ditemukan.</span>
                                </>
                            } />
                        }
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {products.last_page !== 1 && (<Pagination links={products.links} />)}
        </>
    )
}

Index.layout = page => <DashboardLayout children={page} />

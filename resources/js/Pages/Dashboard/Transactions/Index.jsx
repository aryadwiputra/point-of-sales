import React, { useState, useEffect } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import Card from '@/Components/Dashboard/Card';
import Input from '@/Components/Dashboard/Input';
import { IconArrowRight, IconMoneybag, IconPencilPlus, IconReceipt, IconShoppingCart, IconShoppingCartPlus, IconTrash } from '@tabler/icons-react';
import Button from '@/Components/Dashboard/Button';
import axios from 'axios';
import InputSelect from '@/Components/Dashboard/InputSelect';
import Table from '@/Components/Dashboard/Table';
import toast from 'react-hot-toast';

export default function Index({ carts, carts_total, customers }) {
    const { errors, auth } = usePage().props;

    const [barcode, setBarcode] = useState('');
    const [product, setProduct] = useState({});
    const [qty, setQty] = useState(1);
    const [grandTotal, setGrandTotal] = useState(carts_total);
    const [cash, setCash] = useState(0);
    const [change, setChange] = useState(0);
    const [discount, setDiscount] = useState(0);
    const [selectedCustomer, setSelectedCustomer] = useState(null);

    // Helper function to format price
    const formatPrice = (price) => {
        return price.toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });
    };

    const { data, setData, post, processing } = useForm({
        customer_id: '',
        product_id: '',
        sell_price: '',
        qty: '',
        discount: '',
        cash: '',
        change: '',
    });

    // Set selected customer
    const setSelectedCustomerHandler = (value) => {
        setSelectedCustomer(value);
        setData('customer_id', value.id);
    };

    useEffect(() => {
        setGrandTotal(carts_total - discount);
        setChange(cash - (carts_total - discount));
    }, [carts_total, discount, cash]);

    const searchProduct = (e) => {
        e.preventDefault();
        axios.post('/dashboard/transactions/searchProduct', { barcode })
            .then(response => {
                if (response.data.success) {
                    setProduct(response.data.data);
                } else {
                    setProduct({});
                }
            });
    };

    const addToCart = (e) => {
        e.preventDefault();
        router.post(route('transactions.addToCart'), {
            product_id: product.id,
            sell_price: product.sell_price,
            qty,
        });
    };

    const storeTransaction = (e) => {
        e.preventDefault();
        if (!data.customer_id) {
            toast('Pilih pelanggan terlebih dahulu', {
                style: {
                    borderRadius: '10px',
                    background: '#FF0000',
                    color: '#fff',
                },
            });
        } else {
            if (cash >= grandTotal) {
                router.post(route('transactions.store'), {
                    customer_id: selectedCustomer ? selectedCustomer.id : '',
                    discount,
                    grand_total: grandTotal,
                    cash,
                    change,
                }, {
                    onSuccess: () => {
                        toast('Data transaksi berhasil disimpan', {
                            icon: '👏',
                            style: {
                                borderRadius: '10px',
                                background: '#1C1F29',
                                color: '#fff',
                            },
                        })
                    }
                });
            }
        }
    };

    return (
        <>
            <Head title="Dashboard Transaksi" />
            <div className="grid grid-cols-12 gap-4">
                <div className="col-span-12 md:col-span-4">
                    <Card
                        title={'Tambah Data Produk'}
                        icon={<IconShoppingCart size={20} strokeWidth={1.5} />}
                        footer={
                            <Button
                                type={'submit'}
                                label={'Tambah'}
                                icon={<IconShoppingCartPlus size={20} strokeWidth={1.5} />}
                                disabled={!product.id}

                                className={`border bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900 mt-5 ${!product.id ? 'opacity-50 cursor-not-allowed' : ''}`}
                            />
                        }
                        form={addToCart}
                    >
                        <div className="mb-2">
                            <Input
                                type={'text'}
                                label={'Scan/Input Barcode Produk'}
                                placeholder={'Barcode Produk'}
                                onChange={e => setBarcode(e.target.value)}
                                onKeyUp={searchProduct}
                            />
                        </div>
                        <div className="mb-2">
                            <Input
                                type={'text'}
                                label={'Produk'}
                                placeholder={'Nama produk'}
                                disabled
                                value={product.title || ''}
                            />
                        </div>
                        <div className="mb-2">
                            <Input
                                type={'number'}
                                label={'Kuantitas'}
                                placeholder={'Kuantitas'}
                                onChange={e => setQty(e.target.value)}
                            />
                            <small className="text-gray-500">
                                Stok : {product.stock || 0}
                            </small>
                        </div>
                    </Card>
                </div>
                <div className="col-span-12 md:col-span-8">
                    <Card
                        title={'Transaksi'}
                        icon={<IconPencilPlus size={20} strokeWidth={1.5} />}
                    >
                        <div className="grid grid-cols-12 gap-4">
                            <div className="col-span-12">
                                <div className="flex justify-between">
                                    <h1 className="text-lg md:text-2xl text-black dark:text-white">Total Belanja</h1>
                                    <h1 className="text-lg md:text-2xl text-black dark:text-white">
                                        {formatPrice(carts_total)}
                                    </h1>
                                </div>
                            </div>
                            <div className="col-span-12 md:col-span-6">
                                <Input
                                    type={'text'}
                                    label={'Kasir'}
                                    placeholder={'Kasir'}
                                    disabled
                                    value={auth.user.name}
                                />
                            </div>
                            <div className="col-span-12 md:col-span-6">
                                <InputSelect
                                    label="Pelanggan"
                                    data={customers}
                                    selected={selectedCustomer}
                                    setSelected={setSelectedCustomerHandler}
                                    placeholder="Pelanggan"
                                    errors={errors.customer_id}
                                    multiple={false}
                                    searchable={true}
                                    displayKey='name'
                                />
                            </div>
                        </div>
                    </Card>

                    <Table.Card title={'Keranjang'} className={'mt-5'}>
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th className='w-10'>No</Table.Th>
                                    <Table.Th>Produk</Table.Th>
                                    <Table.Th>Harga</Table.Th>
                                    <Table.Th>Qty</Table.Th>
                                    <Table.Th>Sub Total</Table.Th>
                                    <Table.Th></Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {carts.map((item, index) => (
                                    <tr key={item.id}>
                                        <Table.Td className='w-10'>{index + 1}</Table.Td>
                                        <Table.Td>{item.product.title}</Table.Td>
                                        <Table.Td>{formatPrice(item.price)}</Table.Td>
                                        <Table.Td>{item.qty}</Table.Td>
                                        <Table.Td>{formatPrice(item.price * item.qty)}</Table.Td>
                                        <Table.Td>
                                            <Button
                                                type={'delete'}
                                                icon={<IconTrash size={16} strokeWidth={1.5} />}
                                                className={'border bg-rose-100 border-rose-300 text-rose-500 hover:bg-rose-200 dark:bg-rose-950 dark:border-rose-800 dark:text-gray-300  dark:hover:bg-rose-900'}
                                                url={route('transactions.destroyCart', item.id)}
                                            />
                                        </Table.Td>
                                    </tr>
                                ))}
                            </Table.Tbody>
                            <tfoot>
                                <tr>
                                    <Table.Td></Table.Td>
                                    <Table.Td></Table.Td>
                                    <Table.Td></Table.Td>
                                    <Table.Td>Total</Table.Td>
                                    <Table.Td>{formatPrice(carts_total)}</Table.Td>
                                    <Table.Td></Table.Td>
                                </tr>
                            </tfoot>
                        </Table>
                    </Table.Card>

                    <div className="my-5"></div>

                    <Card
                        title={'Pembayaran'}
                        icon={<IconReceipt size={20} strokeWidth={1.5} />}
                    >
                        <div className="grid grid-cols-12 gap-4">
                            <div className="col-span-12 md:col-span-6">
                                <Input
                                    type={'number'}
                                    label={'Diskon'}
                                    placeholder="Discount (Rp.)"
                                    onChange={e => setDiscount(parseInt(e.target.value) || 0)}
                                />
                            </div>
                            <div className="col-span-12 md:col-span-6">
                                <Input
                                    type={'number'}
                                    label={'Bayar'}
                                    placeholder="Pay (Rp.)"
                                    onChange={e => setCash(parseInt(e.target.value) || 0)}
                                />
                            </div>
                        </div>
                        <Button
                            type={'submit'}
                            label={'Bayar'}
                            icon={<IconMoneybag size={20} strokeWidth={1.5} />}
                            onClick={storeTransaction}
                            disabled={cash < grandTotal}
                            className={`border bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900 mt-5 ${cash < grandTotal ? 'opacity-50 cursor-not-allowed' : ''}`}
                        >
                            Bayar
                        </Button>
                    </Card>
                </div>
            </div >
        </>
    );
}

Index.layout = page => <DashboardLayout children={page} />;

import React, { useEffect } from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import { Head } from '@inertiajs/react';

export default function Print({ transaction, store }) {
    const formatPrice = (price) => {
        return price.toLocaleString('id-ID', { style: 'currency', currency: 'IDR' });
    };

    useEffect(() => {
        window.print();
    }, []);

    console.log(transaction)

    return (
        <>
            <Head title="Print Invoice" />
            <div className="max-w-2xl mx-auto p-4 bg-white dark:bg-black">
                <div className="text-center mb-8">
                    {/* <h1 className="text-2xl font-bold">{store.name}</h1>
                    <p>{store.address}</p>
                    <p>{store.phone}</p> */}
                </div>
                <div className="flex justify-between mb-6 bg-white text-black dark:bg-black dark:text-white">
                    <div>
                        <h2 className="text-lg font-semibold">Invoice</h2>
                        <p>No: {transaction.invoice}</p>
                        <p>Date: {new Date(transaction.created_at).toLocaleDateString()}</p>
                    </div>
                    <div className="text-right">
                        <h2 className="text-lg font-semibold">Customer</h2>
                        <p>{transaction.customer.name}</p>
                        <p>{transaction.customer.address}</p>
                    </div>
                </div>
                <div className="mb-6 bg-white text-black dark:bg-black dark:text-white">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr>
                                <th className="border-b py-2">No</th>
                                <th className="border-b py-2">Product</th>
                                <th className="border-b py-2">Price</th>
                                <th className="border-b py-2">Qty</th>
                                <th className="border-b py-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transaction.details.map((item, index) => (
                                <tr key={index}>
                                    <td className="border-b py-2">{index + 1}</td>
                                    <td className="border-b py-2">{item.product.title}</td>
                                    <td className="border-b py-2">{formatPrice(item.price)}</td>
                                    <td className="border-b py-2">{item.qty}</td>
                                    <td className="border-b py-2">{formatPrice(item.price * item.qty)}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colSpan="4" className="text-right py-2">Discount</td>
                                <td className="py-2">{formatPrice(transaction.discount)}</td>
                            </tr>
                            <tr>
                                <td colSpan="4" className="text-right py-2 font-semibold">Total</td>
                                <td className="py-2 font-semibold">{formatPrice(transaction.grand_total)}</td>
                            </tr>
                            <tr>
                                <td colSpan="4" className="text-right py-2">Cash</td>
                                <td className="py-2">{formatPrice(transaction.cash)}</td>
                            </tr>
                            <tr>
                                <td colSpan="4" className="text-right py-2">Change</td>
                                <td className="py-2">{formatPrice(transaction.change)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div className="text-center mt-8 text-black dark:text-white">
                    <p className="text-sm">Thank you for your purchase!</p>
                    <p className="text-sm">Please come again.</p>
                </div>
            </div>
        </>
    );
}

// Print.layout = page => <DashboardLayout children={page} />

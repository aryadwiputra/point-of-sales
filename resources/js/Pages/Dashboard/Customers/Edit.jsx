import React from 'react'
import DashboardLayout from '@/Layouts/DashboardLayout'
import { Head, useForm, usePage } from '@inertiajs/react'
import Card from '@/Components/Dashboard/Card'
import Button from '@/Components/Dashboard/Button'
import { IconPencilPlus, IconUsersPlus } from '@tabler/icons-react'
import Input from '@/Components/Dashboard/Input'
import Textarea from '@/Components/Dashboard/TextArea'
import toast from 'react-hot-toast'

export default function Create({ customer }) {

    const { errors } = usePage().props

    const { data, setData, post, processing } = useForm({
        id: customer.id,
        name: customer.name,
        no_telp: customer.no_telp,
        address: customer.address,
        _method: 'PUT',
    })

    const submit = (e) => {
        e.preventDefault()
        post(route('customers.update', customer.id), {
            onSuccess: () => {
                if (Object.keys(errors).length === 0) {
                    toast('Data berhasil diubah', {
                        icon: '👏',
                        style: {
                            borderRadius: '10px',
                            background: '#1C1F29',
                            color: '#fff',
                        },
                    })
                }
            },
            onError: () => {
                toast('Terjadi kesalahan dalam penyimpanan data', {
                    style: {
                        borderRadius: '10px',
                        background: '#FF0000',
                        color: '#fff',
                    },
                })
            },
        })
    }

    return (
        <>
            <Head title='Tambah Data Pelanggan' />
            <Card
                title={'Edit Pelanggan'}
                icon={<IconUsersPlus size={20} strokeWidth={1.5} />}
                footer={
                    <Button
                        type={'submit'}
                        label={'Simpan'}
                        icon={<IconPencilPlus size={20} strokeWidth={1.5} />}
                        className={'border bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-950 dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-900'}
                    />
                }
                form={submit}
            >
                <div className='grid grid-cols-12 gap-4'>
                    <div className='col-span-6'>
                        <Input
                            name='name'
                            label={'Name'}
                            type={'text'}
                            placeholder={'Nama pelanggan'}
                            errors={errors.name}
                            onChange={e => setData('name', e.target.value)}
                            value={data.name}
                        />
                    </div>
                    <div className="col-span-6">
                        <Input
                            name='no_telp'
                            label={'No. Handphone'}
                            type={'text'}
                            placeholder={'No. Handphone pelanggan'}
                            errors={errors.no_telp}
                            onChange={e => setData('no_telp', e.target.value)}
                            value={data.no_telp}
                        />
                    </div>
                    <div className='col-span-12'>
                        <Textarea
                            name='address'
                            label={'Address'}
                            errors={errors.address}
                            onChange={e => setData('address', e.target.value)}
                            value={data.address}
                        />
                    </div>
                </div>
            </Card>
        </>
    )
}

Create.layout = page => <DashboardLayout children={page} />

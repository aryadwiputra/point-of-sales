import { useForm } from '@inertiajs/react';
import { IconSearch } from '@tabler/icons-react';
import React from 'react'
export default function Search({ url, placeholder }) {

    // define use form inertia
    const { data, setData, get } = useForm({
        search: '',
    })

    // define method searchData
    const searchData = (e) => {
        e.preventDefault();

        get(`${url}?search=${data.search}`)
    }

    return (
        <form onSubmit={searchData}>
            <div className='relative'>
                <input
                    type='text'
                    value={data.search}
                    onChange={e => setData('search', e.target.value)}
                    className='min-h-touch px-4 pr-11 block w-full rounded-md text-sm border focus:outline-none focus:ring-4 focus:ring-aloe-100/70 text-ink bg-white border-hairline-light focus:border-ink dark:focus:ring-hairline-dark dark:focus:border-gray-500 dark:text-gray-200 dark:bg-canvas-night-elevated dark:border-hairline-dark'
                    placeholder={placeholder} />
                <div className='absolute inset-y-0 right-0 flex items-center pointer-events-none pr-4'>
                    <IconSearch className='text-shade-40 w-5 h-5' />
                </div>
            </div>
        </form>
    )
}

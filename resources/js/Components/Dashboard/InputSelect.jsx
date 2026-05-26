import React, { useState } from 'react'
import { Listbox } from '@headlessui/react'
import { IconChevronDown, IconCircle, IconCircleFilled } from '@tabler/icons-react'

export default function InputSelect({ selected, data, setSelected, label, errors, placeholder, multiple = false, searchable = false, displayKey = 'name' }) {
    const [search, setSearch] = useState('')

    const filteredData = data.filter(item =>
        item[displayKey]?.toLowerCase().includes(search.toLowerCase())
    )

    return (
        <div className='flex flex-col gap-2'>
            <label className='text-shade-70 dark:text-gray-300 text-sm'>{label}</label>
            <Listbox value={selected} onChange={setSelected} multiple={multiple} by="id">
                <Listbox.Button className={'w-full min-h-touch px-4 border text-sm rounded-md focus:outline-none focus:ring-4 focus:ring-aloe-100/70 flex justify-between items-center gap-8 bg-white text-ink focus:border-ink border-hairline-light dark:bg-canvas-night-elevated dark:text-gray-300 dark:focus:border-gray-500 dark:border-hairline-dark'}>
                    {multiple ? (
                        selected.length > 0 ? selected.map(item => item[displayKey]).join(', ') : placeholder
                    ) : (
                        selected ? selected[displayKey] : placeholder
                    )}
                    <IconChevronDown size={20} strokeWidth={1.5} />
                </Listbox.Button>
                <Listbox.Options className={'p-3 border rounded-card flex flex-col gap-2 bg-white shadow-paper border-hairline-light dark:border-hairline-dark dark:bg-canvas-night-elevated'}>
                    {searchable && (
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search..."
                            className="w-full min-h-touch px-3 mb-2 text-sm border rounded-md bg-white text-ink border-hairline-light focus:outline-none focus:ring-4 focus:ring-aloe-100/70 focus:border-ink dark:bg-canvas-night dark:text-gray-300 dark:border-hairline-dark dark:focus:border-gray-500"
                        />
                    )}
                    {filteredData.map((item) => (
                        <Listbox.Option key={item.id} value={item}>
                            {({ selected }) => (
                                <div
                                    className='text-sm cursor-pointer px-3 py-2 rounded-full flex items-center gap-2 bg-white text-shade-70 hover:bg-aloe-100 border border-hairline-light dark:bg-canvas-night dark:border-hairline-dark dark:text-gray-400 dark:hover:bg-hairline-dark '>
                                    {selected ? <IconCircleFilled size={15} strokeWidth={1.5} className='text-ink dark:text-white' /> : <IconCircle size={15} strokeWidth={1.5} />}
                                    {item[displayKey]}
                                </div>
                            )}
                        </Listbox.Option>
                    ))}
                </Listbox.Options>
            </Listbox>
            {errors && (
                <small className='text-xs text-red-500'>{errors}</small>
            )}
        </div>
    )
}

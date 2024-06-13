import React, { useState } from 'react'
import { Link, usePage } from '@inertiajs/react'
import { IconChevronDown, IconChevronUp, IconCornerDownRight } from '@tabler/icons-react'
export default function LinkItemDropdown({ icon, title, data, access, sidebarOpen, ...props }) {

    // destruct url from usepage
    const { url } = usePage();

    // define state
    const [isOpen, setIsOpen] = useState(false);

    // destruct auth from usepage props
    const { auth } = usePage().props;

    console.log(auth)

    return (
        <>
            {
                auth.super === true ?
                    sidebarOpen ?
                        <>
                            <button
                                className={`min-w-full flex items-center font-medium gap-x-3.5 px-4 py-3 hover:border-r-2 capitalize hover:cursor-pointer text-sm justify-between text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100`}
                                onClick={() => setIsOpen(!isOpen)}>
                                <div className='flex items-center gap-x-3.5'>{icon}{title}</div>
                                {isOpen ? (
                                    <IconChevronUp size={18} strokeWidth={1.5} />
                                ) : (
                                    <IconChevronDown size={18} strokeWidth={1.5} />
                                )}
                            </button>
                            {isOpen &&
                                data.map((data, i) => (
                                    data.permissions === true &&
                                    <Link
                                        key={i}
                                        href={data.href}
                                        className={`${url === data.href && 'border-r-2 border-r-gray-400 bg-gray-100 text-gray-700 dark:border-r-gray-500 dark:bg-gray-900 dark:text-white'} min-w-full flex items-center font-medium gap-x-3.5 px-5 py-3 hover:border-r-2 capitalize hover:cursor-pointer text-sm line-clamp-1 text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100`}
                                        {...props}>
                                        <IconCornerDownRight size={18} strokeWidth={1.5} /> {data.title}
                                    </Link>
                                ))
                            }
                        </>
                        :
                        <>
                            <button
                                className='min-w-full flex justify-center py-3 hover:border-r-2 item-navigation hover:cursor-pointer text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100'
                                onClick={() => setIsOpen(!isOpen)}>
                                {!isOpen ? icon : <IconChevronDown size={20} strokeWidth={1.5} />}
                            </button>
                            {isOpen &&
                                data.map((data, i) => (
                                    data.permissions === true &&
                                    <Link
                                        href={data.href}
                                        className={`${url === data.href && 'border-r-2 border-r-gray-400 bg-gray-100 text-gray-700 dark:border-r-gray-500 dark:bg-gray-900 dark:text-white'} min-w-full flex justify-center py-3 hover:border-r-2 hover:cursor-pointer text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100`}
                                        key={i}
                                        {...props}>
                                        {data.icon}
                                    </Link>
                                ))
                            }
                        </>
                    :
                    access === true &&
                        sidebarOpen ?
                        <>
                            <button
                                className={`min-w-full flex items-center font-medium gap-x-3.5 px-4 py-3 hover:border-r-2 capitalize hover:cursor-pointer text-sm justify-between text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100`}
                                onClick={() => setIsOpen(!isOpen)}>
                                <div className='flex items-center gap-x-3.5'>{icon}{title}</div>
                                {isOpen ? (
                                    <IconChevronUp size={18} strokeWidth={1.5} />
                                ) : (
                                    <IconChevronDown size={18} strokeWidth={1.5} />
                                )}
                            </button>
                            {isOpen &&
                                data.map((data, i) => (
                                    data.permissions === true &&
                                    <Link
                                        key={i}
                                        href={data.href}
                                        className={`${url === data.href && 'border-r-2 border-r-gray-400 bg-gray-100 text-gray-700 dark:border-r-gray-500 dark:bg-gray-900 dark:text-white'} min-w-full flex items-center font-medium gap-x-3.5 px-5 py-3 hover:border-r-2 capitalize hover:cursor-pointer text-sm line-clamp-1 text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100`}
                                        {...props}>
                                        <IconCornerDownRight size={18} strokeWidth={1.5} /> {data.title}
                                    </Link>
                                ))
                            }
                        </>
                        :
                        <>
                            <button
                                className='min-w-full flex justify-center py-3 hover:border-r-2 hover:cursor-pointer text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100'
                                onClick={() => setIsOpen(!isOpen)}>
                                {!isOpen ? icon : <IconChevronDown size={20} strokeWidth={1.5} />}
                            </button>
                            {isOpen &&
                                data.map((data, i) => (
                                    data.permissions === true &&
                                    <Link
                                        href={data.href}
                                        className={`${url === data.href && 'border-r-2 border-r-gray-400 bg-gray-100 text-gray-700 dark:border-r-gray-500 dark:bg-gray-900 dark:text-white'} min-w-full flex justify-center py-3 hover:border-r-2 hover:cursor-pointer text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100`}
                                        key={i}
                                        {...props}>
                                        {data.icon}
                                    </Link>
                                ))
                            }
                        </>
            }
        </>
    )
}

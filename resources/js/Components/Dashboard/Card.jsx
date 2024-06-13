import React from 'react'

export default function Card({ icon, title, children, footer, className, form }) {
    return (
        <>
            <form onSubmit={form}>
                <div className={`p-4 rounded-t-lg border ${className} bg-white dark:bg-gray-950 dark:border-gray-900 `}>
                    <div className='flex items-center gap-2 font-semibold text-sm text-gray-700 dark:text-gray-200'>
                        {title}
                    </div>
                </div>
                <div className='bg-white dark:bg-gray-950 p-4 border border-t-0 border-b-0 dark:border-gray-900'>
                    {children}
                </div>
                <div className={`px-4 py-2 rounded-b-lg border ${className} bg-white dark:bg-gray-950 dark:border-gray-900 `}>
                    {footer}
                </div>
            </form>
        </>
    )
}

import React from 'react'

export default function Header({ children, title, subtitle }) {
    return (
        <div className='flex w-full items-center justify-between gap-4 mb-2'>
            <div className=''>
                <div className='text-lg font-bold capitalize text-gray-200'>{title}</div>
                <div className='text-gray-500 text-sm'>{subtitle}</div>
            </div>
            {children}
        </div>
    )
}

import { useTheme } from '@/Context/ThemeSwitcherContext'
import React from 'react'

export default function Widget({ title, icon, subtitle, className, total, color }) {
    return (
        <div className={`${className} border p-4 rounded-lg bg-white dark:bg-gray-950 dark:border-gray-800`}>
            <div className='flex justify-between items-center gap-4'>
                <div className='flex items-center gap-3'>
                    <div className={`p-2 rounded-lg ${color}`}>
                        {icon}
                    </div>
                    <div className='flex flex-col'>
                        <div className='font-semibold text-gray-900 dark:text-gray-200'>{title}</div>
                        <div className='text-xs text-gray-500'>{subtitle}</div>
                    </div>
                </div>
                <div className='font-semibold text-base font-mono p-2 text-gray-900 dark:text-white'>
                    {total}
                </div>
            </div>
        </div>
    )
}

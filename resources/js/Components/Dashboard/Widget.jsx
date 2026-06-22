import { useTheme } from '@/Context/ThemeSwitcherContext'
import React from 'react'

export default function Widget({ title, icon, subtitle, className, total, color }) {
    return (
        <div className={`${className || ""} border border-hairline-light p-4 rounded-card bg-white shadow-paper dark:bg-canvas-night-elevated dark:border-hairline-dark`}>
            <div className='flex justify-between items-center gap-4'>
                <div className='flex items-center gap-3'>
                    <div className={`p-2 rounded-full ${color || "bg-aloe-100 text-ink dark:bg-hairline-dark dark:text-white"}`}>
                        {icon}
                    </div>
                    <div className='flex flex-col'>
                        <div className='font-semibold text-ink dark:text-gray-200'>{title}</div>
                        <div className='text-xs text-shade-50 dark:text-gray-400'>{subtitle}</div>
                    </div>
                </div>
                <div className='font-semibold text-base font-mono p-2 text-ink dark:text-white'>
                    {total}
                </div>
            </div>
        </div>
    )
}

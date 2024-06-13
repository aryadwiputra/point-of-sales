import React, { useState, useEffect, useRef } from 'react'
import { Menu, Transition } from '@headlessui/react'
import { IconBell, IconDots, IconCircleCheck, IconPackage, IconCashBanknote, IconPhoto, IconCircleCheckFilled, IconMessage } from '@tabler/icons-react'
export default function Notification() {

    const data = [
    ]

    // define state isMobile
    const [isMobile, setIsMobile] = useState(false);
    // define state isOpen
    const [isOpen, setIsOpen] = useState(false);
    // define ref notification
    const notificationRef = useRef(null);

    // define method handleClickOutside
    const handleClickOutside = (event) => {
        if (notificationRef.current && !notificationRef.current.contains(event.target)) {
            setIsOpen(false);
        }
    };

    // define useEffect
    useEffect(() => {
        // define handle resize window
        const handleResize = () => {
            setIsMobile(window.innerWidth <= 768);
        };

        // define event listener
        window.addEventListener('resize', handleResize);

        // add event listener
        window.addEventListener("mousedown", handleClickOutside);

        // call handle resize window
        handleResize();

        // remove event listener
        return () => {
            window.removeEventListener('resize', handleResize);
            window.removeEventListener('mousedown', handleClickOutside);
        };
    })

    return (
        <>
            {isMobile === false ?
                <Menu className='relative z-50' as="div">
                    <Menu.Button className='flex items-center rounded-md group p-2'>
                        <div className='absolute text-[8px] font-semibold border border-rose-500/40 bg-rose-500/10 text-rose-500 hover:bg-rose-500/20 top-0 -right-2 rounded-md px-1.5 py-0.5 group-hover:scale-125 duration-300 ease-in'>
                            {data.length}
                        </div>
                        <IconBell strokeWidth={1.5} size={18} className='text-gray-700 dark:text-gray-400' />
                    </Menu.Button>
                    <Transition
                        enter="transition duration-100 ease-out"
                        enterFrom="transform scale-95 opacity-0"
                        enterTo="transform scale-100 opacity-100"
                        leave="transition duration-75 ease-out"
                        leaveFrom="transform scale-100 opacity-100"
                        leaveTo="transform scale-95 opacity-0"
                    >
                        <Menu.Items className='absolute rounded-lg w-[500px] border md:right-0 z-[100] bg-white dark:bg-gray-950 dark:border-gray-900'>
                            <div className='flex justify-between items-center gap-2 p-4 border-b dark:border-gray-900'>
                                <div className='text-lg font-bold text-gray-700 dark:text-gray-200'>Notifikasi</div>
                                <IconDots className='text-gray-500 dark:text-gray-200' size={24} />
                            </div>
                            <div className='p-4'>
                                <div className='flex flex-col gap-2 items-start h-60 overflow-y-auto'>
                                    {/* If data is empty */}
                                    {data.length === 0 && <div className='text-sm text-gray-500 dark:text-gray-400'>Tidak ada notifikasi</div>}
                                    {data.map((data, i) => (
                                        <div className='flex items-center justify-between w-full p-4 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-900 ' key={i}>
                                            <div className='flex items-center gap-4'>
                                                {data.icon}
                                                <div>
                                                    <div className='font-semibold text-sm text-gray-700 dark:text-gray-200 '>
                                                        {data.user} <sup className='text-xs font-mono text-gray-400 ml-1'>{data.time}</sup>
                                                    </div>
                                                    <div className='text-gray-500 text-sm'>{data.title}</div>
                                                </div>
                                            </div>
                                            {data.is_read == 1 ? <IconCircleCheckFilled size={20} strokeWidth={1.5} className='text-gray-500 dark:text-gray-400' /> : <IconCircleCheck size={20} strokeWidth={1.5} className='text-gray-500 dark:Ltext-gray-400' />}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </Menu.Items>
                    </Transition>
                </Menu>
                :
                <div ref={notificationRef}>
                    <button className='flex items-center rounded-md group p-2 relative' onClick={() => setIsOpen(!isOpen)}>
                        <div className='absolute text-[8px] font-semibold border border-rose-500/40 bg-rose-500/10 text-rose-500 hover:bg-rose-500/20 top-0 -right-2 rounded-md px-1.5 py-0.5 group-hover:scale-125 duration-300 ease-in'>
                            {data.length}
                        </div>
                        <IconBell strokeWidth={1.5} size={18} className='text-gray-500 dark:text-gray-400' />
                    </button>
                    <div className={`${isOpen ? 'translate-x-0 opacity-100' : 'translate-x-full'} fixed top-0 right-0 z-50 w-[300px] h-full transition-all duration-300 transform border-l bg-white dark:bg-gray-950 dark:border-gray-900`}>
                        <div className='flex justify-between items-center gap-2 p-4 border-b mt-2 dark:border-gray-900 '>
                            <div className='text-base font-bold text-gray-500 dark:text-gray-400 '>Notifications</div>
                            <IconDots className='text-gray-500 dark:text-gray-400' size={24} />
                        </div>
                        <div className='p-4'>
                            <div className='flex flex-col gap-2 items-start overflow-y-auto h-screen'>

                                {data.map((data, i) => (
                                    <div className='flex items-center justify-between w-full p-4 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-900 ' key={i}>
                                        <div className='flex items-center gap-4'>
                                            {data.icon}
                                            <div className='w-full'>
                                                <div className='font-semibold text-sm line-clamp-1 text-gray-700 dark:text-gray-200'>
                                                    {data.user} <sup className='text-xs font-mono text-gray-400 ml-1'>{data.time}</sup>
                                                </div>
                                                <div className='text-gray-500 text-sm line-clamp-1 max-w-[155px]'>{data.title}</div>
                                            </div>
                                        </div>
                                        {data.is_read == 1 ? <IconCircleCheckFilled size={20} strokeWidth={1.5} className='text-gray-500 dark:text-gray-400' /> : <IconCircleCheck size={20} strokeWidth={1.5} className='text-gray-500 dark:text-gray-400' />}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            }
        </>
    )
}

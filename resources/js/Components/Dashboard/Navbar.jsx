import React, { useEffect, useState } from 'react'
import { usePage } from '@inertiajs/react';
import { IconAlignLeft, IconMoon, IconSun } from '@tabler/icons-react'
import AuthDropdown from '@/Components/Dashboard/AuthDropdown';
import Menu from '@/Utils/Menu';
import Notification from '@/Components/Dashboard/Notification';

export default function Navbar({ toggleSidebar, themeSwitcher, darkMode }) {
    // destruct auth from props
    const { auth } = usePage().props;

    // get menu from utils
    const menuNavigation = Menu();

    // recreate array from menu navigations
    const links = menuNavigation.flatMap((item) => item.details);
    const filter_sublinks = links.filter((item) => item.hasOwnProperty('subdetails'));
    const sublinks = filter_sublinks.flatMap((item) => item.subdetails);

    // define state isMobile
    const [isMobile, setIsMobile] = useState(false);

    // define useEffect
    useEffect(() => {
        // define handle resize window
        const handleResize = () => {
            setIsMobile(window.innerWidth <= 768);
        };

        // define event listener
        window.addEventListener('resize', handleResize);

        // call handle resize window
        handleResize();

        // remove event listener
        return () => {
            window.removeEventListener('resize', handleResize);
        };
    })

    return (
        <div className='py-8 px-4 md:px-6 flex justify-between items-center min-w-full sticky top-0 z-20 h-16 border-b bg-white dark:border-gray-900 dark:bg-gray-950'>
            <div className='flex items-center gap-4'>
                <button className='text-gray-700 dark:text-gray-400 hidden md:block' onClick={toggleSidebar}>
                    <IconAlignLeft size={18} strokeWidth={1.5} />
                </button>
                <div className='flex flex-row items-center gap-1 md:border-l-2 md:border-double md:px-4 dark:border-gray-900'>
                    {/* {links.map((link, i) => (
                        link.hasOwnProperty('subdetails') ?
                            sublinks.map((sublink, x) => sublink.active === true && <span className='font-semibold text-sm md:text-base text-gray-700 dark:text-gray-400' key={x}>{sublink.title}</span>)
                            :
                            link.active === true && <span className='font-semibold text-sm md:text-base text-gray-700 dark:text-gray-400' key={i}>{link.title}</span>
                    ))} */}
                    {links.map((link, i) => (
                        link.hasOwnProperty('subdetails') ?
                            sublinks.map((sublink, x) => sublink.active === true && <span className='font-semibold text-sm md:text-base text-gray-700 dark:text-gray-400' key={x}>{sublink.title}</span>)
                            :
                            link.active === true && <span className='font-semibold text-sm md:text-base text-gray-700 dark:text-gray-400 ' key={i}>{link.title}</span>
                    ))}
                </div>
            </div>
            <div className='flex items-center gap-4'>
                <div className='flex flex-row items-center gap-1 border-r-2 border-double px-4 dark:border-gray-900'>
                    <div className='flex flex-row gap-2'>
                        <button className='p-2 rounded-md text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-900 ' onClick={themeSwitcher}>
                            {darkMode ? <IconSun strokeWidth={1.5} size={18} /> : <IconMoon strokeWidth={1.5} size={18} />}
                        </button>
                        <Notification />
                    </div>
                </div>
                <AuthDropdown auth={auth} isMobile={isMobile} />
            </div>
        </div>
    )
}

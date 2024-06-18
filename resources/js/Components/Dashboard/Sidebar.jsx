import React from "react";
import { usePage } from "@inertiajs/react";
import { IconBrandReact } from "@tabler/icons-react";
import LinkItem from "@/Components/Dashboard/LinkItem";
import LinkItemDropdown from "@/Components/Dashboard/LinkItemDropdown";
import Menu from "@/Utils/Menu";

export default function Sidebar({ sidebarOpen }) {
    const { auth } = usePage().props;
    const menuNavigation = Menu();

    return (
        <div className={`${sidebarOpen ? 'w-[260px]' : 'w-[100px]'} hidden md:block min-h-screen overflow-y-auto border-r transition-all duration-300 bg-white dark:bg-gray-950 dark:border-gray-900`}>
            {sidebarOpen ? (
                <>
                    <div className="flex justify-center items-center px-6 py-2 h-16">
                        <div className="text-2xl font-bold text-center leading-loose tracking-wider text-gray-900 dark:text-gray-200">
                            KASIR
                        </div>
                    </div>
                    <div className="w-full p-3 flex items-center gap-4 border-b border-t dark:bg-gray-950/50 dark:border-gray-900">
                        <img src={auth.user.avatar ? auth.user.avatar : "https://ui-avatars.com/api/?name=" + auth.user.name} className="w-12 h-12 rounded-full" />
                        <div className="flex flex-col gap-0.5">
                            <div className="text-sm font-semibold capitalize text-gray-700 dark:text-gray-50">
                                {auth.user.name}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                {auth.user.email}
                            </div>
                        </div>
                    </div>
                    <div className="w-full flex flex-col overflow-y-auto">
                        {menuNavigation.map((item, index) => (
                            item.details.some(detail => detail.permissions === true) && (
                                <div key={index}>
                                    <div className="text-gray-500 text-xs py-3 px-4 font-bold uppercase">
                                        {item.title}
                                    </div>
                                    {item.details.map((detail, indexDetail) => (
                                        detail.permissions === true && (
                                            detail.hasOwnProperty('subdetails') ? (
                                                <LinkItemDropdown
                                                    key={indexDetail}
                                                    title={detail.title}
                                                    icon={detail.icon}
                                                    data={detail.subdetails}
                                                    access={detail.permissions}
                                                    sidebarOpen={sidebarOpen}
                                                />
                                            ) : (
                                                <LinkItem
                                                    key={indexDetail}
                                                    title={detail.title}
                                                    icon={detail.icon}
                                                    href={detail.href}
                                                    access={detail.permissions}
                                                    sidebarOpen={sidebarOpen}
                                                />
                                            )
                                        )
                                    ))}
                                </div>
                            )
                        ))}
                    </div>
                </>
            ) : (
                <>
                    <div className="flex justify-center items-center px-6 py-2 h-16 border-b dark:border-gray-900">
                        <IconBrandReact size={20} strokeWidth={1.5} className="sidebar-title" />
                    </div>
                    <div className='w-full px-6 py-3 flex justify-center items-center gap-4 border-b bg-white dark:bg-gray-950/50 dark:border-gray-900'>
                        <img src={auth.user.avatar ? auth.user.avatar : "https://ui-avatars.com/api/?name=" + auth.user.name} className='w-8 h-8 rounded-full' />
                    </div>
                    <div className='w-full flex flex-col overflow-y-auto items-center justify-center'>
                        {menuNavigation.map((link, i) => (
                            link.details.some(detail => detail.permissions === true) && (
                                <div className='flex flex-col min-w-full items-center relative' key={i}>
                                    {link.details.some(detail => detail.permissions === true) && (
                                        link.details.map((detail, x) =>
                                            detail.hasOwnProperty('subdetails') ? (
                                                <LinkItemDropdown
                                                    sidebarOpen={sidebarOpen}
                                                    key={x}
                                                    title={detail.title}
                                                    data={detail.subdetails}
                                                    icon={detail.icon}
                                                    access={detail.permissions}
                                                />
                                            ) : (
                                                <LinkItem
                                                    sidebarOpen={sidebarOpen}
                                                    key={x}
                                                    access={link.permissions}
                                                    icon={detail.icon}
                                                    href={detail.href}
                                                />
                                            )
                                        )
                                    )}
                                </div>
                            )
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

import { usePage } from '@inertiajs/react';
import { IconBooks, IconChartBarPopular, IconChartInfographic, IconCirclePlus, IconClockHour6, IconFileCertificate, IconFileDescription, IconLayout2, IconSchool, IconTable, IconUserBolt, IconUserShield, IconUserSquare, IconUsers } from '@tabler/icons-react';
import hasAnyPermission from './Permission';
import React from 'react'

export default function Menu() {

    // define use page
    const { url } = usePage();

    // define menu navigations
    const menuNavigation = [
        {
            title: 'Overview',
            details: [
                {
                    title: 'Dashboard',
                    href: route('dashboard'),
                    active: url.startsWith('/dashboard') ? true : false,
                    icon: <IconLayout2 size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(['dashboard-access']),
                },
            ]
        },
        {
            title: 'User Management',
            details: [
                {
                    title: 'Hak Akses',
                    href: route('permissions.index'),
                    active: url.startsWith('/dashboard/permissions') ? true : false,
                    icon: <IconUserBolt size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(['permissions-access']),
                },
                {
                    title: 'Akses Group',
                    href: route('roles.index'),
                    active: url.startsWith('/dashboard/roles') ? true : false,
                    icon: <IconUserShield size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(['roles-access']),
                },
                {
                    title: 'Pengguna',
                    icon: <IconUsers size={20} strokeWidth={1.5} />,
                    permissions: hasAnyPermission(['users-access']),
                    subdetails: [
                        {
                            title: 'Data Pengguna',
                            href: route('users.index'),
                            icon: <IconTable size={20} strokeWidth={1.5} />,
                            active: url === '/users' ? true : false,
                            permissions: hasAnyPermission(['users-access']),
                        },
                        {
                            title: 'Tambah Data Pengguna',
                            href: route('users.create'),
                            icon: <IconCirclePlus size={20} strokeWidth={1.5} />,
                            active: url === '/users/create' ? true : false,
                            permissions: hasAnyPermission(['users-create']),
                        },
                    ]
                }
            ]
        }
    ]

    return menuNavigation;
}

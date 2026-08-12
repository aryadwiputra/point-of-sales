import React, { useMemo, useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import {
    IconChevronDown,
    IconChevronUp,
    IconCornerDownRight,
} from "@tabler/icons-react";
import { isSuperAdmin } from "@/Utils/authorization";

export default function LinkItemDropdown({ icon, title, data, access, sidebarOpen, ...props }) {
    const { url } = usePage();
    const { auth } = usePage().props;
    const superAdmin = isSuperAdmin(auth);

    const visibleItems = useMemo(
        () => data.filter((item) => superAdmin || item.permissions === true),
        [data, superAdmin]
    );

    // Auto-open dropdown when current URL is inside this submenu
    const [isOpen, setIsOpen] = useState(() =>
        visibleItems.some((item) => url === item.href)
    );

    const canRenderParent = superAdmin || access === true || visibleItems.length > 0;

    if (!canRenderParent || visibleItems.length === 0) {
        return null;
    }

    const buttonClass = sidebarOpen
        ? "min-w-full flex items-center font-medium gap-x-3.5 px-4 py-3 hover:border-r-2 capitalize hover:cursor-pointer text-sm justify-between text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100"
        : "min-w-full flex justify-center py-3 hover:border-r-2 hover:cursor-pointer text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100";

    return (
        <>
            <button className={buttonClass} onClick={() => setIsOpen(!isOpen)}>
                {sidebarOpen ? (
                    <>
                        <div className="flex items-center gap-x-3.5">
                            {icon}
                            {title}
                        </div>
                        {isOpen ? (
                            <IconChevronUp size={18} strokeWidth={1.5} />
                        ) : (
                            <IconChevronDown size={18} strokeWidth={1.5} />
                        )}
                    </>
                ) : !isOpen ? (
                    icon
                ) : (
                    <IconChevronDown size={20} strokeWidth={1.5} />
                )}
            </button>

            {isOpen &&
                visibleItems.map((item, index) => (
                    <Link
                        key={index}
                        href={item.href}
                        onClick={() => {
                            // Collapse submenu after navigating on mobile
                            if (window.innerWidth < 768) {
                                setIsOpen(false);
                            }
                        }}
                        className={`${
                            url === item.href &&
                            "border-r-2 border-r-gray-400 bg-gray-100 text-gray-700 dark:border-r-gray-500 dark:bg-gray-900 dark:text-white"
                        } ${
                            sidebarOpen
                                ? "min-w-full flex items-center font-medium gap-x-3.5 px-5 py-3 hover:border-r-2 capitalize hover:cursor-pointer text-sm line-clamp-1 text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100"
                                : "min-w-full flex justify-center py-3 hover:border-r-2 hover:cursor-pointer text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100"
                        }`}
                        {...props}
                    >
                        {sidebarOpen ? (
                            <>
                                <IconCornerDownRight
                                    size={18}
                                    strokeWidth={1.5}
                                />{" "}
                                {item.title}
                            </>
                        ) : (
                            item.icon
                        )}
                    </Link>
                ))}
        </>
    );
}

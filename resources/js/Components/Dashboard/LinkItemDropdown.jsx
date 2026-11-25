import React, { useMemo, useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import {
    IconChevronDown,
    IconChevronUp,
    IconCornerDownRight,
} from "@tabler/icons-react";
import { isSuperAdmin } from "@/Utils/authorization";
import { resolveActiveHref } from "@/Utils/activeMenu";

export default function LinkItemDropdown({ icon, title, data, access, sidebarOpen, ...props }) {
    const { url } = usePage();
    const { auth } = usePage().props;
    const superAdmin = isSuperAdmin(auth);

    const visibleItems = useMemo(
        () => data.filter((item) => superAdmin || item.permissions === true),
        [data, superAdmin]
    );

    const activeHref = resolveActiveHref(visibleItems, url);
    const hasActiveChild = activeHref !== null;

    // Auto-open dropdown when current URL is inside this submenu
    const [isOpen, setIsOpen] = useState(hasActiveChild);

    const canRenderParent = superAdmin || access === true || visibleItems.length > 0;

    if (!canRenderParent || visibleItems.length === 0) {
        return null;
    }

    const buttonClass = `${
        hasActiveChild
            ? "border-r-2 border-r-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-950/50 dark:text-primary-400 dark:border-r-primary-400"
            : "text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100"
    } ${
        sidebarOpen
            ? "min-w-full flex items-center font-medium gap-x-3.5 px-4 py-3 hover:border-r-2 capitalize hover:cursor-pointer text-sm justify-between"
            : "min-w-full flex justify-center py-3 hover:border-r-2 hover:cursor-pointer"
    }`;

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
                            activeHref === item.href
                                ? "border-r-2 border-r-primary-500 bg-primary-50 text-primary-700 dark:border-r-primary-400 dark:bg-primary-950/50 dark:text-primary-400"
                                : "text-gray-500 hover:border-r-gray-700 hover:text-gray-900 dark:text-gray-500 dark:hover:border-r-gray-50 dark:hover:text-gray-100"
                        } ${
                            sidebarOpen
                                ? "min-w-full flex items-center font-medium gap-x-3.5 px-5 py-3 capitalize hover:cursor-pointer text-sm line-clamp-1"
                                : "min-w-full flex justify-center py-3 hover:cursor-pointer"
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

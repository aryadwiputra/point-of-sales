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
    const [isOpen, setIsOpen] = useState(false);
    const { auth } = usePage().props;
    const superAdmin = isSuperAdmin(auth);

    const visibleItems = useMemo(
        () => data.filter((item) => superAdmin || item.permissions === true),
        [data, superAdmin]
    );

    const canRenderParent = superAdmin || access === true || visibleItems.length > 0;

    if (!canRenderParent || visibleItems.length === 0) {
        return null;
    }

    const buttonClass = sidebarOpen
        ? "mx-3 flex min-w-[calc(100%-1.5rem)] items-center justify-between gap-x-3.5 rounded-full px-4 py-3 text-sm font-medium capitalize text-shade-60 transition hover:cursor-pointer hover:bg-canvas-cream hover:text-ink dark:text-gray-400 dark:hover:bg-canvas-night dark:hover:text-gray-100"
        : "min-w-full flex justify-center py-3 text-shade-50 transition hover:cursor-pointer hover:bg-canvas-cream hover:text-ink dark:text-gray-400 dark:hover:bg-canvas-night dark:hover:text-gray-100";

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
                        className={`${
                            url === item.href &&
                            "bg-aloe-100 text-ink dark:bg-hairline-dark dark:text-white"
                        } ${
                            sidebarOpen
                                ? "mx-3 flex min-w-[calc(100%-1.5rem)] items-center gap-x-3.5 rounded-full px-5 py-3 text-sm font-medium capitalize line-clamp-1 text-shade-50 transition hover:cursor-pointer hover:bg-canvas-cream hover:text-ink dark:text-gray-500 dark:hover:bg-canvas-night dark:hover:text-gray-100"
                                : "min-w-full flex justify-center py-3 text-gray-500 transition hover:cursor-pointer hover:bg-canvas-cream hover:text-gray-900 dark:text-gray-500 dark:hover:bg-canvas-night dark:hover:text-gray-100"
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

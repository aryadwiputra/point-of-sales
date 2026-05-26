import React from "react";
import { Link, usePage } from "@inertiajs/react";
import { isSuperAdmin } from "@/Utils/authorization";

export default function LinkItem({
    href,
    icon,
    access,
    title,
    sidebarOpen,
    ...props
}) {
    const { url } = usePage();
    const { auth } = usePage().props;

    const isActive = url.startsWith(href);
    const canAccess = isSuperAdmin(auth) || access === true;

    if (!canAccess) return null;

    const baseClasses = `
        flex items-center gap-3
        transition-all duration-200
        text-shade-60 dark:text-slate-400
    `;

    const activeClasses = isActive
        ? "bg-aloe-100 text-ink dark:bg-hairline-dark dark:text-white"
        : "hover:bg-canvas-cream dark:hover:bg-canvas-night hover:text-ink dark:hover:text-slate-200";

    if (sidebarOpen) {
        return (
            <Link
                href={href}
                className={`${baseClasses} ${activeClasses} mx-3 rounded-full px-4 py-2.5 text-sm font-medium`}
                {...props}
            >
                <span
                    className={
                        isActive ? "text-ink dark:text-white" : ""
                    }
                >
                    {icon}
                </span>
                <span className="truncate">{title}</span>
            </Link>
        );
    }

    // Collapsed sidebar
    return (
        <Link
            href={href}
            className={`
                w-full flex justify-center py-3
                transition-all duration-200
                ${
                    isActive
                        ? "text-ink dark:text-white bg-aloe-100 dark:bg-hairline-dark"
                        : "text-shade-50 dark:text-slate-400 hover:text-ink dark:hover:text-slate-200 hover:bg-canvas-cream dark:hover:bg-canvas-night"
                }
            `}
            title={title}
            {...props}
        >
            {icon}
        </Link>
    );
}

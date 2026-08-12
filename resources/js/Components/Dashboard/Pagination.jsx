import React from "react";
import { Link } from "@inertiajs/react";
import {
    IconChevronRight,
    IconChevronLeft,
    IconDots,
} from "@tabler/icons-react";

/**
 * Pagination — robust & responsive.
 * Handles Laravel paginator links: prev/next (locale-proof), ellipsis,
 * disabled states, and wraps gracefully on mobile.
 */
export default function Pagination({ links }) {
    if (!links || links.length <= 3) return null;

    const numericLabels = links
        .map((l) => l.label)
        .filter((label) => /^\d+$/.test(label))
        .map(Number);

    const current =
        links.find((l) => l.active && /^\d+$/.test(l.label))?.label || "?";
    const total = numericLabels.length ? Math.max(...numericLabels) : "?";

    const baseBtn =
        "inline-flex items-center justify-center min-w-[34px] h-[34px] text-sm border rounded-lg bg-white text-slate-500 hover:bg-slate-100 dark:bg-slate-950 dark:text-slate-400 dark:hover:bg-slate-900 dark:border-slate-800 transition-colors";
    const activeBtn =
        "border-primary-500 bg-primary-50 text-primary-700 font-semibold dark:bg-primary-950/60 dark:text-primary-300 dark:border-primary-700";
    const disabledBtn = "opacity-40 pointer-events-none";

    const isPrev = (item, i) =>
        i === 0 ||
        item.label.includes("Previous") ||
        item.label.includes("Sebelumnya");
    const isNext = (item, i) =>
        i === links.length - 1 ||
        item.label.includes("Next") ||
        item.label.includes("Berikutnya");
    const isEllipsis = (item) =>
        !item.url && !/^\d+$/.test(item.label) && !isPrev(item, 0) && !isNext(item, links.length - 1);

    return (
        <nav
            aria-label="Pagination"
            className="mt-4 lg:mt-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3"
        >
            <p className="text-sm text-slate-500 dark:text-slate-400 order-2 sm:order-1">
                Halaman <span className="font-medium text-slate-700 dark:text-slate-200">{current}</span> dari{" "}
                <span className="font-medium text-slate-700 dark:text-slate-200">{total}</span>
            </p>

            <ul className="order-1 sm:order-2 flex flex-wrap items-center justify-end gap-1.5">
                {links.map((item, i) => {
                    if (isPrev(item, i)) {
                        return item.url ? (
                            <Link
                                key={i}
                                href={item.url}
                                className={baseBtn}
                                aria-label="Halaman sebelumnya"
                            >
                                <IconChevronLeft size={18} strokeWidth={1.5} />
                            </Link>
                        ) : (
                            <span
                                key={i}
                                className={`${baseBtn} ${disabledBtn}`}
                                aria-disabled="true"
                                aria-label="Halaman sebelumnya"
                            >
                                <IconChevronLeft size={18} strokeWidth={1.5} />
                            </span>
                        );
                    }

                    if (isNext(item, i)) {
                        return item.url ? (
                            <Link
                                key={i}
                                href={item.url}
                                className={baseBtn}
                                aria-label="Halaman berikutnya"
                            >
                                <IconChevronRight size={18} strokeWidth={1.5} />
                            </Link>
                        ) : (
                            <span
                                key={i}
                                className={`${baseBtn} ${disabledBtn}`}
                                aria-disabled="true"
                                aria-label="Halaman berikutnya"
                            >
                                <IconChevronRight size={18} strokeWidth={1.5} />
                            </span>
                        );
                    }

                    if (isEllipsis(item) || !item.url) {
                        return (
                            <span
                                key={i}
                                className={`${baseBtn} ${disabledBtn}`}
                                aria-disabled="true"
                            >
                                <IconDots size={16} />
                            </span>
                        );
                    }

                    return (
                        <Link
                            key={i}
                            href={item.url}
                            aria-current={item.active ? "page" : undefined}
                            className={`${baseBtn} px-2.5 ${item.active ? activeBtn : ""}`}
                        >
                            {item.label}
                        </Link>
                    );
                })}
            </ul>
        </nav>
    );
}

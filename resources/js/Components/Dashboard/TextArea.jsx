import React from "react";

export default function Textarea({
    label,
    className,
    errors,
    rows = 4,
    ...props
}) {
    return (
        <div className="flex flex-col gap-2">
            {label && (
                <label className="text-sm font-medium text-shade-70 dark:text-slate-300">
                    {label}
                </label>
            )}
            <textarea
                rows={rows}
                className={`
                    w-full px-4 py-3 text-sm rounded-md
                    border border-hairline-light dark:border-hairline-dark
                    bg-white dark:bg-canvas-night-elevated
                    text-ink dark:text-slate-200
                    placeholder-slate-400 dark:placeholder-slate-500
                    focus:outline-none focus:ring-4 focus:ring-aloe-100/70 focus:border-ink dark:focus:border-slate-200
                    transition-all duration-200 resize-none
                    ${
                        errors
                            ? "border-danger-500 focus:border-danger-500 focus:ring-danger-500/20"
                            : ""
                    }
                    ${className || ""}
                `}
                {...props}
            />
            {errors && (
                <small className="text-xs text-danger-500 dark:text-danger-400">
                    {errors}
                </small>
            )}
        </div>
    );
}

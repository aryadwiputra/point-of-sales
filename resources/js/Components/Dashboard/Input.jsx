import React from "react";

export default function Input({ label, type, className, errors, icon, ...props }) {
    return (
        <div className="flex flex-col gap-2">
            {label && (
                <label className="text-sm font-medium text-shade-70 dark:text-slate-300">
                    {label}
                </label>
            )}
            <div className="relative">
                {icon && (
                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-shade-40">
                        {icon}
                    </span>
                )}
                <input
                    type={type}
                    className={`
                        w-full min-h-touch ${icon ? "pl-10 pr-4" : "px-4"} text-sm rounded-md
                        border border-hairline-light dark:border-hairline-dark
                        bg-white dark:bg-canvas-night-elevated
                        text-ink dark:text-slate-100
                        placeholder-slate-400 dark:placeholder-slate-500
                        focus:outline-none focus:ring-4 focus:ring-aloe-100/70 focus:border-ink dark:focus:border-slate-200
                        transition-all duration-200
                        ${
                            errors
                                ? "border-danger-500 focus:border-danger-500 focus:ring-danger-500/20"
                                : ""
                        }
                        ${className || ""}
                    `}
                    {...props}
                />
            </div>
            {errors && (
                <small className="text-xs text-danger-500 dark:text-danger-400">
                    {errors}
                </small>
            )}
        </div>
    );
}

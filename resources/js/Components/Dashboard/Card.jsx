import React from "react";

export default function Card({
    icon,
    title,
    children,
    footer,
    className,
    form,
}) {
    const CardWrapper = form ? "form" : "div";
    const wrapperProps = form ? { onSubmit: form } : {};

    return (
        <CardWrapper {...wrapperProps}>
            {/* Header */}
            <div
                className={`px-5 py-4 rounded-t-card border border-b-0 ${className || ""} bg-white dark:bg-canvas-night-elevated border-hairline-light dark:border-hairline-dark shadow-paper`}
            >
                <div className="flex items-center gap-2.5">
                    {icon && (
                        <div className="w-8 h-8 rounded-full bg-aloe-100 dark:bg-hairline-dark flex items-center justify-center text-ink dark:text-white">
                            {icon}
                        </div>
                    )}
                    <h3 className="font-semibold text-base text-ink dark:text-slate-100">
                        {title}
                    </h3>
                </div>
            </div>

            {/* Content */}
            <div className="bg-white dark:bg-canvas-night-elevated px-5 py-5 border-x border-hairline-light dark:border-hairline-dark">
                {children}
            </div>

            {/* Footer */}
            {footer && (
                <div
                    className={`px-5 py-4 rounded-b-card border border-t-0 ${className || ""} bg-canvas-cream dark:bg-canvas-night border-hairline-light dark:border-hairline-dark`}
                >
                    {footer}
                </div>
            )}
        </CardWrapper>
    );
}

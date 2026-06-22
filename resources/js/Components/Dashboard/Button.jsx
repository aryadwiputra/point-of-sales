import { Link } from "@inertiajs/react";
import React from "react";
import { useForm } from "@inertiajs/react";
import Swal from "sweetalert2";

export default function Button({
    className,
    icon,
    label,
    type,
    href,
    added,
    url,
    id,
    ...props
}) {
    const { delete: destroy } = useForm();

    const deleteData = async (url) => {
        Swal.fire({
            title: "Hapus Data?",
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#000000",
            cancelButtonColor: "#71717a",
            confirmButtonText: "Ya, Hapus!",
            cancelButtonText: "Batal",
        }).then((result) => {
            if (result.isConfirmed) {
                destroy(url);

                Swal.fire({
                    title: "Berhasil!",
                    text: "Data berhasil dihapus!",
                    icon: "success",
                    showConfirmButton: false,
                    timer: 1500,
                });
            }
        });
    };

    const baseStyles =
        "inline-flex min-h-touch items-center justify-center gap-2 rounded-full font-medium transition-all duration-200 active:scale-[0.98]";
    const sizeStyles = "px-4 py-2.5 text-sm";
    const smallStyles = "min-w-touch px-3 py-2 text-sm";
    const defaultStyles =
        "bg-ink text-white hover:bg-shade-70 disabled:cursor-not-allowed disabled:opacity-50";
    const defaultDangerStyles =
        "border border-danger-200 bg-danger-50 text-danger-600 hover:bg-danger-100 dark:border-danger-900 dark:bg-danger-950/40 dark:text-danger-300";
    const resolvedClassName = className || defaultStyles;

    return (
        <>
            {type === "link" && (
                <Link
                    href={href}
                    className={`${baseStyles} ${sizeStyles} ${resolvedClassName}`}
                >
                    {icon}{" "}
                    <span
                        className={`${added === true ? "hidden lg:block" : ""}`}
                    >
                        {label}
                    </span>
                </Link>
            )}
            {type === "button" && (
                <button
                    className={`${baseStyles} ${sizeStyles} ${resolvedClassName}`}
                    {...props}
                >
                    {icon}{" "}
                    <span
                        className={`${added === true ? "hidden md:block" : ""}`}
                    >
                        {label}
                    </span>
                </button>
            )}
            {type === "submit" && (
                <button
                    type="submit"
                    className={`${baseStyles} ${sizeStyles} ${resolvedClassName}`}
                    {...props}
                >
                    {icon}{" "}
                    <span
                        className={`${added === true ? "hidden lg:block" : ""}`}
                    >
                        {label}
                    </span>
                </button>
            )}
            {type === "delete" && (
                <button
                    onClick={() => deleteData(url)}
                    className={`${baseStyles} ${smallStyles} ${className || defaultDangerStyles}`}
                    {...props}
                >
                    {icon} {label && <span>{label}</span>}
                </button>
            )}
            {type === "modal" && (
                <button
                    className={`${baseStyles} ${smallStyles} ${className || "border border-hairline-light bg-white text-ink hover:bg-canvas-cream dark:border-hairline-dark dark:bg-canvas-night-elevated dark:text-white"}`}
                    {...props}
                >
                    {icon}
                </button>
            )}
            {type === "edit" && (
                <Link
                    href={href}
                    className={`${baseStyles} ${smallStyles} ${className || "border border-warning-200 bg-warning-50 text-warning-700 hover:bg-warning-100 dark:border-warning-900 dark:bg-warning-950/40 dark:text-warning-300"}`}
                    {...props}
                >
                    {icon}
                </Link>
            )}
            {type === "bulk" && (
                <button
                    {...props}
                    className={`${baseStyles} ${sizeStyles} ${resolvedClassName}`}
                >
                    {icon}{" "}
                    <span
                        className={`${added === true ? "hidden lg:block" : ""}`}
                    >
                        {label}
                    </span>
                </button>
            )}
        </>
    );
}

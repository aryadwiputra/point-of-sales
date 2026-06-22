import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.jsx",
    ],
    darkMode: "class",
    theme: {
        extend: {
            fontFamily: {
                sans: ["Inter", "Inter Display", ...defaultTheme.fontFamily.sans],
                display: ["Inter Display", "Inter", ...defaultTheme.fontFamily.sans],
                mono: [
                    "JetBrains Mono",
                    "Fira Code",
                    ...defaultTheme.fontFamily.mono,
                ],
            },
            colors: {
                canvas: {
                    night: "#000000",
                    "night-elevated": "#0a0a0a",
                    light: "#ffffff",
                    cream: "#fbfbf5",
                },
                ink: "#000000",
                shade: {
                    30: "#d4d4d8",
                    40: "#a1a1aa",
                    50: "#71717a",
                    60: "#52525b",
                    70: "#3f3f46",
                },
                hairline: {
                    light: "#e4e4e7",
                    dark: "#1e2c31",
                },
                aloe: {
                    10: "#c1fbd4",
                    50: "#effff4",
                    100: "#c1fbd4",
                    200: "#98f3b7",
                    500: "#31c36b",
                    700: "#16723d",
                },
                pistachio: {
                    10: "#d4f9e0",
                    50: "#f4fff7",
                    100: "#d4f9e0",
                    200: "#b8efc9",
                    500: "#4fc97a",
                    700: "#1f6d3d",
                },
                primary: {
                    50: "#f7f7f7",
                    100: "#eeeeee",
                    200: "#e4e4e7",
                    300: "#d4d4d8",
                    400: "#a1a1aa",
                    500: "#000000",
                    600: "#000000",
                    700: "#18181b",
                    800: "#09090b",
                    900: "#000000",
                    950: "#000000",
                },
                accent: {
                    50: "#f4fff7",
                    100: "#d4f9e0",
                    200: "#c1fbd4",
                    300: "#98f3b7",
                    400: "#70e89a",
                    500: "#31c36b",
                    600: "#249653",
                    700: "#16723d",
                    800: "#115a31",
                    900: "#0b3e22",
                    950: "#062514",
                },
                success: {
                    50: "#ecfdf5",
                    100: "#d1fae5",
                    200: "#a7f3d0",
                    300: "#6ee7b7",
                    400: "#34d399",
                    500: "#10b981",
                    600: "#059669",
                    700: "#047857",
                    800: "#065f46",
                    900: "#064e3b",
                    950: "#022c22",
                },
                warning: {
                    50: "#fffbeb",
                    100: "#fef3c7",
                    200: "#fde68a",
                    300: "#fcd34d",
                    400: "#fbbf24",
                    500: "#f59e0b",
                    600: "#d97706",
                    700: "#b45309",
                    800: "#92400e",
                    900: "#78350f",
                    950: "#451a03",
                },
                danger: {
                    50: "#fff1f2",
                    100: "#ffe4e6",
                    200: "#fecdd3",
                    300: "#fda4af",
                    400: "#fb7185",
                    500: "#f43f5e",
                    600: "#e11d48",
                    700: "#be123c",
                    800: "#9f1239",
                    900: "#881337",
                    950: "#4c0519",
                },
            },
            spacing: {
                18: "4.5rem",
                88: "22rem",
                100: "25rem",
                112: "28rem",
                128: "32rem",
            },
            minHeight: {
                touch: "2.75rem",
                "touch-lg": "3rem",
            },
            minWidth: {
                touch: "2.75rem",
                "touch-lg": "3rem",
            },
            borderRadius: {
                card: "0.75rem",
                pill: "9999px",
                "4xl": "2rem",
            },
            boxShadow: {
                paper: "0 1px 2px rgb(0 0 0 / 0.04), 0 12px 30px rgb(0 0 0 / 0.06)",
                glow: "0 0 20px rgba(193, 251, 212, 0.45)",
                "glow-lg": "0 0 40px rgba(193, 251, 212, 0.5)",
                "inner-lg": "inset 0 4px 6px -1px rgb(0 0 0 / 0.1)",
            },
            animation: {
                "slide-in": "slideIn 0.2s ease-out",
                "slide-up": "slideUp 0.2s ease-out",
                "fade-in": "fadeIn 0.15s ease-out",
                "pulse-subtle":
                    "pulseSubtle 2s cubic-bezier(0.4, 0, 0.6, 1) infinite",
                "bounce-subtle":
                    "bounceSubtle 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)",
                "cart-add": "cartAdd 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)",
            },
            keyframes: {
                slideIn: {
                    "0%": { transform: "translateX(100%)", opacity: "0" },
                    "100%": { transform: "translateX(0)", opacity: "1" },
                },
                slideUp: {
                    "0%": { transform: "translateY(10px)", opacity: "0" },
                    "100%": { transform: "translateY(0)", opacity: "1" },
                },
                fadeIn: {
                    "0%": { opacity: "0" },
                    "100%": { opacity: "1" },
                },
                pulseSubtle: {
                    "0%, 100%": { opacity: "1" },
                    "50%": { opacity: "0.7" },
                },
                bounceSubtle: {
                    "0%": { transform: "scale(1)" },
                    "50%": { transform: "scale(1.05)" },
                    "100%": { transform: "scale(1)" },
                },
                cartAdd: {
                    "0%": { transform: "scale(0.8)", opacity: "0" },
                    "50%": { transform: "scale(1.1)" },
                    "100%": { transform: "scale(1)", opacity: "1" },
                },
            },
            backdropBlur: {
                xs: "2px",
            },
            transitionDuration: {
                250: "250ms",
            },
        },
    },
    plugins: [forms],
};

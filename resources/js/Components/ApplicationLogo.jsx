export default function ApplicationLogo({ className = "w-10 h-10", ...props }) {
    return (
        <svg
            viewBox="0 0 64 64"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={className}
            {...props}
        >
            <defs>
                <linearGradient id="bihajuLogoGrad" x1="8" y1="4" x2="56" y2="60" gradientUnits="userSpaceOnUse">
                    <stop stopColor="#6366f1" />
                    <stop offset="1" stopColor="#4f46e5" />
                </linearGradient>
            </defs>
            <rect x="4" y="4" width="56" height="56" rx="16" fill="url(#bihajuLogoGrad)" />
            <path
                d="M24 18h11c6 0 9 3 9 7.2 0 3-1.7 5-4.3 6 3.4.8 5.8 3 5.8 7.3 0 5.1-3.8 8.5-10.4 8.5H24V18zm9.7 11.5c2.8 0 4.3-1.2 4.3-3.3 0-2-1.4-3.2-4.2-3.2h-3.9v6.5h3.8zm.7 12.5c3.2 0 5-1.4 5-3.9 0-2.6-1.9-3.9-5.2-3.9h-4.3v7.8h4.5z"
                fill="#fff"
            />
        </svg>
    );
}

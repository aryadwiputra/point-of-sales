const MARK_BOWL =
    "M50 126a58 58 0 1 0 116 0 58 58 0 1 0-116 0zM82 126a26 26 0 1 0 52 0 26 26 0 1 0-52 0z";
const MARK_STEM = "M146 44h36v148h-36z";
const MARK_TEETH = "M146 192h36l-6 12-6-12-6 12-6-12-6 12-6-12z";


const TONES = {
    brand: {
        mark: "fill-primary-600 dark:fill-primary-400",
        teeth: "fill-accent-500 dark:fill-accent-400",
    },
    mono: {
        mark: "fill-current",
        teeth: "fill-current",
    },
    white: {
        mark: "fill-white",
        teeth: "fill-white",
    },
};

export function BrandMark({ size = 32, tone = "brand", className = "" }) {
    const colors = TONES[tone] ?? TONES.brand;

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 256 256"
            width={size}
            height={size}
            role="img"
            aria-label="Dikasir"
            className={className}
        >
            <path fillRule="evenodd" className={colors.mark} d={MARK_BOWL} />
            <path className={colors.mark} d={MARK_STEM} />
            <path className={colors.teeth} d={MARK_TEETH} />
        </svg>
    );
}

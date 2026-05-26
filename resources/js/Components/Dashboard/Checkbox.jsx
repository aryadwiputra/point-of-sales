export default function Checkbox({ label, errors, ...props }) {
    return (
        <div>
            <div className="flex flex-row items-center gap-2">
                <input
                    {...props}
                    type="checkbox"
                    className={'rounded-md bg-white border-hairline-light text-ink focus:ring-aloe-100 dark:bg-canvas-night-elevated dark:border-hairline-dark checked:bg-ink'}
                />
                <label className="text-sm text-shade-70 dark:text-gray-400">{label}</label>

                {errors && (
                    <small className='text-xs text-red-500'>{errors}</small>
                )}
            </div>
        </div>

    );
}

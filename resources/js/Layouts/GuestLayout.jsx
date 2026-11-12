import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, usePage } from '@inertiajs/react';

export default function Guest({ children }) {
    const { brand } = usePage().props;
    const name = brand?.name || 'Bihaju POS';
    const tagline = brand?.tagline || 'Bikin Usaha Maju';

    return (
        <div className="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-50 dark:bg-slate-950">
            <div className="text-center">
                <Link href="/" className="inline-flex flex-col items-center gap-2">
                    <ApplicationLogo className="w-16 h-16" />
                    <div>
                        <div className="text-xl font-bold text-slate-900 dark:text-white">{name}</div>
                        <div className="text-xs text-slate-500 dark:text-slate-400">{tagline}</div>
                    </div>
                </Link>
            </div>

            <div className="w-full sm:max-w-md mt-6 px-6 py-5 bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden sm:rounded-2xl">
                {children}
            </div>
        </div>
    );
}

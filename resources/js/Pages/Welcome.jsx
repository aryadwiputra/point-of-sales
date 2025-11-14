export default function Landing() {
    return (
        <div className="min-h-screen bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-100">
            {/* NAVBAR */}
            <header className="w-full border-b border-neutral-200 dark:border-neutral-800">
                <div className="mx-auto max-w-6xl px-6 py-4 flex items-center justify-between">
                    <h1 className="text-xl font-bold">Aplikasi Kasir</h1>

                    <nav className="hidden md:flex items-center gap-6 text-sm">
                        <a href="#features" className="hover:text-neutral-500">
                            Fitur
                        </a>
                        <a href="#tech" className="hover:text-neutral-500">
                            Teknologi
                        </a>
                        <a href="#install" className="hover:text-neutral-500">
                            Instalasi
                        </a>
                        <a href="#authors" className="hover:text-neutral-500">
                            Authors
                        </a>
                    </nav>

                    <a
                        href="/login"
                        className="hidden md:inline-block bg-neutral-900 text-white px-4 py-2 rounded-md text-sm hover:bg-neutral-800 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                    >
                        Masuk
                    </a>
                </div>
            </header>

            {/* HERO */}
            <section className="px-6 py-20 md:py-28">
                <div className="mx-auto max-w-5xl text-center">
                    <h2 className="text-4xl md:text-6xl font-extrabold leading-tight">
                        Sistem Point of Sales Modern
                        <br />
                        <span className="text-neutral-600 dark:text-neutral-400">
                            Cepat, Stabil, dan Mudah Digunakan
                        </span>
                    </h2>

                    <p className="mt-5 text-lg text-neutral-600 dark:text-neutral-400 max-w-3xl mx-auto">
                        Aplikasi kasir berbasis web untuk warung & toko
                        kecil–menengah. Mendukung pencatatan transaksi, laporan,
                        manajemen produk, pelanggan, dan banyak lagi.
                    </p>

                    <div className="mt-8 flex justify-center gap-4">
                        <a
                            href="/register"
                            className="px-6 py-3 bg-neutral-900 text-white rounded-lg hover:bg-neutral-800 dark:bg-neutral-800 dark:hover:bg-neutral-700"
                        >
                            Mulai Sekarang
                        </a>
                        <a
                            href="https://github.com/aryadwiputra/point-of-sales"
                            target="_blank"
                            className="px-6 py-3 border border-neutral-300 dark:border-neutral-700 rounded-lg hover:bg-neutral-100 dark:hover:bg-neutral-800"
                        >
                            Lihat Repository
                        </a>
                    </div>
                </div>
            </section>

            {/* FITUR */}
            <section
                id="features"
                className="py-20 bg-neutral-50 dark:bg-neutral-800/30"
            >
                <div className="mx-auto max-w-6xl px-6">
                    <h3 className="text-3xl font-bold mb-10 text-center">
                        Fitur Utama
                    </h3>

                    <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        {[
                            "Authentikasi Admin",
                            "Manajemen User & Role",
                            "Manajemen Kategori",
                            "Manajemen Produk",
                            "Manajemen Pelanggan",
                            "Print Invoice",
                            "Laporan Penjualan",
                            "Laporan Keuntungan",
                            "Riwayat Order",
                        ].map((f, i) => (
                            <div
                                key={i}
                                className="p-5 border border-neutral-200 dark:border-neutral-700 rounded-lg bg-white dark:bg-neutral-900"
                            >
                                <p className="font-semibold">{f}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* TECH STACK */}
            <section id="tech" className="py-20">
                <div className="mx-auto max-w-6xl px-6 text-center">
                    <h3 className="text-3xl font-bold mb-4">Tech Stack</h3>
                    <p className="text-neutral-600 dark:text-neutral-400 mb-8">
                        Dibangun dengan teknologi modern yang cepat dan stabil.
                    </p>

                    <div className="flex flex-wrap justify-center gap-4 text-sm">
                        {[
                            "Laravel 12",
                            "Inertia 2",
                            "React",
                            "TailwindCSS",
                            "MySQL",
                        ].map((t, i) => (
                            <span
                                key={i}
                                className="px-4 py-2 border border-neutral-300 dark:border-neutral-700 rounded-lg"
                            >
                                {t}
                            </span>
                        ))}
                    </div>
                </div>
            </section>

            {/* INSTALLATION */}
            <section
                id="install"
                className="py-20 bg-neutral-50 dark:bg-neutral-800/30"
            >
                <div className="mx-auto max-w-6xl px-6">
                    <h3 className="text-3xl font-bold text-center mb-8">
                        Panduan Instalasi
                    </h3>

                    <div className="bg-white dark:bg-neutral-900 border border-neutral-200 dark:border-neutral-700 p-6 rounded-lg">
                        <pre className="text-sm whitespace-pre-wrap leading-relaxed">
                            {`git clone https://github.com/aryadwiputra/point-of-sales
cd point-of-sales
composer install
npm install
cp .env.example .env
php artisan key:generate

# Setup database di .env

php artisan migrate --seed
php artisan storage:link
npm run dev
php artisan serve`}
                        </pre>
                    </div>
                </div>
            </section>

            {/* AUTHORS */}
            <section id="authors" className="py-20">
                <div className="mx-auto max-w-6xl px-6 text-center">
                    <h3 className="text-3xl font-bold mb-8">Authors</h3>

                    <div className="flex flex-col sm:flex-row justify-center gap-6">
                        <div className="p-5 border border-neutral-200 dark:border-neutral-700 rounded-lg w-full sm:w-64 bg-white dark:bg-neutral-900">
                            <p className="font-semibold text-lg">
                                Arya Dwi Putra
                            </p>
                            <p className="text-sm text-neutral-500">
                                Developer
                            </p>
                        </div>
                        <div className="p-5 border border-neutral-200 dark:border-neutral-700 rounded-lg w-full sm:w-64 bg-white dark:bg-neutral-900">
                            <p className="font-semibold text-lg">
                                Rafi Taufiqurrahman
                            </p>
                            <p className="text-sm text-neutral-500">
                                Contributor
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {/* FOOTER */}
            <footer className="py-6 border-t border-neutral-200 dark:border-neutral-800 text-center text-sm text-neutral-500">
                © {new Date().getFullYear()} Aplikasi Kasir — Dibuat oleh Arya
                Dwi Putra
            </footer>
        </div>
    );
}

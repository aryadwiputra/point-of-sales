import { Head, usePage } from "@inertiajs/react";
import PublicLayout from "@/Layouts/PublicLayout";

export default function RefundPolicy() {
    const { brand } = usePage().props;
    const name = brand?.name || "Bihaju POS";
    const email = brand?.supportEmail || "support@bihajupos.id";
    return (
        <PublicLayout>
            <Head title={`Kebijakan Pengembalian — ${name}`} />
            <section className="pt-20 pb-16 px-6">
                <div className="max-w-3xl mx-auto prose prose-slate dark:prose-invert">
                    <h1>Kebijakan Pengembalian Dana & Pembatalan</h1>
                    <p>Kebijakan ini berlaku untuk langganan dan add-on {name}, kecuali dinyatakan lain dalam perjanjian tertulis.</p>
                    <h2>1. Langganan & Siklus Tagihan</h2>
                    <p>Langganan ditagih sesuai siklus (mis. bulanan/tahunan) dan paket yang dipilih. Aktivasi atau perpanjangan mengikuti konfirmasi pembayaran yang berhasil.</p>
                    <h2>2. Pembatalan</h2>
                    <p>Anda dapat membatalkan perpanjangan sebelum periode berikutnya dimulai. Akses umumnya tetap berlaku hingga akhir periode yang sudah dibayar, sesuai ketentuan paket.</p>
                    <h2>3. Pengembalian Dana</h2>
                    <p>Biaya yang telah dibayarkan umumnya tidak dapat dikembalikan (non-refundable), kecuali diwajibkan oleh hukum yang berlaku atau disepakati secara tertulis. Untuk kasus khusus (mis. kesalahan penagihan ganda yang terverifikasi), hubungi dukungan kami.</p>
                    <h2>4. Add-on</h2>
                    <p>Pembelian/aktivasi add-on mengikuti ketentuan paket dan periode yang berlaku. Hak akses add-on bergantung pada entitlement dan status langganan tenant.</p>
                    <h2>5. Kontak</h2>
                    <p>Untuk pertanyaan pembatalan atau penagihan, hubungi <a href={`mailto:${email}`}>{email}</a> dengan menyertakan detail akun/tenant dan bukti pembayaran.</p>
                </div>
            </section>
        </PublicLayout>
    );
}

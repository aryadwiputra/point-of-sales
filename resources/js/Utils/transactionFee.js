// transactionFee — preview biaya transaksi untuk paket "bayar per transaksi".
// Rumus ini identik dengan App\Services\TransactionFeeService (server).
// Nilai akhir SELALU dihitung ulang oleh server saat checkout; ini hanya
// untuk menampilkan estimasi sebelum kasir menekan "Selesaikan Transaksi".

/**
 * @param {object|null} config — props Inertia `transactionFee` (null = paket langganan biasa)
 * @param {number} amount — grand total transaksi
 * @param {string[]|string} methods — metode bayar yang terlibat (split → banyak)
 * @returns {number} biaya dalam Rupiah (0 bila tidak berlaku)
 */
export function calculateTransactionFee(config, amount, methods = []) {
    if (!config || !Number(amount) || Number(amount) <= 0) return 0;

    const allowed = Array.isArray(config.fee_methods) ? config.fee_methods : [];
    if (allowed.length > 0) {
        const list = Array.isArray(methods) ? methods : [methods];
        if (!list.some((method) => method && allowed.includes(method))) return 0;
    }

    let fee =
        config.fee_type === "fixed"
            ? Number(config.fee_value || 0)
            : (Number(amount) * Number(config.fee_value || 0)) / 100;

    const min = Number(config.fee_min || 0);
    const max = config.fee_max != null ? Number(config.fee_max) : 0;
    if (min > 0 && fee < min) fee = min;
    if (max > 0 && fee > max) fee = max;

    switch (config.fee_rounding) {
        case "up":
            return Math.ceil(fee);
        case "down":
            return Math.floor(fee);
        default:
            return Math.round(fee);
    }
}

/**
 * Ringkuman singkat untuk ditampilkan di kartu paket:
 * "1,5% / transaksi (min Rp500, maks Rp2.000)"
 */
export function describeFee(config) {
    if (!config) return "";

    const value =
        config.fee_type === "fixed"
            ? formatRupiah(config.fee_value)
            : `${Number(config.fee_value).toLocaleString("id-ID")}%`;

    const extras = [];
    if (Number(config.fee_min) > 0) extras.push(`min ${formatRupiah(config.fee_min)}`);
    if (config.fee_max != null && Number(config.fee_max) > 0)
        extras.push(`maks ${formatRupiah(config.fee_max)}`);

    return extras.length > 0
        ? `${value} / transaksi (${extras.join(", ")})`
        : `${value} / transaksi`;
}

function formatRupiah(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

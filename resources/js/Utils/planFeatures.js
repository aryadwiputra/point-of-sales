// planFeatures — simpan sebagai snake_case (dipakai gating Plan::hasFeature),
// tampilkan sebagai teks normal.

const ACRONYMS = {
    pos: 'POS',
    crm: 'CRM',
    api: 'API',
    wa: 'WhatsApp',
    qr: 'QR',
    qris: 'QRIS',
    ppn: 'PPN',
    sku: 'SKU',
    sla: 'SLA',
    db: 'DB',
    ui: 'UI',
    url: 'URL',
    sms: 'SMS',
    id: 'ID',
};

/**
 * "Multi Outlet" / "multi outlet" / "Multi_Outlet" -> "multi_outlet"
 * Dipakai saat menyimpan agar gating (in_array strict) tetap cocok.
 */
export function normalizeFeature(input) {
    return String(input || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
}

/**
 * "basic_pos" -> "Basic POS", "multi_wa" -> "Multi WhatsApp"
 * Dipakai saat menampilkan agar user melihat teks normal, bukan snake_case.
 */
export function formatFeature(code) {
    const normalized = String(code || '').trim();
    if (!normalized) return '';

    return normalized
        .split('_')
        .filter(Boolean)
        .map((word) => ACRONYMS[word] ?? word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

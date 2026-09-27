// ponytail: raw ESC/POS byte generator — no heavy lib, 58mm/80mm + cutter + drawer kick
// Upgrade path: image/logo support, codepage mapping if store sells non-ASCII heavy.

const ESC = 0x1b;
const GS = 0x1d;

function textBytes(text) {
    return Array.from(new TextEncoder().encode(text));
}

function buildReceiptBytes(data, paperSize = "80mm") {
    const width = paperSize === "58mm" ? 32 : 48;
    const bytes = [];

    // Init: ESC @, codepage off, no upside down
    bytes.push(ESC, 0x40);

    const center = (text) => {
        const pad = Math.max(0, Math.floor((width - text.length) / 2));
        bytes.push(...textBytes(" ".repeat(pad) + text + "\n"));
    };
    const leftRight = (l, r) => {
        const space = Math.max(1, width - l.length - r.length);
        bytes.push(...textBytes(l + " ".repeat(space) + r + "\n"));
    };
    const line = () => {
        bytes.push(...textBytes("-".repeat(width) + "\n"));
    };
    const emphasize = (on) => {
        bytes.push(ESC, 0x45, on ? 0x01 : 0x00);
    };

    emphasize(true);
    center(data.store_name || "TOKO");
    emphasize(false);
    if (data.store_address) center(data.store_address.slice(0, width));
    if (data.store_phone) center(`Telp: ${data.store_phone}`);
    line();
    leftRight(`#${data.invoice}`, data.created_at || "");
    if (data.cashier) leftRight("Kasir", data.cashier);
    if (data.customer_name) leftRight("Pelanggan", data.customer_name);
    if (data.order_type_label) leftRight("Tipe", data.order_type_label);
    line();

    for (const item of data.items || []) {
        const name = `${item.qty}x ${item.name}`.slice(0, width);
        bytes.push(...textBytes(name + "\n"));
        leftRight("", data.money(item.price));
    }

    line();
    leftRight("Subtotal", data.money(data.subtotal));
    if (data.discount_total > 0) leftRight("Diskon", `-${data.money(data.discount_total)}`);
    if (data.tax_total > 0) leftRight("PPN", data.money(data.tax_total));
    if (data.shipping_cost > 0) leftRight("Ongkir", data.money(data.shipping_cost));
    emphasize(true);
    leftRight("TOTAL", data.money(data.grand_total));
    emphasize(false);
    line();
    if (data.payment_method_label) leftRight("Bayar", data.payment_method_label);
    if (data.cash_received != null) leftRight("Tunai", data.money(data.cash_received));
    if (data.change > 0) leftRight("Kembali", data.money(data.change));
    if (data.note) bytes.push(...textBytes(`Cat: ${data.note.slice(0, width * 2)}\n`));
    line();
    center(data.footer || "Terima kasih!");
    bytes.push(...textBytes("\n\n"));

    // Cut: GS V 66 0
    bytes.push(GS, 0x56, 0x42, 0x00);
    return new Uint8Array(bytes);
}

function drawerKickBytes(pin = 2) {
    // ESC p m t1 t2 — kick drawer pin 2, ~50ms pulse
    return new Uint8Array([ESC, 0x70, pin === 5 ? 0x01 : 0x00, 0x19, 0xfa]);
}

export async function requestPrinter() {
    if (!navigator.usb) {
        throw new Error("WebUSB tidak didukung browser ini. Gunakan Chrome/Edge.");
    }
    const device = await navigator.usb.requestDevice({
        filters: [],
    });
    try {
        await configurePrinter(device);
        getPrinter.cached = device;
        return device;
    } catch (error) {
        if (device.opened) await device.close();
        throw error;
    }
}

async function configurePrinter(device) {
    await device.open();
    if (device.configuration === null) {
        await device.selectConfiguration(1);
    }

    let interfaceNumber = null;
    let endpointNumber = null;
    const interfaces = device.configuration?.interfaces ?? [];
    const candidates = interfaces.flatMap((iface) =>
        iface.alternates.map((alt) => ({ iface, alt }))
    );
    const candidate =
        candidates.find(({ alt }) => alt.interfaceClass === 7) ||
        candidates.find(({ alt }) => alt.endpoints.some((e) => e.direction === "out"));
    const endpoint = candidate?.alt.endpoints.find((e) => e.direction === "out");
    if (candidate && endpoint) {
        interfaceNumber = candidate.iface.interfaceNumber;
        endpointNumber = endpoint.endpointNumber;
    }
    if (interfaceNumber === null || endpointNumber === null) {
        throw new Error("Tidak ditemukan interface output printer pada perangkat ini.");
    }
    await device.claimInterface(interfaceNumber);
    device._endpoint = endpointNumber;
}

async function getPrinter() {
    if (getPrinter.cached && getPrinter.cached.opened) {
        return getPrinter.cached;
    }
    // Re-attach to previously granted device
    if (!navigator.usb) throw new Error("WebUSB tidak didukung browser ini.");
    const devices = await navigator.usb.getDevices();
    if (devices.length === 0) return null;
    const device = devices[0];
    try {
        await configurePrinter(device);
        getPrinter.cached = device;
        return device;
    } catch (error) {
        if (device.opened) await device.close();
        throw error;
    }
}

export async function printBytes(bytes) {
    const device = await getPrinter();
    if (!device) throw new Error("Printer belum terhubung.");
    if (!device.opened || !device._endpoint) {
        throw new Error("Printer tidak siap menerima data. Hubungkan ulang printer.");
    }
    try {
        await device.transferOut(device._endpoint, bytes);
    } catch {
        getPrinter.cached = undefined;
        throw new Error("Gagal mengirim data ke printer. Periksa koneksi printer.");
    }
}

export async function printReceipt(data, paperSize) {
    await printBytes(buildReceiptBytes(data, paperSize));
}

export async function kickDrawer() {
    await printBytes(drawerKickBytes());
}

export async function disconnectPrinter() {
    if (getPrinter.cached) {
        try {
            await getPrinter.cached.close();
        } finally {
            getPrinter.cached = undefined;
        }
    }
}

export function hasCachedPrinter() {
    return Boolean(getPrinter.cached?.opened);
}

export { buildReceiptBytes, drawerKickBytes };

import React, { useEffect, useRef, useState } from "react";
import { IconCamera, IconBarcode, IconX } from "@tabler/icons-react";

export default function BarcodeScanner({ onScan, onClose }) {
    const scannerRef = useRef(null);
    const [scanning, setScanning] = useState(false);
    const [error, setError] = useState("");
    const html5QrCodeRef = useRef(null);

    useEffect(() => {
        let mounted = true;

        const start = async () => {
            try {
                const { Html5Qrcode } = await import("html5-qrcode");
                if (!mounted) return;

                const scanner = new Html5Qrcode("barcode-scanner-element");
                html5QrCodeRef.current = scanner;
                setScanning(true);
                setError("");

                await scanner.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 250, height: 150 } },
                    (decodedText) => {
                        if (mounted) {
                            scanner.stop().catch(() => {});
                            setScanning(false);
                            onScan(decodedText);
                        }
                    },
                    () => {}
                );
            } catch (err) {
                if (mounted) {
                    setError(err?.message || "Kamera tidak tersedia atau ditolak.");
                    setScanning(false);
                }
            }
        };

        start();

        return () => {
            mounted = false;
            if (html5QrCodeRef.current) {
                html5QrCodeRef.current.stop().catch(() => {});
            }
        };
    }, [onScan]);

    return (
        <div className="fixed inset-0 z-50 bg-black/90 flex flex-col">
            <div className="flex items-center justify-between p-4 text-white">
                <span className="text-sm font-medium">
                    {scanning ? "Arahkan ke barcode" : "Memulai kamera..."}
                </span>
                <button
                    type="button"
                    onClick={onClose}
                    className="p-2 rounded-lg hover:bg-white/10 transition-colors"
                >
                    <IconX size={24} />
                </button>
            </div>

            <div className="flex-1 flex items-center justify-center p-8">
                <div
                    id="barcode-scanner-element"
                    className="w-full max-w-sm aspect-square rounded-2xl overflow-hidden"
                />
            </div>

            {error && (
                <div className="p-4 text-center">
                    <p className="text-sm text-danger-400 mb-3">{error}</p>
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-6 py-2.5 rounded-xl bg-white/10 text-white text-sm font-medium hover:bg-white/20"
                    >
                        Tutup
                    </button>
                </div>
            )}

            <div className="p-4 text-center text-xs text-white/50">
                Atau tutup untuk input manual
            </div>
        </div>
    );
}

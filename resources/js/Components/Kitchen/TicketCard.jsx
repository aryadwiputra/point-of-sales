import React from "react";
import {
    IconArrowBackUp,
    IconCheck,
    IconClock,
    IconPlayerPlay,
    IconToolsKitchen2,
} from "@tabler/icons-react";

const ORDER_TYPE_LABELS = {
    dine_in: "Dine-in",
    takeaway: "Takeaway",
    delivery: "Delivery",
};

function elapsedLabel(iso, now) {
    if (!iso) {
        return "-";
    }

    const minutes = Math.max(0, Math.floor((now - new Date(iso).getTime()) / 60000));

    if (minutes < 60) {
        return `${minutes} mnt`;
    }

    return `${Math.floor(minutes / 60)} jam ${minutes % 60} mnt`;
}

function ItemActions({ item, busy, onItemStatus }) {
    if (item.status === "queued") {
        return (
            <button
                type="button"
                disabled={busy}
                onClick={() => onItemStatus(item.id, "cooking")}
                className="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
            >
                <IconPlayerPlay size={14} strokeWidth={2} />
                Mulai
            </button>
        );
    }

    if (item.status === "cooking") {
        return (
            <div className="flex flex-wrap gap-1">
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => onItemStatus(item.id, "ready")}
                    className="inline-flex items-center gap-1 rounded-lg bg-success-600 px-2.5 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                >
                    <IconCheck size={14} strokeWidth={2} />
                    Siap
                </button>
                <button
                    type="button"
                    disabled={busy}
                    onClick={() => onItemStatus(item.id, "queued")}
                    className="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50"
                >
                    <IconArrowBackUp size={14} strokeWidth={2} />
                    Antre
                </button>
            </div>
        );
    }

    if (item.status === "ready") {
        return (
            <button
                type="button"
                disabled={busy}
                onClick={() => onItemStatus(item.id, "cooking")}
                className="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50"
            >
                <IconArrowBackUp size={14} strokeWidth={2} />
                Masak lagi
            </button>
        );
    }

    return null;
}

export default function TicketCard({
    ticket,
    now,
    busy,
    onItemStatus,
    onTicketCooking,
    onTicketReady,
    onTicketClear,
}) {
    const activeItems = (ticket.items ?? []).filter((item) => item.status !== "cancelled");
    const hasQueued = activeItems.some((item) => item.status === "queued");
    const hasInProgress = activeItems.some((item) => ["queued", "cooking"].includes(item.status));
    const isReady = ticket.status === "ready";
    const orderLabel = ticket.table_label || ORDER_TYPE_LABELS[ticket.order_type] || ticket.order_type;

    return (
        <article className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <header className="flex items-start justify-between gap-2">
                <div>
                    <p className="text-lg font-bold text-slate-900">#{ticket.id}</p>
                    <p className="text-sm font-medium text-slate-700">{orderLabel}</p>
                </div>
                <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">
                    {ticket.station?.name}
                </span>
            </header>

            <div className="mt-1 flex items-center gap-1 text-xs text-slate-500">
                <IconClock size={14} strokeWidth={1.8} />
                <span>{elapsedLabel(ticket.placed_at, now)}</span>
            </div>

            <ul className="mt-3 space-y-2">
                {activeItems.map((item) => (
                    <li
                        key={item.id}
                        className={`rounded-lg border p-2 ${
                            item.status === "ready"
                                ? "border-success-200 bg-success-50"
                                : item.status === "cooking"
                                  ? "border-warning-200 bg-warning-50"
                                  : "border-slate-200 bg-slate-50"
                        }`}
                    >
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <p className="text-sm font-semibold text-slate-900">
                                    <span className="text-primary-700">{item.qty}x</span> {item.product_name}
                                </p>
                                {item.modifiers_text ? (
                                    <p className="text-xs text-slate-600">{item.modifiers_text}</p>
                                ) : null}
                                {item.note ? (
                                    <p className="text-xs italic text-slate-600">Catatan: {item.note}</p>
                                ) : null}
                            </div>
                            <ItemActions item={item} busy={busy} onItemStatus={onItemStatus} />
                        </div>
                    </li>
                ))}
            </ul>

            <footer className="mt-3 flex flex-wrap gap-2">
                {hasQueued ? (
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => onTicketCooking(ticket.id)}
                        className="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    >
                        Mulai semua
                    </button>
                ) : null}
                {hasInProgress ? (
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => onTicketReady(ticket.id)}
                        className="rounded-lg bg-success-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    >
                        Siap semua
                    </button>
                ) : null}
                {isReady ? (
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() => onTicketClear(ticket.id)}
                        className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 disabled:opacity-50"
                    >
                        Bersihkan
                    </button>
                ) : null}
            </footer>
        </article>
    );
}
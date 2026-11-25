import React, { useCallback, useEffect, useRef, useState } from "react";
import { Head, Link } from "@inertiajs/react";
import axios from "axios";
import toast from "react-hot-toast";
import { IconRefresh, IconSettings, IconToolsKitchen2 } from "@tabler/icons-react";
import DashboardLayout from "@/Layouts/DashboardLayout";
import StationTabs from "@/Components/Kitchen/StationTabs";
import TicketCard from "@/Components/Kitchen/TicketCard";

const POLL_INTERVAL = 5000;

const COLUMNS = [
    { key: "queued", title: "Antre" },
    { key: "cooking", title: "Dimasak" },
    { key: "ready", title: "Siap" },
];

function columnFor(ticket) {
    if (ticket.status === "ready") {
        return "ready";
    }

    if ((ticket.items ?? []).some((item) => item.status === "queued")) {
        return "queued";
    }

    return "cooking";
}

export default function Index({
    stations = [],
    selectedStationId = null,
    enabled = true,
    canManage = false,
}) {
    const [stationId, setStationId] = useState(selectedStationId);
    const [tickets, setTickets] = useState([]);
    const [boardEnabled, setBoardEnabled] = useState(enabled);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [busyKey, setBusyKey] = useState(null);
    const [lastUpdated, setLastUpdated] = useState(null);
    const [now, setNow] = useState(() => Date.now());
    const [newCount, setNewCount] = useState(0);
    const knownIds = useRef(null);

    const fetchBoard = useCallback(async () => {
        try {
            const { data } = await axios.get(route("kitchen.board"), {
                params: stationId ? { station: stationId } : {},
            });

            const nextTickets = data.tickets ?? [];
            const nextIds = nextTickets.map((ticket) => ticket.id);

            if (knownIds.current) {
                const added = nextIds.filter((id) => !knownIds.current.has(id)).length;
                if (added > 0) {
                    setNewCount((count) => count + added);
                }
            }

            knownIds.current = new Set(nextIds);
            setTickets(nextTickets);
            setBoardEnabled(Boolean(data.enabled));
            setLastUpdated(new Date());
            setError("");
        } catch (requestError) {
            setError(requestError?.response?.data?.message ?? "Gagal memuat papan dapur.");
        } finally {
            setLoading(false);
        }
    }, [stationId]);

    useEffect(() => {
        fetchBoard();
    }, [fetchBoard]);

    useEffect(() => {
        const timer = window.setInterval(() => {
            if (!document.hidden) {
                fetchBoard();
            }
        }, POLL_INTERVAL);

        const onVisibilityChange = () => {
            if (!document.hidden) {
                fetchBoard();
            }
        };

        document.addEventListener("visibilitychange", onVisibilityChange);

        return () => {
            window.clearInterval(timer);
            document.removeEventListener("visibilitychange", onVisibilityChange);
        };
    }, [fetchBoard]);

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        if (newCount === 0) {
            return undefined;
        }

        const timer = window.setTimeout(() => setNewCount(0), 10000);

        return () => window.clearTimeout(timer);
    }, [newCount]);

    const runAction = async (key, request) => {
        setBusyKey(key);

        try {
            await request();
            await fetchBoard();
            toast.success("Status diperbarui.");
        } catch (requestError) {
            toast.error(requestError?.response?.data?.message ?? "Gagal memperbarui status.");
        } finally {
            setBusyKey(null);
        }
    };

    const updateItemStatus = (itemId, status) =>
        runAction(`item-${itemId}`, () => axios.patch(route("kitchen.items.status", itemId), { status }));

    const startTicket = (ticketId) =>
        runAction(`ticket-${ticketId}`, () => axios.post(route("kitchen.tickets.cooking", ticketId)));

    const readyTicket = (ticketId) =>
        runAction(`ticket-${ticketId}`, () => axios.post(route("kitchen.tickets.ready", ticketId)));

    const clearTicket = (ticketId) =>
        runAction(`ticket-${ticketId}`, () => axios.post(route("kitchen.tickets.clear", ticketId)));

    const grouped = { queued: [], cooking: [], ready: [] };
    tickets.forEach((ticket) => grouped[columnFor(ticket)].push(ticket));

    return (
        <DashboardLayout>
            <Head title="Dapur" />

            <div className="space-y-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Dapur</h1>
                        <p className="text-sm text-slate-600">Papan pesanan dapur diperbarui otomatis setiap 5 detik.</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {newCount > 0 ? (
                            <span className="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700">
                                {newCount} tiket baru
                            </span>
                        ) : null}
                        <button
                            type="button"
                            onClick={fetchBoard}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <IconRefresh size={16} strokeWidth={1.8} />
                            Segarkan
                        </button>
                        {canManage ? (
                            <Link
                                href={route("kitchen.stations.index")}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            >
                                <IconSettings size={16} strokeWidth={1.8} />
                                Kelola station
                            </Link>
                        ) : null}
                    </div>
                </div>

                <StationTabs stations={stations} selectedStationId={stationId} onSelect={setStationId} />

                {!boardEnabled ? (
                    <div className="rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 text-sm text-warning-800">
                        Kitchen display sedang dinonaktifkan. Tiket baru tidak dibuat sampai admin mengaktifkannya kembali.
                    </div>
                ) : null}

                {error ? (
                    <div className="rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-800">
                        {error}
                    </div>
                ) : null}

                {stations.length === 0 ? (
                    <div className="rounded-xl border border-slate-200 bg-white p-8 text-center">
                        <IconToolsKitchen2 size={36} strokeWidth={1.5} className="mx-auto text-slate-400" />
                        <p className="mt-3 font-semibold text-slate-800">Belum ada station dapur.</p>
                        <p className="text-sm text-slate-600">
                            Hubungi admin untuk memetakan kategori menu ke station dapur.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-3">
                        {COLUMNS.map((column) => (
                            <section key={column.key} className="rounded-xl bg-slate-100 p-3">
                                <h2 className="flex items-center justify-between text-sm font-bold uppercase tracking-wide text-slate-700">
                                    {column.title}
                                    <span className="rounded-full bg-white px-2 py-0.5 text-xs text-slate-600">
                                        {grouped[column.key].length}
                                    </span>
                                </h2>
                                <div className="mt-3 space-y-3">
                                    {loading && grouped[column.key].length === 0 ? (
                                        <p className="text-sm text-slate-500">Memuat...</p>
                                    ) : null}
                                    {grouped[column.key].map((ticket) => (
                                        <TicketCard
                                            key={ticket.id}
                                            ticket={ticket}
                                            now={now}
                                            busy={busyKey !== null}
                                            onItemStatus={updateItemStatus}
                                            onTicketCooking={startTicket}
                                            onTicketReady={readyTicket}
                                            onTicketClear={clearTicket}
                                        />
                                    ))}
                                    {!loading && grouped[column.key].length === 0 ? (
                                        <p className="text-sm text-slate-500">Tidak ada tiket.</p>
                                    ) : null}
                                </div>
                            </section>
                        ))}
                    </div>
                )}

                <p className="text-xs text-slate-500">
                    Terakhir diperbarui: {lastUpdated ? lastUpdated.toLocaleTimeString("id-ID") : "-"}
                </p>
            </div>
        </DashboardLayout>
    );
}
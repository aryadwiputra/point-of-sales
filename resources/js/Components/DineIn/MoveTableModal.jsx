import React from "react";
import { router } from "@inertiajs/react";
import Modal from "@/Components/Dashboard/Modal";

export default function MoveTableModal({ show, session, tables, onClose }) {
    const targets = (tables ?? []).filter(
        (table) => table.id !== session?.dine_table_id && table.is_active && !table.occupancy
    );

    const move = (table) => {
        router.post(route("dine-sessions.move", session.id), {
            dine_table_id: table.id,
        }, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal show={show} onClose={onClose} title="Pindah Meja" maxWidth="md">
            <div className="space-y-2">
                {targets.length === 0 && (
                    <p className="text-sm text-slate-500">Tidak ada meja kosong yang tersedia.</p>
                )}
                {targets.map((table) => (
                    <button
                        key={table.id}
                        type="button"
                        onClick={() => move(table)}
                        className="flex w-full items-center justify-between rounded-xl border border-slate-200 p-3 text-left dark:border-slate-700"
                    >
                        <span className="text-sm font-medium">{table.name}</span>
                        <span className="text-xs text-slate-500">{table.area?.name ?? "Tanpa area"}</span>
                    </button>
                ))}
            </div>
        </Modal>
    );
}
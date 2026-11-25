import React from "react";
import { router } from "@inertiajs/react";
import Modal from "@/Components/Dashboard/Modal";
import Button from "@/Components/Dashboard/Button";

export default function MergeTableModal({ show, session, sessions, onClose }) {
    const targets = (sessions ?? []).filter(
        (candidate) => candidate.id !== session?.id && candidate.bills?.some((b) => b.status === "open")
    );

    const merge = (target) => {
        router.post(route("dine-sessions.merge", session.id), {
            target_session_id: target.id,
        }, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal show={show} onClose={onClose} title="Gabung Meja" maxWidth="md">
            <div className="space-y-2">
                {targets.length === 0 && (
                    <p className="text-sm text-slate-500">Tidak ada meja terisi lain yang bisa digabung.</p>
                )}
                {targets.map((target) => (
                    <button
                        key={target.id}
                        type="button"
                        onClick={() => merge(target)}
                        className="flex w-full items-center justify-between rounded-xl border border-slate-200 p-3 text-left dark:border-slate-700"
                    >
                        <span className="text-sm font-medium">{target.table?.name ?? `Sesi ${target.id}`}</span>
                        <span className="text-xs text-slate-500">{target.bills?.length ?? 0} bill</span>
                    </button>
                ))}
            </div>
        </Modal>
    );
}
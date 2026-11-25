import React from "react";
import { useForm } from "@inertiajs/react";
import Modal from "@/Components/Dashboard/Modal";
import Input from "@/Components/Dashboard/Input";
import Button from "@/Components/Dashboard/Button";

export default function OpenTableModal({ show, table, onClose, onSuccess }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        guest_count: 2,
        notes: "",
    });

    const submit = (event) => {
        event.preventDefault();
        post(route("dine-sessions.store", table.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onSuccess?.();
            },
        });
    };

    return (
        <Modal
            show={show}
            onClose={onClose}
            title={table ? `Buka Meja ${table.name}` : "Buka Meja"}
            maxWidth="md"
        >
            <form onSubmit={submit} className="space-y-4">
                <Input
                    label="Jumlah Tamu"
                    type="number"
                    min="1"
                    value={data.guest_count}
                    onChange={(e) => setData("guest_count", parseInt(e.target.value) || 1)}
                    errors={errors.guest_count}
                />
                <Input
                    label="Catatan"
                    value={data.notes}
                    onChange={(e) => setData("notes", e.target.value)}
                    errors={errors.notes}
                    placeholder="Opsional"
                />
                <div className="flex justify-end gap-3 pt-2">
                    <Button type="button" label="Batal" onClick={onClose} />
                    <Button
                        type="submit"
                        label="Buka Meja"
                        disabled={processing}
                        className="bg-primary-500 hover:bg-primary-600 text-white"
                    />
                </div>
            </form>
        </Modal>
    );
}
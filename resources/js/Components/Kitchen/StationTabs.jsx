import React from "react";

export default function StationTabs({ stations = [], selectedStationId = null, onSelect }) {
    const buttonClass = (isActive) =>
        `shrink-0 rounded-lg border px-3 py-2 text-sm font-medium transition ${
            isActive
                ? "border-primary-600 bg-primary-600 text-white"
                : "border-slate-300 bg-white text-slate-700 hover:bg-slate-50"
        }`;

    return (
        <div className="flex gap-2 overflow-x-auto pb-1">
            <button type="button" onClick={() => onSelect(null)} className={buttonClass(selectedStationId === null)}>
                Semua station
            </button>
            {stations.map((station) => (
                <button
                    key={station.id}
                    type="button"
                    onClick={() => onSelect(station.id)}
                    className={buttonClass(selectedStationId === station.id)}
                >
                    {station.name}
                    {station.is_active === false ? (
                        <span className="ml-1 text-xs opacity-75">(nonaktif)</span>
                    ) : null}
                </button>
            ))}
        </div>
    );
}
import React, { useState, useEffect, useRef } from "react";
import { Menu, Transition } from "@headlessui/react";
import {
    IconBell,
    IconDots,
    IconCircleCheck,
    IconPackage,
    IconReceipt,
    IconCurrencyDollar,
} from "@tabler/icons-react";
import { usePage, router } from "@inertiajs/react";

export default function Notification() {
    const {
        lowStockNotifications = [],
        expiringBatchNotifications = [],
        receivableNotifications = [],
        payableNotifications = [],
    } = usePage().props;

    const mapItems = (items) =>
        items.map((item) => ({
            ...item,
            type: item.type || "stock",
            icon:
                item.type === "receivable" ? (
                    <span className="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                        <IconReceipt size={18} />
                    </span>
                ) : item.type === "payable" ? (
                    <span className="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                        <IconCurrencyDollar size={18} />
                    </span>
                ) : (
                    <span className="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center">
                        <IconPackage size={18} />
                    </span>
                ),
        }));

    const mergeData = () => [
        ...mapItems(
            lowStockNotifications.map((n) => ({
                ...n,
                id: `stock-${n.id}`,
                originalId: n.id,
                title: `Stok habis: ${n.title}`,
                subtitle: `Stok: ${n.stock}`,
                type: "stock",
            }))
        ),
        ...mapItems(
            expiringBatchNotifications.map((n) => ({
                ...n,
                id: `batch-${n.id}`,
                title: `Batch kedaluwarsa: ${n.title}`,
                subtitle: `${n.batch_number} • Stok: ${n.stock}`,
                type: "stock",
                noAck: true,
            }))
        ),
        ...mapItems(
            receivableNotifications.map((n) => ({
                ...n,
                id: `recv-${n.id}`,
                type: "receivable",
            }))
        ),
        ...mapItems(
            payableNotifications.map((n) => ({
                ...n,
                id: `pay-${n.id}`,
                type: "payable",
            }))
        ),
    ];

    const [data, setData] = useState(mergeData());

    const [isMobile, setIsMobile] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const notificationRef = useRef(null);

    const handleClickOutside = (event) => {
        if (notificationRef.current && !notificationRef.current.contains(event.target)) {
            setIsOpen(false);
        }
    };

    useEffect(() => {
        const handleResize = () => {
            setIsMobile(window.innerWidth <= 768);
        };

        window.addEventListener("resize", handleResize);
        window.addEventListener("mousedown", handleClickOutside);
        handleResize();

        return () => {
            window.removeEventListener("resize", handleResize);
            window.removeEventListener("mousedown", handleClickOutside);
        };
    }, []);

    // Sync when low stock changes (e.g., restocked items disappear)
    useEffect(() => {
        setData(mergeData());
    },         [lowStockNotifications, expiringBatchNotifications, receivableNotifications, payableNotifications]);

    const handleMarkRead = (id) => {
        const item = data.find((d) => d.id === id);
        if (item?.noAck) {
            return;
        }
        setData((prev) => prev.filter((item) => item.id !== id));
        if (item?.type === "stock") {
            router.post(
                route("notifications.stock.read"),
                { product_id: item.originalId || id },
                { preserveScroll: true, preserveState: true }
            );
        }
    };

    const handleMarkAllRead = () => {
        setData([]);
        router.post(
            route("notifications.stock.readAll"),
            {},
            { preserveScroll: true, preserveState: true }
        );
    };

    const badgeCount = data.length;

    const NotificationList = () => (
        <div className="flex flex-col gap-3 items-start max-h-80 overflow-y-auto pr-1">
            {badgeCount === 0 && (
                <div className="text-sm text-gray-500 dark:text-gray-400">
                    Tidak ada notifikasi
                </div>
            )}
            {data.map((item) => (
                <div
                    className="flex items-center justify-between w-full p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-primary-200 dark:hover:border-primary-800 hover:shadow transition-all"
                    key={item.id}
                >
                    <div className="flex items-center gap-4">
                        {item.icon}
                        <div>
                            <div className="font-semibold text-sm md:text-base text-gray-700 dark:text-gray-200">
                                {item.title}
                            </div>
                            <div className="text-gray-500 text-xs md:text-sm">
                                {item.subtitle} {item.time && `• ${item.time}`}
                            </div>
                        </div>
                    </div>
                    <button
                        onClick={() => handleMarkRead(item.id)}
                        disabled={item.noAck}
                        className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold text-primary-600 hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-primary-900/30 border border-transparent hover:border-primary-200 dark:hover:border-primary-800 ${item.noAck ? "opacity-50 cursor-default" : ""}`}
                    >
                        <IconCircleCheck size={16} />
                        Dibaca
                    </button>
                </div>
            ))}
        </div>
    );

    return (
        <>
            {isMobile === false ? (
                <Menu className="relative z-50" as="div">
                    <Menu.Button className="flex items-center rounded-2xl group px-3 py-2.5 border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:shadow transition">
                        <div className="absolute text-[11px] font-semibold border border-rose-500/40 bg-rose-500/10 text-rose-500 hover:bg-rose-500/20 top-0 -right-2 rounded-md px-2 py-0.5 group-hover:scale-110 duration-200 ease-in">
                            {badgeCount}
                        </div>
                        <IconBell
                            strokeWidth={1.5}
                            size={22}
                            className="text-gray-700 dark:text-gray-400"
                        />
                    </Menu.Button>
                    <Transition
                        enter="transition duration-100 ease-out"
                        enterFrom="transform scale-95 opacity-0"
                        enterTo="transform scale-100 opacity-100"
                        leave="transition duration-75 ease-out"
                        leaveFrom="transform scale-100 opacity-100"
                        leaveTo="transform scale-95 opacity-0"
                    >
                        <Menu.Items className="absolute rounded-2xl w-[600px] max-w-[94vw] border md:right-0 z-[100] bg-white dark:bg-gray-950 dark:border-gray-900 shadow-2xl">
                            <div className="flex justify-between items-center gap-2 p-4 border-b dark:border-gray-900">
                                <div className="text-xl font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                                    Notifikasi
                                </div>
                                <div className="flex items-center gap-2">
                                    {badgeCount > 0 && (
                                        <button
                                            onClick={handleMarkAllRead}
                                            className="text-xs font-semibold px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700"
                                        >
                                            Tandai dibaca
                                        </button>
                                    )}
                                    <IconDots className="text-gray-500 dark:text-gray-200" size={24} />
                                </div>
                            </div>
                            <div className="p-4">
                                <NotificationList />
                            </div>
                        </Menu.Items>
                    </Transition>
                </Menu>
            ) : (
                <div ref={notificationRef}>
                    <button
                        className="flex items-center rounded-xl group p-2 relative border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900"
                        onClick={() => setIsOpen(!isOpen)}
                    >
                        <div className="absolute text-[10px] font-semibold border border-rose-500/40 bg-rose-500/10 text-rose-500 hover:bg-rose-500/20 top-0 -right-2 rounded-md px-1.5 py-0.5 group-hover:scale-110 duration-200 ease-in">
                            {badgeCount}
                        </div>
                        <IconBell strokeWidth={1.5} size={20} className="text-gray-500 dark:text-gray-400" />
                    </button>
                    <div
                        className={`${
                            isOpen ? "translate-x-0 opacity-100" : "translate-x-full"
                        } fixed top-0 right-0 z-50 w-[300px] h-full transition-all duration-300 transform border-l bg-white dark:bg-gray-950 dark:border-gray-900`}
                    >
                        <div className="flex justify-between items-center gap-2 p-4 border-b mt-2 dark:border-gray-900 ">
                            <div className="text-base font-bold text-gray-500 dark:text-gray-400 ">
                                Notifications
                            </div>
                            <IconDots className="text-gray-500 dark:text-gray-400" size={24} />
                        </div>
                        <div className="p-4">
                            <div className="flex flex-col gap-3 items-start overflow-y-auto h-screen">
                                <NotificationList />
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

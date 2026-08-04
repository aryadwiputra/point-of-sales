import React from "react";
import { usePage, useForm } from "@inertiajs/react";
import { IconLanguage } from "@tabler/icons-react";
import { Menu, Transition } from "@headlessui/react";

export default function LanguageSwitcher() {
    const { locale } = usePage().props;
    const { post, processing } = useForm({
        locale: locale?.current || "id",
    });

    const handleChange = (e) => {
        post(route("language.switch", { locale: e }), {
            preserveScroll: true,
            onSuccess: () => {
                window.location.reload();
            },
        });
    };

    const languages = [
        { code: "id", name: "Indonesia", flag: "🇮🇩" },
        { code: "en", name: "English", flag: "🇬🇧" },
    ];

    const currentLang = languages.find((l) => l.code === locale?.current) || languages[0];

    return (
        <Menu as="div" className="relative">
            <Menu.Button
                className="flex items-center gap-2 p-2.5 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition-colors"
                title="Change Language"
            >
                <IconLanguage size={20} strokeWidth={1.5} />
                <span className="hidden lg:inline text-sm font-medium">{currentLang.code.toUpperCase()}</span>
            </Menu.Button>

            <Transition
                enter="transition ease-out duration-100"
                enterFrom="transform opacity-0 scale-95"
                enterTo="transform opacity-100 scale-100"
                leave="transition ease-in duration-75"
                leaveFrom="transform opacity-100 scale-100"
                leaveTo="transform opacity-0 scale-95"
            >
                <Menu.Items className="absolute right-0 mt-2 w-48 origin-top-right rounded-xl bg-white dark:bg-slate-800 shadow-lg ring-1 ring-slate-200 dark:ring-slate-700 focus:outline-none overflow-hidden">
                    <div className="p-1">
                        <div className="px-3 py-2 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            Select Language
                        </div>
                        {languages.map((lang) => (
                            <Menu.Item key={lang.code}>
                                {({ active }) => (
                                    <button
                                        onClick={() => handleChange(lang.code)}
                                        disabled={processing}
                                        className={`${
                                            active ? "bg-slate-100 dark:bg-slate-700" : ""
                                        } ${
                                            locale?.current === lang.code
                                                ? "bg-primary-50 dark:bg-primary-900/20"
                                                : ""
                                        } flex w-full items-center gap-3 px-3 py-2.5 text-sm text-slate-700 dark:text-slate-200 rounded-lg transition-colors disabled:opacity-50`}
                                    >
                                        <span className="text-lg">{lang.flag}</span>
                                        <span className="flex-1 text-left">{lang.name}</span>
                                        {locale?.current === lang.code && (
                                            <svg
                                                className="w-4 h-4 text-primary-600"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M5 13l4 4L19 7"
                                                />
                                            </svg>
                                        )}
                                    </button>
                                )}
                            </Menu.Item>
                        ))}
                    </div>
                </Menu.Items>
            </Transition>
        </Menu>
    );
}

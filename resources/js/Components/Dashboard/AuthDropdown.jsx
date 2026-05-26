import React, { useState, useRef, useEffect } from "react";
import { Menu, Transition } from "@headlessui/react";
import { Link, usePage } from "@inertiajs/react";
import { IconLogout, IconUserCog } from "@tabler/icons-react";
import { useForm } from "@inertiajs/react";
import MenuLink from "@/Utils/Menu";
import LinkItem from "./LinkItem";
import LinkItemDropdown from "./LinkItemDropdown";
export default function AuthDropdown({ auth, isMobile }) {
    // define usefrom
    const { post } = useForm();
    // define url from usepage
    const { url } = usePage();

    // define state isToggle
    const [isToggle, setIsToggle] = useState(false);
    // define state isOpen
    const [isOpen, setIsOpen] = useState(false);
    // define ref dropdown
    const dropdownRef = useRef(null);

    // define method handleClickOutside
    const handleClickOutside = (event) => {
        if (
            dropdownRef.current &&
            !dropdownRef.current.contains(event.target)
        ) {
            setIsToggle(false);
        }
    };

    // get menu from utils
    const menuNavigation = MenuLink();

    // define useEffect
    useEffect(() => {
        // add event listener
        window.addEventListener("mousedown", handleClickOutside);

        // remove event listener
        return () => {
            window.removeEventListener("mousedown", handleClickOutside);
        };
    }, []);

    // define function logout
    const logout = async (e) => {
        e.preventDefault();

        post(route("logout"));
    };

    const avatarUrl = auth.user.avatar;
    const userInitial =
        auth.user.name?.charAt(0)?.toUpperCase() ??
        auth.user.email?.charAt(0)?.toUpperCase() ??
        "?";

    return (
        <>
            {isMobile === false ? (
                <Menu className="relative z-10" as="div">
                    <Menu.Button className="flex items-center rounded-full">
                        {avatarUrl ? (
                            <img
                                src={avatarUrl}
                                alt={auth.user.name}
                                className="w-10 h-10 rounded-full object-cover"
                            />
                        ) : (
                            <div className="w-10 h-10 rounded-full bg-aloe-100 text-ink flex items-center justify-center font-semibold">
                                {userInitial}
                            </div>
                        )}
                    </Menu.Button>
                    <Transition
                        enter="transition duration-100 ease-out"
                        enterFrom="transform scale-95 opacity-0"
                        enterTo="transform scale-100 opacity-100"
                        leave="transition duration-75 ease-out"
                        leaveFrom="transform scale-100 opacity-100"
                        leaveTo="transform scale-95 opacity-0"
                    >
                        <Menu.Items className="absolute rounded-card w-48 border mt-2 py-2 right-0 z-[100] bg-white shadow-paper border-hairline-light dark:bg-canvas-night-elevated dark:border-hairline-dark">
                            <div className="flex flex-col gap-1.5 divide-y divide-hairline-light dark:divide-hairline-dark">
                                {/* <Menu.Item>
                                    <Link href="/apps/profile" className='px-3 py-1.5 text-sm flex items-center gap-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'>
                                        <IconUserCog strokeWidth={'1.5'} size={'20'} /> Profile
                                    </Link>
                                </Menu.Item> */}
                                <Menu.Item>
                                    <button
                                        onClick={logout}
                                        className="w-full rounded-full px-3 py-2 text-sm flex items-center gap-2 text-shade-60 hover:bg-canvas-cream hover:text-ink dark:text-gray-400 dark:hover:bg-canvas-night dark:hover:text-gray-200"
                                    >
                                        <IconLogout
                                            strokeWidth={"1.5"}
                                            size={"20"}
                                        />
                                        Logout
                                    </button>
                                </Menu.Item>
                            </div>
                        </Menu.Items>
                    </Transition>
                </Menu>
            ) : (
                <div ref={dropdownRef}>
                    <div className="flex items-center">
                        {avatarUrl ? (
                            <img
                                src={avatarUrl}
                                alt={auth.user.name}
                                className="w-10 h-10 rounded-full object-cover"
                            />
                        ) : (
                            <div className="w-10 h-10 rounded-full bg-aloe-100 text-ink flex items-center justify-center font-semibold">
                                {userInitial}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}

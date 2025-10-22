import { useState, useEffect, useMemo } from "react";
import { usePage, Link } from "@inertiajs/react";

export default function Dropdown({
    label,
    icon = null,
    links = [],
    notification = null,
    isSidebarOpen = false,
}) {
    const { url } = usePage();

    const normalizePath = (href) => {
        try {
            return new URL(href, window.location.origin).pathname;
        } catch {
            return href;
        }
    };

    const isActiveLink = (href) =>
        url === new URL(href, window.location.origin).pathname;

    const hasActiveChild = useMemo(
        () => links.some((link) => isActiveLink(link.href)),
        [url, links]
    );

    const [open, setOpen] = useState(false);

    useEffect(() => {
        // Open dropdown only if sidebar is open and has active child
        setOpen(isSidebarOpen && hasActiveChild);
    }, [hasActiveChild, isSidebarOpen]);

    // Theme-aware classes
    const theme = localStorage.getItem("theme") === "dark" ? "dark" : "light";
    const hoverBg =
        theme === "dark" ? "hover:bg-gray-200" : "hover:bg-gray-700";
    const hoverText =
        theme === "dark" ? "hover:text-black" : "hover:text-white";
    const activeBg = theme === "dark" ? "bg-gray-200" : "bg-gray-700";
    const activeText = theme === "dark" ? "text-black" : "text-white";

    // Determine if parent should be highlighted
    const parentActive = !isSidebarOpen && hasActiveChild;

    return (
        <div className="relative w-full">
            <button
                onClick={() => setOpen(!open)}
                className={`relative flex items-center justify-between w-full px-4 py-2 transition-colors duration-150 rounded-md ${
                    parentActive
                        ? `${activeBg} ${activeText}`
                        : `${hoverBg} ${hoverText}`
                }`}
            >
                <div className="flex items-center space-x-1">
                    {icon && (
                        <span className="w-6 h-6 pt-[2px] flex items-center justify-center">
                            {icon}
                        </span>
                    )}
                    {isSidebarOpen && <p className="ml-2">{label}</p>}
                </div>

                {/* Show arrow and notification only if sidebar is open */}
                {isSidebarOpen && (
                    <div className="flex items-center space-x-2">
                        {notification &&
                            (typeof notification === "number" ? (
                                <span className="bg-red-600 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">
                                    {notification > 99 ? "99+" : notification}
                                </span>
                            ) : (
                                <span className="w-2 h-2 bg-red-600 rounded-full"></span>
                            ))}

                        <span className="pt-[3px] flex items-center justify-center">
                            {open ? (
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    strokeWidth="1.5"
                                    stroke="currentColor"
                                    className="w-6 h-6"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="m4.5 15.75 7.5-7.5 7.5 7.5"
                                    />
                                </svg>
                            ) : (
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    strokeWidth="1.5"
                                    stroke="currentColor"
                                    className="size-4"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        d="m19.5 8.25-7.5 7.5-7.5-7.5"
                                    />
                                </svg>
                            )}
                        </span>
                    </div>
                )}
            </button>

            {/* Render child links only if sidebar is open and dropdown is open */}
            {isSidebarOpen && open && (
                <div className="space-y-1">
                    {links.map((link, index) => {
                        const active = isActiveLink(link.href);
                        const linkNotification = link.notification;

                        return (
                            <Link
                                key={`${normalizePath(link.href)}-${index}`}
                                href={link.href}
                                className={`flex items-center justify-between w-full pl-8 pr-[10px] py-2 rounded transition-colors
                                    ${
                                        active
                                            ? `${activeBg} ${activeText}`
                                            : `${hoverBg} ${hoverText}`
                                    }`}
                            >
                                <div className="flex items-center space-x-1">
                                    <span className="w-6 h-6 pt-[2px] flex items-center justify-center">
                                        {link.icon ? (
                                            link.icon
                                        ) : (
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                strokeWidth="1.5"
                                                stroke="currentColor"
                                                className="w-4 h-4"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    d="M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z"
                                                />
                                            </svg>
                                        )}
                                    </span>
                                    <span className="pl-0 pr-1">
                                        {link.label}
                                    </span>
                                </div>

                                {linkNotification &&
                                    (typeof linkNotification === "number" ? (
                                        <span className="bg-red-600 text-white text-xs px-1.5 py-0.5 rounded-md">
                                            {linkNotification > 99
                                                ? "99+"
                                                : linkNotification}
                                        </span>
                                    ) : (
                                        <span className="w-2 h-2 mr-[7px] bg-red-600 rounded-full"></span>
                                    ))}
                            </Link>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

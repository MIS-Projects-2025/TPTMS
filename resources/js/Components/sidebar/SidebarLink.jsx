import React from "react";
import { Link, usePage } from "@inertiajs/react";

const SidebarLink = ({
    href,
    label,
    icon,
    notifications = 0,
    isSidebarOpen,
}) => {
    const { url } = usePage();
    const isActive = url === new URL(href, window.location.origin).pathname;

    // Determine theme
    const theme = localStorage.getItem("theme") === "dark" ? "dark" : "light";

    // Theme-aware classes
    const hoverBg =
        theme === "dark" ? "hover:bg-gray-200" : "hover:bg-gray-700";
    const hoverText =
        theme === "dark" ? "hover:text-black" : "hover:text-white";
    const activeBg = theme === "dark" ? "bg-gray-200" : "bg-gray-700";
    const activeText = theme === "dark" ? "text-black" : "text-white";

    return (
        <Link
            href={href}
            className={`relative flex items-center px-4 py-2 transition-colors duration-150 rounded-md
                ${
                    isActive
                        ? `${activeBg} ${activeText}`
                        : `${hoverBg} ${hoverText}`
                }`}
            title={!isSidebarOpen ? label : ""} // tooltip on hover if collapsed
        >
            {/* Icon always visible */}
            <span className="w-6 h-6">{icon}</span>

            {/* Label (only if sidebar is expanded) */}
            {isSidebarOpen && <p className="ml-2">{label}</p>}

            {/* Notifications */}
            {notifications > 0 && (
                <span
                    className={`ml-auto text-xs px-2 py-1 rounded-md text-white bg-red-600 ${
                        !isSidebarOpen ? "absolute right-2" : ""
                    }`}
                >
                    {notifications > 99 ? "99+" : notifications}
                </span>
            )}
        </Link>
    );
};

export default SidebarLink;

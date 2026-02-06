import React from "react";
import { Link, usePage } from "@inertiajs/react";
import { Badge } from "antd";

const SidebarLink = ({
    href,
    label,
    icon,
    notifications = 0,
    isSidebarOpen,
    activeColor = "#1890ff", // Ant Design primary color
}) => {
    const { url } = usePage();
    const isActive = url === new URL(href, window.location.origin).pathname;

    return (
        <Link
            href={href}
            className={`relative flex items-center px-4 py-2 rounded-md transition-all duration-150
                ${isActive ? "bg-base-200 font-semibold" : "hover:bg-base-200"}
            `}
            title={!isSidebarOpen ? label : ""}
            style={{
                borderLeft: isActive
                    ? `4px solid ${activeColor}`
                    : "4px solid transparent",
                color: isActive ? activeColor : "inherit",
            }}
        >
            {/* Icon */}
            <span className="w-6 h-6 flex items-center justify-center">
                {icon}
            </span>

            {/* Label */}
            {isSidebarOpen && <p className="ml-3 truncate">{label}</p>}

            {/* Notifications */}
            {notifications > 0 && (
                <Badge
                    count={notifications > 99 ? "99+" : notifications}
                    size="small"
                    className={`ml-auto ${
                        !isSidebarOpen
                            ? "absolute right-2 top-1/2 -translate-y-1/2"
                            : ""
                    }`}
                    style={{ backgroundColor: "#ff4d4f" }}
                />
            )}
        </Link>
    );
};

export default SidebarLink;

import { useState, useEffect, useMemo } from "react";
import { usePage, Link } from "@inertiajs/react";
import { ChevronDown, ChevronRight } from "lucide-react";

export default function Dropdown({
    label,
    icon = null,
    links = [],
    notification = null,
    isSidebarOpen = false,
    activeColor = "#1890ff",
}) {
    const { url } = usePage();

    const normalizePath = (href) => {
        try {
            return new URL(href, window.location.origin).pathname;
        } catch {
            return href;
        }
    };

    const isActiveLink = (href) => url === normalizePath(href);

    const hasActiveChild = useMemo(
        () => links.some((link) => isActiveLink(link.href)),
        [url, links],
    );

    const [open, setOpen] = useState(false);

    useEffect(() => {
        setOpen(isSidebarOpen && hasActiveChild);
    }, [isSidebarOpen, hasActiveChild]);

    const parentActive = hasActiveChild;

    return (
        <div className="relative w-full">
            {/* Parent */}
            <button
                onClick={() => setOpen(!open)}
                className={`relative flex items-center justify-between w-full px-4 py-2 rounded-md transition-all duration-150
                    ${
                        parentActive
                            ? "bg-base-200 font-semibold"
                            : "hover:bg-base-200"
                    }
                `}
                style={{
                    borderLeft: parentActive
                        ? `4px solid ${activeColor}`
                        : "4px solid transparent",
                    color: parentActive ? activeColor : "inherit",
                }}
            >
                <div className="flex items-center space-x-2">
                    {icon && (
                        <span className="w-5 h-5 flex items-center justify-center">
                            {icon}
                        </span>
                    )}
                    {isSidebarOpen && (
                        <span className="ml-2 text-sm truncate">{label}</span>
                    )}
                </div>

                {isSidebarOpen && (
                    <div className="flex items-center space-x-2">
                        {notification && typeof notification === "number" && (
                            <span className="bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                                {notification > 99 ? "99+" : notification}
                            </span>
                        )}
                        <span className="text-gray-200">
                            {open ? (
                                <ChevronDown className="w-4 h-4" />
                            ) : (
                                <ChevronRight className="w-4 h-4" />
                            )}
                        </span>
                    </div>
                )}
            </button>

            {/* Children */}
            {isSidebarOpen && open && (
                <div className="relative mt-1 space-y-1 pl-4">
                    {/* Vertical connecting line */}
                    <div className="absolute left-2 top-2 bottom-2 w-[2px] bg-gray-600 rounded" />

                    {links.map((link, idx) => {
                        const active = isActiveLink(link.href);

                        return (
                            <Link
                                key={idx}
                                href={link.href}
                                className={`relative flex items-center justify-between w-full pl-8 pr-3 py-2 rounded-md transition-all
                                    ${
                                        active
                                            ? "bg-base-200 font-semibold"
                                            : "hover:bg-base-200"
                                    }
                                `}
                                style={{
                                    color: active ? activeColor : "inherit",
                                }}
                            >
                                {/* Dot indicator on the vertical line */}
                                <span
                                    className="absolute left-[13px] top-1/2 -translate-y-1/2 w-2.5 h-2.5 rounded-full border-2 transition-colors duration-200"
                                    style={{
                                        backgroundColor: active
                                            ? activeColor
                                            : "oklch(var(--b3))",
                                        borderColor: active
                                            ? activeColor
                                            : "oklch(var(--bc) / 0.3)",
                                    }}
                                />

                                <div className="flex items-center space-x-2">
                                    {link.icon && (
                                        <span className="w-4 h-4 flex items-center justify-center">
                                            {link.icon}
                                        </span>
                                    )}
                                    <span className="text-xs truncate">
                                        {link.label}
                                    </span>
                                </div>

                                {link.notification &&
                                    typeof link.notification === "number" && (
                                        <span className="bg-red-600 text-white text-xs px-1.5 py-0.5 rounded-md">
                                            {link.notification > 99
                                                ? "99+"
                                                : link.notification}
                                        </span>
                                    )}
                            </Link>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

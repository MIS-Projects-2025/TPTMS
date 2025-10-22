import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import { useContext } from "react";
import Navigation from "@/Components/sidebar/Navigation";
import ThemeToggler from "@/Components/sidebar/ThemeToggler";
import { ThemeContext } from "../ThemeContext";
import { Menu, X, PanelLeftClose, PanelLeftOpen } from "lucide-react";

export default function Sidebar() {
    const { display_name } = usePage().props;
    const { theme, toggleTheme } = useContext(ThemeContext); // Use context
    const [isSidebarOpen, setIsSidebarOpen] = useState(true); // desktop toggle
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false); // mobile toggle

    const formattedAppName = display_name
        ?.split(" ")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");

    return (
        <div className="flex">
            {/* Mobile Hamburger */}
            <button
                className="absolute z-50 p-2 rounded top-4 left-4 md:hidden"
                onClick={() => setIsMobileSidebarOpen(true)}
            >
                <Menu className="w-6 h-6" />
            </button>

            {/* Overlay (mobile only) */}
            {isMobileSidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black bg-opacity-50 md:hidden"
                    onClick={() => setIsMobileSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <div
                className={`
                    fixed md:relative top-0 left-0 z-50 transition-all duration-300
                    ${
                        isMobileSidebarOpen
                            ? "translate-x-0"
                            : "-translate-x-full"
                    }
                    md:translate-x-0
                    flex flex-col min-h-screen
                    ${isSidebarOpen ? "w-[240px]" : "w-[80px]"}
                    px-4 pb-6 pt-4
                    ${
                        theme === "light"
                            ? "bg-gray-50 text-black"
                            : "bg-base-100 text-base-content"
                    }
                `}
            >
                {/* Desktop Collapse Button */}
                <button
                    className="hidden md:flex items-center justify-center absolute top-4 right-[-18px] 
                       btn btn-sm btn-circle shadow-md 
                       bg-base-200 hover:bg-base-300 
                       transition"
                    onClick={() => setIsSidebarOpen(!isSidebarOpen)}
                >
                    {isSidebarOpen ? (
                        <PanelLeftClose className="w-4 h-4" />
                    ) : (
                        <PanelLeftOpen className="w-4 h-4" />
                    )}
                </button>

                {/* Close button on mobile */}
                <button
                    className="absolute top-4 right-4 md:hidden"
                    onClick={() => setIsMobileSidebarOpen(false)}
                >
                    <X className="w-6 h-6" />
                </button>

                {/* LOGO */}
                <Link
                    href={route("dashboard")}
                    className={`flex items-center ${
                        isSidebarOpen ? "pl-[10px]" : "justify-center"
                    } text-lg font-bold`}
                >
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
                            d="M9 3H7.5A2.25 2.25 0 0 0 5.25 5.25V7A2.25 2.25 0 0 1 3 9.25v1.5A2.25 2.25 0 0 1 5.25 13V14.75A2.25 2.25 0 0 0 7.5 17h1.5M15 3h1.5A2.25 2.25 0 0 1 18.75 5.25V7A2.25 2.25 0 0 0 21 9.25v1.5A2.25 2.25 0 0 0 18.75 13V14.75A2.25 2.25 0 0 1 16.5 17H15"
                        />
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M9 3h6v18H9z"
                        />
                    </svg>
                    {isSidebarOpen && (
                        <p className="pt-[2px] pl-1">{formattedAppName}</p>
                    )}
                </Link>
                <br />

                {/* Navigation */}
                <Navigation isSidebarOpen={isSidebarOpen} />

                {/* Theme toggler */}
                <div
                    className={`${isSidebarOpen ? "block" : "hidden"} md:block`}
                >
                    <ThemeToggler toggleTheme={toggleTheme} theme={theme} />
                </div>
            </div>
        </div>
    );
}

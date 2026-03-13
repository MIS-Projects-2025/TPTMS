import { Link, usePage, router } from "@inertiajs/react";
import { useState, useContext, useEffect } from "react";
import Navigation from "@/Components/sidebar/Navigation";
import ThemeToggler from "@/Components/sidebar/ThemeToggler";
import { ThemeContext } from "../ThemeContext";
import NotificationBell from "@/Components/NotificationBell";
import {
    Menu,
    X,
    PanelLeftClose,
    PanelLeftOpen,
    LogOut,
    User,
} from "lucide-react";

export default function Sidebar() {
    const { display_name, emp_data } = usePage().props;
    const { theme, toggleTheme } = useContext(ThemeContext);
    const [isSidebarOpen, setIsSidebarOpen] = useState(true); // desktop toggle
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false); // mobile toggle
    const [mounted, setMounted] = useState(false);

    useEffect(() => setMounted(true), []);

    // Logout function
    const logout = () => {
        localStorage.clear();
        sessionStorage.clear();
        window.location.href = route("logout"); // Laravel handles SSO redirect
    };

    const formattedAppName = display_name
        ?.split(" ")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ");

    if (!mounted) return null;

    return (
        <div className="flex">
            {/* Mobile Hamburger - Hidden when sidebar is open */}
            {!isMobileSidebarOpen && (
                <button
                    className="fixed z-[60] p-2 rounded top-4 left-4 md:hidden bg-base-100 shadow-lg"
                    onClick={() => setIsMobileSidebarOpen(true)}
                >
                    <Menu className="w-5 h-5" />
                </button>
            )}

            {/* Overlay (mobile only) - Clicking this closes the sidebar */}
            {isMobileSidebarOpen && (
                <div
                    className="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm md:hidden"
                    onClick={() => setIsMobileSidebarOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside
                className={`
                    fixed md:relative top-0 left-0 z-50 transition-all duration-300
                    ${isMobileSidebarOpen ? "translate-x-0" : "-translate-x-full md:!translate-x-0"}
                    flex flex-col min-h-screen
                    ${isSidebarOpen ? "w-64" : "w-20"}
                    px-4 pb-6 pt-4
                    ${
                        theme === "light"
                            ? "bg-gray-50 text-black"
                            : "bg-base-300 text-base-content"
                    }
                    ${isMobileSidebarOpen ? "shadow-2xl" : ""}
                `}
            >
                {/* Desktop Toggle Button */}
                <button
                    className="
                        hidden md:flex
                        absolute -right-3 top-1/2 -translate-y-1/2
                        w-7 h-7
                        items-center justify-center
                        rounded-full
                        bg-base-100
                        text-base-content
                        shadow-lg
                        border border-base-content/10
                        hover:scale-105
                        transition-all duration-200
                        z-10
                    "
                    onClick={() => setIsSidebarOpen(!isSidebarOpen)}
                >
                    {isSidebarOpen ? (
                        <PanelLeftClose className="w-3.5 h-3.5" />
                    ) : (
                        <PanelLeftOpen className="w-3.5 h-3.5" />
                    )}
                </button>

                {/* LOGO Section */}
                <div
                    className={`flex items-center h-14 ${
                        !isSidebarOpen ? "justify-center" : ""
                    } border-b ${
                        theme === "light"
                            ? "border-gray-200"
                            : "border-base-content/10"
                    }`}
                >
                    <Link
                        href={route("dashboard")}
                        className={`flex items-center gap-2 text-lg font-bold ${
                            !isSidebarOpen && "justify-center w-full"
                        }`}
                    >
                        <div
                            className={`rounded-lg flex items-center justify-center ${
                                theme === "light"
                                    ? "text-black"
                                    : "text-base-content"
                            }`}
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
                        </div>
                        {isSidebarOpen && <span>{formattedAppName}</span>}
                    </Link>
                </div>

                {/* Navigation */}
                <div className="flex-1 overflow-y-auto py-4">
                    <Navigation isSidebarOpen={isSidebarOpen} />
                </div>

                {/* Mobile Profile Section */}
                {/* {isMobileSidebarOpen && (
                    <div
                        className={`border-t pt-4 md:hidden ${
                            theme === "light"
                                ? "border-gray-200"
                                : "border-base-content/10"
                        }`}
                    >
                        <div className="flex items-center gap-3 mb-4">
                            <div
                                className={`w-10 h-10 rounded-full flex items-center justify-center font-semibold ${
                                    theme === "light"
                                        ? "bg-gray-200 text-gray-700"
                                        : "bg-base-200 text-base-content"
                                }`}
                            >
                                {emp_data?.emp_firstname?.charAt(0)}
                                {emp_data?.emp_lastname?.charAt(0)}
                            </div>
                            <div>
                                <p className="font-medium">
                                    {emp_data?.emp_firstname}{" "}
                                    {emp_data?.emp_lastname}
                                </p>
                                <p
                                    className={`text-xs ${
                                        theme === "light"
                                            ? "text-gray-500"
                                            : "text-gray-400"
                                    }`}
                                >
                                    Online
                                </p>
                            </div>
                            <div className="ml-auto">
                                <NotificationBell />
                            </div>
                        </div>
                        <div className="flex flex-col gap-1">
                            <Link
                                href={route("profile.index")}
                                className={`flex items-center gap-2 px-3 py-2 rounded-lg transition-colors ${
                                    theme === "light"
                                        ? "text-gray-700 hover:bg-gray-200"
                                        : "text-gray-300 hover:bg-base-200"
                                }`}
                            >
                                <User className="w-4 h-4" />
                                <span>Profile</span>
                            </Link>
                            <button
                                onClick={logout}
                                className={`flex items-center gap-2 px-3 py-2 rounded-lg transition-colors text-left ${
                                    theme === "light"
                                        ? "text-red-600 hover:bg-gray-200"
                                        : "text-red-400 hover:bg-base-200"
                                }`}
                            >
                                <LogOut className="w-4 h-4" />
                                <span>Logout</span>
                            </button>
                        </div>
                    </div>
                )} */}

                {/* Theme toggler */}
                <div
                    className={`mt-4 pt-4 border-t ${
                        theme === "light"
                            ? "border-gray-200"
                            : "border-base-content/10"
                    }`}
                >
                    <ThemeToggler
                        toggleTheme={toggleTheme}
                        theme={theme}
                        isCollapsed={!isSidebarOpen}
                    />
                </div>
            </aside>
        </div>
    );
}

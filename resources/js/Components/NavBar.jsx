import { usePage } from "@inertiajs/react";
import { useState, useRef, useEffect } from "react";
import { User, LogOut, ChevronDown, Loader2 } from "lucide-react";
import NotificationBell from "./NotificationBell";

export default function NavBar() {
    const { emp_data } = usePage().props;
    const [open, setOpen] = useState(false);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const dropdownRef = useRef(null);

    const logout = () => {
        setIsLoggingOut(true);
        localStorage.clear();
        sessionStorage.clear();
        setTimeout(() => {
            window.location.href = route("logout");
        }, 500);
    };

    const getInitials = (name) => {
        if (!name) return "?";
        return name
            .split(" ")
            .map((n) => n[0])
            .join("")
            .toUpperCase()
            .slice(0, 2);
    };

    // Close dropdown on outside click
    useEffect(() => {
        const handler = (e) => {
            if (
                dropdownRef.current &&
                !dropdownRef.current.contains(e.target)
            ) {
                setOpen(false);
            }
        };
        document.addEventListener("mousedown", handler);
        return () => document.removeEventListener("mousedown", handler);
    }, []);

    return (
        <nav className="sticky top-0 z-50 bg-base-100/80 backdrop-blur-lg shadow-none">
            <div className="px-4 mx-auto sm:px-6 lg:px-8 max-w-screen-2xl">
                <div className="flex items-center justify-end h-14 gap-2">
                    {/* User Menu */}
                    <div className="relative" ref={dropdownRef}>
                        <button
                            onClick={() => setOpen(!open)}
                            aria-haspopup="true"
                            aria-expanded={open}
                            className={`flex items-center gap-2.5 px-2.5 py-1.5 rounded-xl transition-colors ${
                                open ? "bg-base-200" : "hover:bg-base-200"
                            }`}
                        >
                            {/* Avatar */}
                            <div className="relative shrink-0">
                                <div className="w-8 h-8 rounded-xl bg-primary text-primary-content flex items-center justify-center text-xs font-bold tracking-wide select-none">
                                    {getInitials(emp_data?.emp_firstname)}
                                </div>
                                <span className="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-success border-2 border-base-100 rounded-full" />
                            </div>

                            {/* Name */}
                            <span className="hidden sm:block text-sm font-medium text-base-content/90 max-w-[120px] truncate">
                                Hello, {emp_data?.emp_firstname || "Guest"}
                            </span>

                            {/* Chevron */}
                            <ChevronDown
                                className={`w-3.5 h-3.5 text-base-content/50 transition-transform duration-200 ${
                                    open ? "rotate-180" : ""
                                }`}
                                strokeWidth={2}
                            />
                        </button>

                        {/* Dropdown */}
                        {open && (
                            <div className="absolute right-0 mt-2 w-56 bg-base-100 rounded-2xl shadow-lg border border-base-content/10 overflow-hidden">
                                {/* User Info Header */}
                                <div className="px-4 py-3 border-b border-base-content/8 bg-base-200/40">
                                    <div className="flex items-center gap-3">
                                        <div className="w-9 h-9 rounded-xl bg-primary text-primary-content flex items-center justify-center text-sm font-bold shrink-0">
                                            {getInitials(
                                                emp_data?.emp_firstname,
                                            )}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-base-content truncate">
                                                {emp_data?.emp_firstname ||
                                                    "Guest"}
                                            </p>
                                            <div className="flex items-center gap-1 mt-0.5">
                                                <span className="w-1.5 h-1.5 bg-success rounded-full inline-block" />
                                                <span className="text-xs text-base-content/50">
                                                    Active now
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Menu Items */}
                                <div className="p-1.5 space-y-0.5">
                                    <a
                                        href={route("profile.index")}
                                        onClick={() => setOpen(false)}
                                        className="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-base-200 transition-colors text-sm text-base-content group"
                                    >
                                        <span className="w-7 h-7 rounded-lg bg-primary/10 flex items-center justify-center shrink-0 group-hover:bg-primary/20 transition-colors">
                                            <User
                                                className="w-3.5 h-3.5 text-primary"
                                                strokeWidth={1.8}
                                            />
                                        </span>
                                        View Profile
                                    </a>

                                    <div className="h-px bg-base-content/8 mx-2 my-1" />

                                    <button
                                        onClick={logout}
                                        disabled={isLoggingOut}
                                        className="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-error/8 transition-colors text-sm text-error w-full text-left group disabled:opacity-60"
                                    >
                                        <span className="w-7 h-7 rounded-lg bg-error/10 flex items-center justify-center shrink-0 group-hover:bg-error/15 transition-colors">
                                            {isLoggingOut ? (
                                                <Loader2 className="w-3.5 h-3.5 text-error animate-spin" />
                                            ) : (
                                                <LogOut
                                                    className="w-3.5 h-3.5"
                                                    strokeWidth={1.8}
                                                />
                                            )}
                                        </span>
                                        {isLoggingOut
                                            ? "Signing out…"
                                            : "Sign Out"}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                    {/* Notification Bell */}
                    <div className="flex items-center justify-center w-9 h-9 rounded-xl hover:bg-base-200 transition-colors cursor-pointer">
                        <NotificationBell />
                    </div>
                </div>
            </div>
        </nav>
    );
}

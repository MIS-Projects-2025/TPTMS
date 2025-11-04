import React, { useContext, useState } from "react";
import { Link } from "@inertiajs/react";
import { ArrowLeft, Filter, PlusCircle } from "lucide-react";
import ThemeToggler from "@/Components/sidebar/ThemeToggler";
import { ThemeContext } from "@/Components/ThemeContext";
import NavBar from "@/Components/NavBar";

export default function ProjectLayout({ children }) {
    const { theme, toggleTheme } = useContext(ThemeContext);
    const [isSidebarOpen] = useState(true);

    return (
        <div className="flex h-screen bg-base-200">
            {/* 🟩 Sidebar */}
            <aside className="w-64 bg-base-100 shadow-md flex flex-col p-4 border-r border-base-300">
                <Link
                    href="/"
                    className="flex items-center gap-2 mb-6 btn btn-outline btn-sm"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Back to Main
                </Link>

                <div>
                    <div className="mt-6 border-t border-base-300 pt-4">
                        <h2 className="font-semibold mb-3 text-sm uppercase text-gray-500">
                            Actions
                        </h2>
                        <button
                            className="btn btn-primary btn-sm w-full flex items-center gap-2 justify-center"
                            onClick={() => {
                                // Encode parameters
                                const newEncoded = btoa("new"); // only passing 'new'
                                const params = new URLSearchParams({
                                    action: newEncoded,
                                });

                                // Redirect to tickets route with encoded query
                                window.location.href = `${route(
                                    "tickets"
                                )}?${params.toString()}`;
                            }}
                        >
                            <PlusCircle className="w-4 h-4" />
                            New Project
                        </button>
                    </div>
                </div>

                {/* 🌓 Theme toggler at bottom */}
                <div className="mt-auto pt-4 border-t border-base-300">
                    <ThemeToggler toggleTheme={toggleTheme} theme={theme} />
                </div>
            </aside>

            <div className="flex-1 flex flex-col min-w-0">
                <NavBar /> {/* top navbar */}
                <main className="flex-1 px-4 sm:px-6 py-6 pb-[70px] overflow-y-auto">
                    {children}
                </main>
            </div>
        </div>
    );
}

import React, { useContext, useState } from "react";
import { Link } from "@inertiajs/react";
import { ArrowLeft, Filter, PlusCircle } from "lucide-react";
import ThemeToggler from "@/Components/sidebar/ThemeToggler";
import { ThemeContext } from "@/Components/ThemeContext";

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
                    <h2 className="font-semibold mb-3 text-sm uppercase text-gray-500">
                        Filters
                    </h2>
                    <div className="space-y-2">
                        <button className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start">
                            <Filter className="w-4 h-4" />
                            By Department
                        </button>
                        <button className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start">
                            <Filter className="w-4 h-4" />
                            By Status
                        </button>
                    </div>

                    <div className="mt-6 border-t border-base-300 pt-4">
                        <h2 className="font-semibold mb-3 text-sm uppercase text-gray-500">
                            Actions
                        </h2>
                        <button className="btn btn-primary btn-sm w-full flex items-center gap-2 justify-center">
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

            {/* 🟦 Main Content */}
            <main className="flex-1 overflow-y-auto p-6">{children}</main>
        </div>
    );
}

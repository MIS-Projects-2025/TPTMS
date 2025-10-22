import React, { useContext, useState } from "react";
import { Link } from "@inertiajs/react";
import { ArrowLeft, Filter, PlusCircle } from "lucide-react";
import ThemeToggler from "@/Components/sidebar/ThemeToggler";
import { ThemeContext } from "@/Components/ThemeContext";

export default function TaskLayout({ children }) {
    const { theme, toggleTheme } = useContext(ThemeContext);
    const [isSidebarOpen, setIsSidebarOpen] = useState(true);
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false);

    return (
        <div className="flex h-screen bg-base-200">
            {/* 🟩 Sidebar */}
            <aside className="w-64 bg-base-100 shadow-md flex flex-col p-4 border-r border-base-300">
                {/* Back to main */}
                <Link
                    href="/"
                    className="flex items-center gap-2 mb-6 btn btn-outline btn-sm"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Back to Main
                </Link>

                {/* Filters / Actions */}
                <div>
                    <h2 className="font-semibold mb-3 text-sm uppercase text-gray-500">
                        Filters
                    </h2>
                    <div className="space-y-2">
                        <button className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start">
                            <Filter className="w-4 h-4" />
                            Filter by Status
                        </button>

                        <button className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start">
                            <Filter className="w-4 h-4" />
                            Filter by Project
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

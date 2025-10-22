import React, { useContext, useState } from "react";
import { Link } from "@inertiajs/react";
import { ArrowLeft, Filter, PlusCircle } from "lucide-react";
import ThemeToggler from "@/Components/sidebar/ThemeToggler";
import { ThemeContext } from "@/Components/ThemeContext";

export default function TaskLayout({
    children,
    onFilterStatus,
    onResetFilters,
}) {
    const { theme, toggleTheme } = useContext(ThemeContext);

    const handleFilterStatus = (status) => {
        if (onFilterStatus) onFilterStatus(status);
    };

    const handleResetFilters = () => {
        if (onResetFilters) onResetFilters();
    };

    return (
        <div className="flex h-screen bg-base-200">
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
                        <button
                            className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start"
                            onClick={() => handleFilterStatus(1)}
                        >
                            <Filter className="w-4 h-4" />
                            Pending
                        </button>
                        <button
                            className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start"
                            onClick={() => handleFilterStatus(2)}
                        >
                            <Filter className="w-4 h-4" />
                            In Progress
                        </button>
                        <button
                            className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start"
                            onClick={() => handleFilterStatus(3)}
                        >
                            <Filter className="w-4 h-4" />
                            Completed
                        </button>
                        <button
                            className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start"
                            onClick={handleResetFilters}
                        >
                            <Filter className="w-4 h-4" />
                            Reset Filter
                        </button>
                    </div>
                </div>

                <div className="mt-auto pt-4 border-t border-base-300">
                    <ThemeToggler toggleTheme={toggleTheme} theme={theme} />
                </div>
            </aside>

            <main className="flex-1 overflow-y-auto p-6">{children}</main>
        </div>
    );
}

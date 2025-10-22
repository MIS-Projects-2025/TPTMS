import React, { useContext } from "react";
import { Link } from "@inertiajs/react";
import { ArrowLeft, Filter, RefreshCcw } from "lucide-react";
import ThemeToggler from "@/Components/sidebar/ThemeToggler";
import { ThemeContext } from "@/Components/ThemeContext";
import { DatePicker } from "antd";

export default function TaskLayout({
    children,
    selectedDates,
    onDateChange,
    onFilterStatus,
    onResetFilters,
}) {
    const { theme, toggleTheme } = useContext(ThemeContext);

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
                        {/* Date Range Picker - Controlled by parent */}
                        <DatePicker.RangePicker
                            value={selectedDates}
                            onChange={(dates) => {
                                if (dates && dates.length === 2) {
                                    onDateChange([
                                        dates[0].startOf("day"),
                                        dates[1].endOf("day"),
                                    ]);
                                } else {
                                    // User cleared the selection
                                    onDateChange(null);
                                }
                            }}
                            format="YYYY-MM-DD"
                            allowEmpty={[true, true]}
                            className="w-full"
                        />

                        {/* Status filters */}
                        <button
                            className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start"
                            onClick={() => onFilterStatus(1)}
                        >
                            <Filter className="w-4 h-4" /> Pending
                        </button>
                        <button
                            className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start"
                            onClick={() => onFilterStatus(2)}
                        >
                            <Filter className="w-4 h-4" /> In Progress
                        </button>
                        <button
                            className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start"
                            onClick={() => onFilterStatus(3)}
                        >
                            <Filter className="w-4 h-4" /> Completed
                        </button>

                        {/* Reset Filters */}
                        <button
                            className="btn btn-sm btn-outline w-full flex items-center gap-2 justify-start"
                            onClick={onResetFilters}
                        >
                            <RefreshCcw className="w-4 h-4" /> Reset Filters
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

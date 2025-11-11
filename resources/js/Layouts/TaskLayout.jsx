import React, { useContext } from "react";
import { Link } from "@inertiajs/react";
import { ArrowLeft, Filter, RefreshCcw, Users } from "lucide-react";
import ThemeToggler from "@/Components/sidebar/ThemeToggler";
import { ThemeContext } from "@/Components/ThemeContext";
import { DatePicker, Select } from "antd";
import NavBar from "@/Components/NavBar";

const { Option } = Select;

export default function TaskLayout({
    children,
    selectedDates,
    selectedProgrammer,
    selectedStatus,
    onDateChange,
    onFilterStatus,
    onProgrammerChange,
    onResetFilters,
    programmers = [], // Add programmers prop
    isSupervisor,
}) {
    const { theme, toggleTheme } = useContext(ThemeContext);

    // Normalize programmer data to match expected format
    const normalizedProgrammers = programmers.map((programmer) => ({
        id: programmer.EMPLOYID || programmer.id,
        name: programmer.EMPNAME || programmer.name,
        email: programmer.email || "", // Add email if available
    }));

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
                        {/* Date Range Picker */}
                        <DatePicker.RangePicker
                            value={selectedDates}
                            onChange={(dates) => {
                                if (dates && dates.length === 2) {
                                    onDateChange([
                                        dates[0].startOf("day"),
                                        dates[1].endOf("day"),
                                    ]);
                                } else {
                                    onDateChange(null);
                                }
                            }}
                            format="YYYY-MM-DD"
                            allowEmpty={[true, true]}
                            className="w-full"
                        />
                        {isSupervisor && (
                            <Select
                                placeholder="Filter by Programmer"
                                value={selectedProgrammer || undefined} // Convert null to undefined to avoid warning
                                onChange={onProgrammerChange}
                                allowClear
                                className="w-full"
                                suffixIcon={<Users className="w-4 h-4" />}
                            >
                                {normalizedProgrammers.map((programmer) => (
                                    <Option
                                        key={programmer.id} // Use unique ID as key
                                        value={programmer.id}
                                    >
                                        {programmer.name}
                                    </Option>
                                ))}
                            </Select>
                        )}

                        {/* Status filters */}
                        <Select
                            placeholder="Filter by Status"
                            value={selectedStatus || undefined} // You need a selectedStatus state in parent
                            onChange={onFilterStatus}
                            allowClear
                            className="w-full"
                            suffixIcon={<Filter className="w-4 h-4" />}
                        >
                            <Option value={1}>Pending</Option>
                            <Option value={2}>In Progress</Option>
                            <Option value={3}>Completed</Option>
                        </Select>
                    </div>
                </div>

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

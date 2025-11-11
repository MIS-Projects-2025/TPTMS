import React from "react";
import { Input, Tooltip, DatePicker, Select } from "antd";
import {
    PlusCircle,
    LayoutGrid,
    List,
    Users,
    Filter,
    RotateCw,
} from "lucide-react";

const { Option } = Select;

export default function TaskNavbar({
    isCardView,
    toggleView,
    searchTerm,
    onSearch,
    setIsModalOpen,
    isSupervisor,
    selectedDates,
    onDateChange,
    selectedProgrammer,
    onProgrammerChange,
    programmers = [],
    selectedStatus,
    onFilterStatus,
    resetFilters, // function to reset all filters
}) {
    // Normalize programmer data
    const normalizedProgrammers = programmers.map((programmer) => ({
        id: programmer.EMPLOYID || programmer.id,
        name: programmer.EMPNAME || programmer.name,
        email: programmer.email || "",
    }));

    return (
        <div className="flex flex-wrap items-center gap-3 mb-4 bg-base-100 px-4 py-3 rounded-xl shadow-sm border border-base-300">
            {/* 🔍 Search */}
            <Input.Search
                placeholder="Search tasks..."
                allowClear
                value={searchTerm}
                onSearch={onSearch}
                onChange={(e) => onSearch(e.target.value)}
                className="flex-shrink-0 w-64"
            />

            {/* 📅 Date Range */}
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
                className="flex-shrink-0"
            />

            {/* 👨‍💻 Programmer filter (supervisor only) */}
            {isSupervisor && (
                <Select
                    placeholder="Filter by Programmer"
                    value={selectedProgrammer || undefined}
                    onChange={onProgrammerChange}
                    allowClear
                    className="flex-shrink-0 w-64"
                    suffixIcon={<Users className="w-4 h-4" />}
                >
                    {normalizedProgrammers.map((programmer) => (
                        <Option key={programmer.id} value={programmer.id}>
                            {programmer.name}
                        </Option>
                    ))}
                </Select>
            )}

            {/* 🔖 Status filter + Reset */}
            <div className="flex items-center gap-2 flex-shrink-0">
                <Select
                    placeholder="Filter by Status"
                    value={selectedStatus || undefined}
                    onChange={onFilterStatus}
                    allowClear
                    className="w-48"
                    suffixIcon={<Filter className="w-4 h-4" />}
                >
                    <Option value={1}>Pending</Option>
                    <Option value={2}>In Progress</Option>
                    <Option value={3}>Completed</Option>
                </Select>

                {/* Reset Filter Icon */}
                <Tooltip title="Reset Filters">
                    <button
                        onClick={resetFilters}
                        className="btn btn-ghost btn-outline btn-sm p-2 flex items-center justify-center"
                    >
                        <RotateCw className="w-4 h-4" />
                        Reset
                    </button>
                </Tooltip>
            </div>

            {/* ⚙️ Actions */}
            <div className="flex gap-2 ml-auto">
                <Tooltip title="Switch View">
                    <button
                        className="btn btn-sm btn-outline flex items-center gap-2"
                        onClick={toggleView}
                    >
                        {isCardView ? (
                            <>
                                <List className="w-4 h-4" /> Table
                            </>
                        ) : (
                            <>
                                <LayoutGrid className="w-4 h-4" /> Cards
                            </>
                        )}
                    </button>
                </Tooltip>

                {!isSupervisor && (
                    <Tooltip title="New Task">
                        <button
                            className="btn btn-sm btn-primary flex items-center gap-2"
                            onClick={() => setIsModalOpen(true)}
                        >
                            <PlusCircle className="w-4 h-4" /> New Task
                        </button>
                    </Tooltip>
                )}
            </div>
        </div>
    );
}

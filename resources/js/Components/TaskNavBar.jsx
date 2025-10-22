import React, { useEffect } from "react";
import { Input, Select, Tooltip, DatePicker } from "antd";
import {
    Search,
    PlusCircle,
    LayoutGrid,
    List,
    Download,
    RefreshCw,
} from "lucide-react";
import dayjs from "dayjs";

export default function TaskNavbar({
    isCardView,
    toggleView,
    onSearch,
    onAddTask,
    onSortChange,
    onDateChange,
    onResetFilters,
}) {
    const { Option } = Select;
    const [dateRange, setDateRange] = React.useState([dayjs(), dayjs()]);

    const handleDateChange = (dates) => {
        if (dates && dates.length === 2) {
            setDateRange(dates); // update displayed value
            onDateChange(dates); // send to parent filter
        }
    };

    // ✅ Reset button
    const handleReset = () => {
        const today = [dayjs(), dayjs()];
        setDateRange(today); // update input
        onDateChange(today); // update filter
        if (onResetFilters) onResetFilters();
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 mb-4 bg-base-100 px-4 py-3 rounded-xl shadow-sm border border-base-300">
            {/* 🔍 Search */}
            <div className="flex items-center gap-2">
                <Search className="w-4 h-4 text-gray-500" />
                <Input.Search
                    placeholder="Search tasks..."
                    allowClear
                    style={{ width: 250 }}
                    onSearch={onSearch}
                    onChange={(e) => onSearch(e.target.value)}
                />
            </div>

            {/* ⚙️ Actions */}
            <div className="flex items-center gap-3">
                {/* 🗓 Date Range Picker */}
                <Tooltip title="Filter by Date">
                    <DatePicker.RangePicker
                        value={dateRange}
                        onChange={handleDateChange}
                        format="YYYY-MM-DD"
                        allowEmpty={[false, false]}
                    />
                </Tooltip>

                <Select
                    placeholder="Sort by"
                    style={{ width: 150 }}
                    onChange={(value) => onSortChange(value)}
                    allowClear
                >
                    <Option value="date">Date</Option>
                    <Option value="priority">Priority</Option>
                    <Option value="status">Status</Option>
                </Select>

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

                <Tooltip title="Reset Filters">
                    <button
                        className="btn btn-sm btn-outline flex items-center gap-2"
                        onClick={() => {
                            const today = [dayjs(), dayjs()];
                            setDateRange(today);
                            onDateChange(today);
                            if (onResetFilters) onResetFilters();
                        }}
                    >
                        <RefreshCw className="w-4 h-4" /> Reset
                    </button>
                </Tooltip>

                <button
                    onClick={onAddTask}
                    className="btn btn-primary btn-sm flex items-center gap-2"
                >
                    <PlusCircle className="w-4 h-4" /> New Task
                </button>
            </div>
        </div>
    );
}

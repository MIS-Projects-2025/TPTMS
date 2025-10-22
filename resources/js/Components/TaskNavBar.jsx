import React from "react";
import { Input, Select, Tooltip } from "antd";
import { Search, PlusCircle, LayoutGrid, List, Download } from "lucide-react";

export default function TaskNavbar({
    isCardView,
    toggleView,
    onSearch,
    onAddTask,
    onSortChange,
}) {
    const { Option } = Select;

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 mb-4 bg-base-100 px-4 py-3 rounded-xl shadow-sm border border-base-300">
            {/* 🔍 Search */}
            <div className="flex items-center gap-2">
                <Search className="w-4 h-4 text-gray-500" />
                <Input.Search
                    placeholder="Search tasks..."
                    allowClear
                    style={{ width: 250 }}
                    onSearch={onSearch} // When pressing Enter or clicking icon
                    onChange={(e) => onSearch(e.target.value)} // 🔥 Live search as you type
                />
            </div>

            {/* ⚙️ Actions */}
            <div className="flex items-center gap-3">
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

                <Tooltip title="Download Report">
                    <button className="btn btn-sm btn-outline">
                        <Download className="w-4 h-4" />
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

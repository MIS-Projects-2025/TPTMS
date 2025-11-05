import React from "react";
import { Input, Tooltip } from "antd";
import { PlusCircle, LayoutGrid, List } from "lucide-react";

export default function TaskNavbar({
    isCardView,
    toggleView,
    searchTerm,
    onSearch,
    setIsModalOpen,
    isSupervisor,
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 mb-4 bg-base-100 px-4 py-3 rounded-xl shadow-sm border border-base-300">
            {/* 🔍 Search */}
            <div className="flex items-center gap-2">
                <Input.Search
                    placeholder="Search tasks..."
                    allowClear
                    value={searchTerm}
                    style={{ width: 250 }}
                    onSearch={onSearch}
                    onChange={(e) => onSearch(e.target.value)}
                />
            </div>

            {/* ⚙️ Actions */}
            <div className="flex items-center gap-3">
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

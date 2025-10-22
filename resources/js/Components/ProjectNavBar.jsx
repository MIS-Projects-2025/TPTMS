import React from "react";
import { Input, Select, Tooltip } from "antd";
import { Search, Filter, Download } from "lucide-react";

export default function ProjectNavbar({
    searchValue,
    onSearch,
    departments,
    onDepartmentChange,
}) {
    const { Option } = Select;

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 mb-4 bg-base-100 px-4 py-3 rounded-xl shadow-sm border border-base-300">
            {/* 🔍 Search */}
            <div className="flex items-center gap-2">
                <Search className="w-4 h-4 text-gray-500" />
                <Input.Search
                    placeholder="Search projects..."
                    allowClear
                    style={{ width: 250 }}
                    value={searchValue}
                    onSearch={onSearch}
                    onChange={(e) => onSearch(e.target.value)} // Live search
                />
            </div>

            {/* ⚙️ Filters and Actions */}
            <div className="flex items-center gap-3">
                <Select
                    placeholder="Filter by Department"
                    allowClear
                    style={{ width: 180 }}
                    onChange={onDepartmentChange}
                >
                    {departments?.map((dept) => (
                        <Option key={dept} value={dept}>
                            {dept}
                        </Option>
                    ))}
                </Select>
                {/* 
                <Tooltip title="More Filters">
                    <button className="btn btn-sm btn-outline flex items-center gap-2">
                        <Filter className="w-4 h-4" /> Filters
                    </button>
                </Tooltip> */}

                {/* <Tooltip title="Download Report">
                    <button className="btn btn-sm btn-outline flex items-center gap-2">
                        <Download className="w-4 h-4" /> Export
                    </button>
                </Tooltip> */}
            </div>
        </div>
    );
}

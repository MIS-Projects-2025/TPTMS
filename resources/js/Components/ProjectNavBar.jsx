import React from "react";
import { Input, Select, Tooltip } from "antd";
import { FileSpreadsheet } from "lucide-react";

const { Option } = Select;

export default function ProjectNavbar({
    searchValue,
    onSearch,
    departments,
    onDepartmentChange,
    setShowImportModal,
    showAllDepartments,
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 mb-4 bg-base-100 px-5 py-3 rounded-xl shadow-sm border border-base-300">
            {/* Left Section: Search + Department Filter */}
            <div className="flex flex-wrap items-center gap-3">
                {/* Search Box */}
                <div className="flex items-center gap-2">
                    <Input.Search
                        placeholder="Search projects..."
                        allowClear
                        style={{ width: 250 }}
                        value={searchValue}
                        onSearch={onSearch}
                        onChange={(e) => onSearch(e.target.value)}
                    />
                </div>

                {/* Department Filter (MIS/OD only) */}
                {showAllDepartments && (
                    <Select
                        placeholder="Filter by Department"
                        allowClear
                        style={{ width: 200 }}
                        onChange={onDepartmentChange}
                    >
                        {departments?.map((dept) => (
                            <Option key={dept} value={dept}>
                                {dept}
                            </Option>
                        ))}
                    </Select>
                )}
            </div>

            {/* Right Section: Import Excel (MIS/OD only) */}
            {showAllDepartments && (
                <div className="flex items-center gap-2">
                    <Tooltip title="Import Excel File">
                        <button
                            className="btn btn-success flex items-center gap-2 px-4 py-2 text-white rounded-lg shadow-sm hover:opacity-90"
                            onClick={() => setShowImportModal(true)}
                        >
                            <FileSpreadsheet size={18} />
                            Import Excel
                        </button>
                    </Tooltip>
                </div>
            )}
        </div>
    );
}

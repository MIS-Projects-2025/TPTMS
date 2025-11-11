import React from "react";
import { Input, Select, Tooltip, Badge } from "antd";
import { FileSpreadsheet } from "lucide-react";
import { PlusCircleOutlined } from "@ant-design/icons";

const { Option } = Select;

export default function ProjectNavbar({
    searchValue,
    onSearch,
    departments,
    onDepartmentChange,
    setShowImportModal,
    showAllDepartments,
    isProgrammer,
    handleNewProjectClick,
    // New props for additional filtering
    assignedPeople,
    statusCounts,
    onAssignedToChange,
    onStatusChange,
    currentFilters,
    projectStatuses,
}) {
    // Status options with counts - using numeric values
    const statusOptions = [
        { value: "", label: "All Status", count: statusCounts?.all || 0 },
        ...(projectStatuses?.map((status) => ({
            value: status.value,
            label: status.label,
            count: statusCounts?.[status.value] || 0,
        })) || []),
    ];

    // Get count for "All" option
    const allCount = statusCounts?.all || 0;

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 mb-4 bg-base-100 px-5 py-3 rounded-xl shadow-sm border border-base-300">
            {/* Left Section: Search + Filters */}
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
                        value={currentFilters?.department}
                    >
                        {departments?.map((dept) => (
                            <Option key={dept} value={dept}>
                                {dept}
                            </Option>
                        ))}
                    </Select>
                )}

                {/* Assigned To Filter */}
                {showAllDepartments && (
                    <Select
                        placeholder="Filter by Assigned To"
                        allowClear
                        style={{ width: 200 }}
                        onChange={onAssignedToChange}
                        value={currentFilters?.assigned_to}
                    >
                        {assignedPeople?.map((person) => (
                            <Option key={person.emp_id} value={person.emp_id}>
                                {person.name}
                            </Option>
                        ))}
                    </Select>
                )}

                {/* Status Filter - Using numeric values */}
                {showAllDepartments && (
                    <Select
                        placeholder="Filter by Status"
                        allowClear
                        style={{ width: 180 }}
                        onChange={onStatusChange}
                        value={currentFilters?.status}
                    >
                        {statusOptions.map((status) => (
                            <Option key={status.value} value={status.value}>
                                <div className="flex justify-between items-center w-full">
                                    <span className="flex-1">
                                        {status.label}
                                    </span>
                                    <Badge
                                        count={status.count}
                                        size="small"
                                        style={{
                                            backgroundColor: "#1890ff",
                                            marginLeft: 8,
                                        }}
                                    />
                                </div>
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
                            className="btn flex items-center gap-2 px-4 py-2 h-10 rounded-lg text-white shadow-sm hover:opacity-90 bg-green-500"
                            onClick={() => setShowImportModal(true)}
                        >
                            <FileSpreadsheet size={18} />
                            Import Excel
                        </button>
                    </Tooltip>

                    <Tooltip
                        title={
                            isProgrammer
                                ? "Create new project"
                                : "Create new ticket"
                        }
                    >
                        <button
                            className="btn flex items-center gap-2 px-4 py-2 h-10 rounded-lg text-white shadow-sm hover:opacity-90 bg-blue-600"
                            onClick={handleNewProjectClick}
                        >
                            <PlusCircleOutlined
                                style={{ width: 16, height: 16 }}
                            />
                            {isProgrammer ? "New Project" : "New Ticket"}
                        </button>
                    </Tooltip>
                </div>
            )}
        </div>
    );
}

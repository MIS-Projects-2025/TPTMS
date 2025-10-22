import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import React, { useState } from "react";
import { usePage, router } from "@inertiajs/react";
import { Table, Spin, Empty, Select, Space } from "antd";
import { Search, Filter } from "lucide-react";

const ProjectsTable = () => {
    const {
        projects,
        pagination,
        filters: initialFilters,
        departments,
    } = usePage().props;
    const [loading, setLoading] = useState(false);
    const [searchValue, setSearchValue] = useState(
        initialFilters?.search || ""
    );
    const [filters, setFilters] = useState(initialFilters || {});

    // 🔐 Helper to encode params
    const encodeParams = (params) => {
        return btoa(JSON.stringify(params));
    };

    // 🔍 Handle Search
    const handleSearch = (value) => {
        const updatedFilters = { ...filters, search: value };
        setFilters(updatedFilters);
        setSearchValue(value);

        const encoded = encodeParams(updatedFilters);
        setLoading(true);

        router.get(
            route("projects.list"),
            { q: encoded },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setLoading(false),
            }
        );
    };

    // 🔽 Handle Department Filter
    const handleDepartmentChange = (value) => {
        const updatedFilters = { ...filters, department: value };
        setFilters(updatedFilters);

        const encoded = encodeParams(updatedFilters);
        setLoading(true);

        router.get(
            route("projects.list"),
            { q: encoded },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setLoading(false),
            }
        );
    };

    const columns = [
        { title: "Project Name", dataIndex: "name", key: "name" },
        {
            title: "Project Description",
            dataIndex: "description",
            key: "description",
        },
        { title: "Department", dataIndex: "department", key: "department" },
        { title: "Status", dataIndex: "status", key: "status" },
    ];

    return (
        <AuthenticatedLayout>
            {/* Filters */}
            <div className="flex flex-wrap justify-between items-center mb-4 gap-3">
                <div className="flex items-center gap-2">
                    <Search className="w-4 h-4 text-base-content/70" />
                    <input
                        type="text"
                        placeholder="Search projects..."
                        value={searchValue}
                        onChange={(e) => handleSearch(e.target.value)}
                        className="input input-bordered input-sm w-64"
                    />
                </div>

                <Space wrap>
                    <Select
                        placeholder="Filter by Department"
                        allowClear
                        style={{ width: 180 }}
                        onChange={handleDepartmentChange}
                        value={filters?.department || undefined}
                    >
                        {departments?.map((dept) => (
                            <Select.Option key={dept} value={dept}>
                                {dept}
                            </Select.Option>
                        ))}
                    </Select>

                    <button className="btn btn-outline btn-sm flex items-center gap-2">
                        <Filter className="w-4 h-4" />
                        Filters
                    </button>
                </Space>
            </div>

            {/* Table */}
            <Spin spinning={loading}>
                {projects && projects.length > 0 ? (
                    <Table
                        columns={columns}
                        dataSource={projects}
                        rowKey={(record) => record.id}
                        pagination={{
                            current: pagination?.current_page || 1,
                            pageSize: pagination?.per_page || 10,
                            total: pagination?.total || 0,
                            showSizeChanger: true,
                            showQuickJumper: true,
                            pageSizeOptions: ["10", "20", "50"],
                        }}
                        bordered
                        size="middle"
                        scroll={{ x: 1200 }}
                        className="bg-base-100 rounded-xl shadow-md"
                        loading={loading}
                    />
                ) : (
                    <div className="flex flex-col items-center justify-center py-20 bg-base-100 rounded-xl shadow-md">
                        <Empty description="No projects found" />
                    </div>
                )}
            </Spin>
        </AuthenticatedLayout>
    );
};

export default ProjectsTable;

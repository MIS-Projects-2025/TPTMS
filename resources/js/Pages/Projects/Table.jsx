import React, { useState } from "react";
import { usePage, router } from "@inertiajs/react";
import { Table, Spin, Empty, Tag } from "antd";
import ProjectLayout from "@/Layouts/ProjectLayout";
import ProjectNavbar from "@/Components/ProjectNavbar";

export default function ProjectsTable() {
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

    const encodeParams = (params) => btoa(JSON.stringify(params));

    // 🔍 Search handler
    const handleSearch = (value) => {
        const updated = { ...filters, search: value, page: 1 }; // reset to page 1
        setFilters(updated);
        setSearchValue(value);

        const encoded = encodeParams(updated);
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

    // 🏢 Department filter
    const handleDepartmentChange = (value) => {
        const updated = { ...filters, department: value, page: 1 };
        setFilters(updated);

        const encoded = encodeParams(updated);
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

    // 📄 Pagination handler
    const handleTableChange = (paginationData) => {
        const updated = { ...filters, page: paginationData.current };
        setFilters(updated);

        const encoded = encodeParams(updated);
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
        { title: "Description", dataIndex: "description", key: "description" },
        { title: "Department", dataIndex: "department", key: "department" },
        {
            title: "Status",
            dataIndex: "status",
            key: "status",
            render: (status) => {
                const colors = {
                    "Not Started": "default",
                    "In Progress": "blue",
                    "On Hold": "orange",
                    Completed: "green",
                    Cancelled: "red",
                };
                return (
                    <Tag color={colors[status] || "gray"}>
                        {status || "Unknown"}
                    </Tag>
                );
            },
        },
    ];

    return (
        <ProjectLayout>
            <ProjectNavbar
                searchValue={searchValue}
                onSearch={handleSearch}
                departments={departments}
                onDepartmentChange={handleDepartmentChange}
            />

            <Spin spinning={loading}>
                {projects && projects.length > 0 ? (
                    <Table
                        columns={columns}
                        dataSource={projects}
                        rowKey={(r) => r.id}
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
                        scroll={{ x: 1000 }}
                        className="bg-base-100 rounded-xl shadow-md"
                        onChange={handleTableChange} // ✅ added handler
                    />
                ) : (
                    <div className="flex flex-col items-center justify-center py-20 bg-base-100 rounded-xl shadow-md">
                        <Empty description="No projects found" />
                    </div>
                )}
            </Spin>
        </ProjectLayout>
    );
}

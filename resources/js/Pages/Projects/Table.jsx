import React, { useState } from "react";
import { usePage, router } from "@inertiajs/react";
import { Table, Spin, Empty, Tag, Avatar, Tooltip } from "antd";
import { UserOutlined } from "@ant-design/icons";
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
        const updated = { ...filters, search: value, page: 1 };
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

    // Generate consistent color based on string
    const getColorFromString = (str) => {
        const colors = [
            "#1890ff",
            "#52c41a",
            "#faad14",
            "#f5222d",
            "#722ed1",
            "#eb2f96",
            "#13c2c2",
            "#fa8c16",
        ];
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return colors[Math.abs(hash) % colors.length];
    };

    const columns = [
        { title: "Project Name", dataIndex: "name", key: "name", width: 150 },
        {
            title: "Description",
            dataIndex: "description",
            key: "description",
            width: 200,
        },
        {
            title: "Department",
            dataIndex: "department",
            key: "department",
            width: 120,
        },
        {
            title: "Assigned To",
            dataIndex: "assigned_to",
            key: "assigned_to",
            width: 50,
            render: (assignedTo) => {
                if (!assignedTo || assignedTo.length === 0) {
                    return <span className="text-gray-400">Unassigned</span>;
                }

                // Only show the first 2 avatars
                const visible = assignedTo.slice(0, 2);
                const hidden = assignedTo.slice(2); // rest of the people
                const remaining = hidden.length;

                // Combine initials of hidden people for tooltip
                const hiddenInitials = hidden.map((p) => p.initials).join(", ");

                return (
                    <Avatar.Group maxCount={2} size="default">
                        {visible.map((person) => (
                            <Tooltip
                                key={person.emp_id}
                                title={person.fullName}
                            >
                                <Avatar
                                    style={{
                                        backgroundColor: getColorFromString(
                                            person.emp_id
                                        ),
                                    }}
                                >
                                    {person.initials}
                                </Avatar>
                            </Tooltip>
                        ))}
                        {remaining > 0 && (
                            <Tooltip title={hiddenInitials}>
                                <Avatar style={{ backgroundColor: "#f56a00" }}>
                                    +{remaining}
                                </Avatar>
                            </Tooltip>
                        )}
                    </Avatar.Group>
                );
            },
        },
        {
            title: "Status",
            dataIndex: "status",
            key: "status",
            width: 70,
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
                        scroll={{ x: 1200, y: "65vh" }}
                        className="bg-base-100 rounded-xl shadow-md"
                        onChange={handleTableChange}
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

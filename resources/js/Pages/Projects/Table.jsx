import React, { useState } from "react";
import { usePage, router } from "@inertiajs/react";
import {
    Table,
    Spin,
    Empty,
    Tag,
    Avatar,
    Tooltip,
    Button,
    Dropdown,
    Menu,
} from "antd";
import {
    UserOutlined,
    PlusOutlined,
    FileSearchOutlined,
    MoreOutlined,
} from "@ant-design/icons";
import ProjectLayout from "@/Layouts/ProjectLayout";
import ProjectNavbar from "@/Components/ProjectNavbar";
import ImportModal from "./ImportModal";
import ProjectLogsModal from "./ProjectLogsModal";
import useProjectLogs from "@/Hooks/useProjectLogs";

export default function ProjectsTable() {
    const {
        projects,
        pagination,
        filters: initialFilters,
        departments,
        appName,
    } = usePage().props;

    const [loading, setLoading] = useState(false);
    const [searchValue, setSearchValue] = useState(
        initialFilters?.search || ""
    );
    const [filters, setFilters] = useState(initialFilters || {});
    const [showImportModal, setShowImportModal] = useState(false);
    const [showLogsModal, setShowLogsModal] = useState(false);
    const [selectedProject, setSelectedProject] = useState(null);

    const {
        projectLogs,
        logsLoading,
        pagination: logPagination,
        fetchProjectLogs,
    } = useProjectLogs(appName);
    console.log(projectLogs);

    const encodeParams = (params) => btoa(JSON.stringify(params));

    // ✅ Create Ticket
    const handleCreateTicket = (project) => {
        const params = new URLSearchParams();
        params.set("project", btoa(project.name)); // encode project name
        window.location.href = `${route("tickets")}?${params.toString()}`;
    };

    // 🔍 Search
    const handleSearch = (value) => {
        const updated = { ...filters, search: value, page: 1 };
        setFilters(updated);
        setSearchValue(value);
        const encoded = encodeParams(updated);
        setLoading(true);
        router.get(
            route("projects.list"),
            { q: encoded },
            { preserveState: true, onFinish: () => setLoading(false) }
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
            { preserveState: true, onFinish: () => setLoading(false) }
        );
    };

    // 📄 Table pagination
    const handleTableChange = (paginationData) => {
        const updated = { ...filters, page: paginationData.current };
        setFilters(updated);
        const encoded = encodeParams(updated);
        setLoading(true);
        router.get(
            route("projects.list"),
            { q: encoded },
            { preserveState: true, onFinish: () => setLoading(false) }
        );
    };
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

    // 🧾 View Logs
    const handleViewLogs = (project) => {
        setSelectedProject(project);
        setShowLogsModal(true);
        fetchProjectLogs(project.id, 1);
    };

    // 🧱 Table Columns
    const columns = [
        { title: "Project Name", dataIndex: "name", key: "name", width: 180 },
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
            width: 70,
            render: (assignedTo) => {
                if (!assignedTo || assignedTo.length === 0) {
                    return <span className="text-gray-400">-</span>;
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
            title: "Active Requests",
            key: "active_requests",
            width: 150,
            render: (_, record) => {
                if (
                    !record.active_tickets ||
                    record.active_tickets.length === 0
                ) {
                    return <span className="text-gray-400">No tickets</span>;
                }

                // Show first 2 tickets, rest in tooltip
                const visible = record.active_tickets.slice(0, 2);
                const hidden = record.active_tickets.slice(2);
                const remaining = hidden.length;

                return (
                    <div className="flex flex-col gap-1">
                        {visible.map((ticket) => (
                            <div key={ticket.id} className="flex flex-col">
                                <span className="font-semibold">
                                    {ticket.type}
                                </span>
                                <span className="text-xs text-gray-500">
                                    {ticket.date_start
                                        ? new Date(
                                              ticket.date_start
                                          ).toLocaleDateString()
                                        : "-"}{" "}
                                    -{" "}
                                    {ticket.date_end
                                        ? ticket.date_end === "Ongoing"
                                            ? "Ongoing"
                                            : new Date(
                                                  ticket.date_end
                                              ).toLocaleDateString()
                                        : "-"}
                                </span>
                            </div>
                        ))}

                        {remaining > 0 && (
                            <Tooltip
                                title={hidden
                                    .map(
                                        (t) =>
                                            `${t.type}: ${
                                                t.date_start
                                                    ? new Date(
                                                          t.date_start
                                                      ).toLocaleDateString()
                                                    : "-"
                                            } - ${
                                                t.date_end === "Ongoing"
                                                    ? "Ongoing"
                                                    : new Date(
                                                          t.date_end
                                                      ).toLocaleDateString()
                                            }`
                                    )
                                    .join("\n")}
                            >
                                <span className="text-xs text-blue-600 cursor-pointer">
                                    +{remaining} more
                                </span>
                            </Tooltip>
                        )}
                    </div>
                );
            },
        },
        {
            title: "Status",
            dataIndex: "status",
            key: "status",
            width: 100,
            render: (status) => {
                const colors = {
                    Planning: "gold",
                    "Not Started": "default",
                    "In Progress": "processing",
                    "On Hold": "orange",
                    Ready: "cyan",
                    Deployed: "geekblue",
                    Completed: "green",
                    Cancelled: "red",
                };

                return (
                    <Tag color={colors[status] || "default"}>
                        {status || "Unknown"}
                    </Tag>
                );
            },
        },
        {
            title: "Actions",
            key: "actions",
            width: 80,
            render: (_, record) => {
                const menu = (
                    <Menu
                        items={[
                            {
                                key: "viewLogs",
                                label: "View Logs",
                                icon: <FileSearchOutlined />,
                                onClick: () => handleViewLogs(record),
                            },
                            {
                                key: "createTicket",
                                label: "Create Ticket",
                                icon: <PlusOutlined />,
                                onClick: () => handleCreateTicket(record),
                            },
                        ]}
                    />
                );
                return (
                    <Dropdown
                        overlay={menu}
                        trigger={["click"]}
                        placement="bottomRight"
                    >
                        <Button
                            type="text"
                            icon={<MoreOutlined style={{ fontSize: "18px" }} />}
                        />
                    </Dropdown>
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
                setShowImportModal={setShowImportModal}
            />

            <Spin spinning={loading}>
                {projects?.length > 0 ? (
                    <Table
                        columns={columns}
                        dataSource={projects}
                        rowKey={(r) => r.id}
                        pagination={{
                            current: pagination?.current_page || 1,
                            pageSize: pagination?.per_page || 10,
                            total: pagination?.total || 0,
                        }}
                        bordered
                        size="middle"
                        scroll={{ x: 1000, y: "50vh" }}
                        onChange={handleTableChange}
                        className="bg-base-100 rounded-xl shadow-md"
                    />
                ) : (
                    <div className="flex flex-col items-center justify-center py-20 bg-base-100 rounded-xl shadow-md">
                        <Empty description="No projects found" />
                    </div>
                )}
            </Spin>

            {/* 📦 Import Modal */}
            <ImportModal
                isOpen={showImportModal}
                onClose={() => setShowImportModal(false)}
            />

            {/* 🧾 Logs Modal */}
            <ProjectLogsModal
                isOpen={showLogsModal}
                onClose={() => setShowLogsModal(false)}
                logs={projectLogs}
                loading={logsLoading}
                pagination={logPagination}
                onPageChange={(page) =>
                    fetchProjectLogs(selectedProject.id, page)
                }
                projectName={selectedProject?.name}
            />
        </ProjectLayout>
    );
}

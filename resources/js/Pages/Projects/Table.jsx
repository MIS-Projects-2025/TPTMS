import React, { use, useState } from "react";
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
    message,
} from "antd";
import {
    PlusOutlined,
    FileSearchOutlined,
    MoreOutlined,
    EditOutlined,
    LeftOutlined,
    PlusCircleOutlined,
} from "@ant-design/icons";
import { Link } from "@inertiajs/react";
import ProjectNavbar from "@/Components/ProjectNavbar";
import ImportModal from "./ImportModal";
import ProjectLogsModal from "./ProjectLogsModal";
import ProjectEditDrawer from "./ProjectEditDrawer";
import useProjectLogs from "@/Hooks/useProjectLogs";
import useProjectConstants from "@/Hooks/useProjectConstants";
import ProjectTableSkeleton from "./ProjectTableSkeleton";

export default function ProjectsTable() {
    const {
        projects,
        pagination,
        filters: initialFilters,
        departments,
        appName,
        showAllDepartments,
        emp_data,
        programmers,
        statusCounts,
    } = usePage().props;
    console.log(usePage().props);

    const [loading, setLoading] = useState(false);
    const [searchValue, setSearchValue] = useState(
        initialFilters?.search || ""
    );
    const [filters, setFilters] = useState(initialFilters || {});
    const [showImportModal, setShowImportModal] = useState(false);
    const [showLogsModal, setShowLogsModal] = useState(false);
    const [showEditDrawer, setShowEditDrawer] = useState(false);
    const [selectedProject, setSelectedProject] = useState(null);
    const [drawerMode, setDrawerMode] = useState("edit");

    // Check if user is programmer
    const isProgrammer = emp_data?.emp_system_role === "Programmer";

    const {
        projectLogs,
        logsLoading,
        pagination: logPagination,
        fetchProjectLogs,
    } = useProjectLogs(appName);

    // Fetch project constants from API
    const { projectStatuses, getStatusLabel } = useProjectConstants();

    const encodeParams = (params) => btoa(JSON.stringify(params));

    // ✅ Create Ticket
    const handleCreateTicket = (project) => {
        const params = new URLSearchParams();
        params.set("project", btoa(project.name));
        window.location.href = `${route("tickets")}?${params.toString()}`;
    };

    // 🆕 Create Project - Only for programmers
    const handleCreateProject = () => {
        if (!isProgrammer) {
            message.warning("You don't have permission to create projects");
            return;
        }
        setSelectedProject(null);
        setDrawerMode("create");
        setShowEditDrawer(true);
    };

    // ✏️ Edit Project - Only for programmers
    const handleEditProject = (project) => {
        if (!isProgrammer) {
            message.warning("You don't have permission to edit projects");
            return;
        }
        setSelectedProject(project);
        setDrawerMode("edit");
        setShowEditDrawer(true);
    };

    // 🔍 Search
    const handleSearch = (value) => {
        const updated = { ...filters, search: value, page: 1 };
        setFilters(updated);
        setSearchValue(value);
        const encoded = encodeParams(updated);
        setLoading(true);
        router.get(
            route("project.list"),
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
            route("project.list"),
            { q: encoded },
            { preserveState: true, onFinish: () => setLoading(false) }
        );
    };

    // 👨‍💻 Assigned To filter (using assigned_to array)
    const handleAssignedToChange = (value) => {
        const updated = { ...filters, assigned_to: value, page: 1 };
        setFilters(updated);
        const encoded = encodeParams(updated);
        setLoading(true);
        router.get(
            route("project.list"),
            { q: encoded },
            { preserveState: true, onFinish: () => setLoading(false) }
        );
    };

    // 📊 Status filter - Send label string to backend
    const handleStatusChange = (value) => {
        const updated = { ...filters, status: value, page: 1 };
        setFilters(updated);
        const encoded = encodeParams(updated);
        setLoading(true);
        router.get(
            route("project.list"),
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
            route("project.list"),
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

    // Handle New Project/Ticket click
    const handleNewProjectClick = () => {
        if (isProgrammer) {
            handleCreateProject();
        } else {
            const newEncoded = btoa("new");
            const params = new URLSearchParams({
                action: newEncoded,
            });
            window.location.href = `${route("tickets")}?${params.toString()}`;
        }
    };

    // Get unique assigned people from all projects for filter
    const getAssignedPeopleOptions = () => {
        const peopleMap = new Map();

        projects?.forEach((project) => {
            project.assigned_to?.forEach((person) => {
                if (person.emp_id && !peopleMap.has(person.emp_id)) {
                    peopleMap.set(person.emp_id, {
                        emp_id: person.emp_id,
                        name: person.fullName || person.full_name,
                        initials: person.initials,
                    });
                }
            });
        });

        return Array.from(peopleMap.values());
    };

    // Render avatar group helper
    const renderAvatarGroup = (people, maxCount = 2) => {
        if (!people || people.length === 0) {
            return <span className="text-gray-400">-</span>;
        }

        const visible = people.slice(0, maxCount);
        const hidden = people.slice(maxCount);
        const remaining = hidden.length;
        const hiddenNames = hidden
            .map((p) => p.fullName || p.full_name)
            .join(", ");

        return (
            <Avatar.Group max={maxCount} size="default">
                {visible.map((person) => (
                    <Tooltip
                        key={person.emp_id}
                        title={person.fullName || person.full_name}
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
                    <Tooltip title={hiddenNames}>
                        <Avatar style={{ backgroundColor: "#f56a00" }}>
                            +{remaining}
                        </Avatar>
                    </Tooltip>
                )}
            </Avatar.Group>
        );
    };

    // Get status label for display
    const getStatusDisplayLabel = (statusValue) => {
        const statusObj = projectStatuses.find((s) => s.value === statusValue);
        return statusObj ? statusObj.label : statusValue;
    };

    // 🧱 Table Columns
    const columns = [
        {
            title: "Project Name",
            dataIndex: "name",
            key: "name",
            width: 180,
            fixed: "left",
        },
        {
            title: "Description",
            dataIndex: "description",
            key: "description",
            width: 200,
            render: (desc) => desc || <span className="text-gray-400">-</span>,
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
            width: 120,
            render: (assignedTo) => renderAvatarGroup(assignedTo, 2),
        },
        {
            title: "Project Handler",
            dataIndex: "proj_handler",
            key: "proj_handler",
            width: 120,
            render: (handlers) => renderAvatarGroup(handlers, 2),
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
            title: "Target Deadline",
            dataIndex: "target_deadline",
            key: "target_deadline",
            width: 120,
            render: (deadline) => {
                if (!deadline) {
                    return <span className="text-gray-400">Not set</span>;
                }
                return (
                    <span className="text-sm">
                        {new Date(deadline).toLocaleDateString()}
                    </span>
                );
            },
        },
        {
            title: "Status",
            dataIndex: "status",
            key: "status",
            width: 100,
            render: (statusLabel) => {
                const statusObj = projectStatuses.find(
                    (s) => s.label === statusLabel
                );

                if (!statusObj) {
                    return <Tag color="default">{statusLabel}</Tag>;
                }

                return <Tag color={statusObj.color}>{statusObj.label}</Tag>;
            },
        },

        {
            title: "Actions",
            key: "actions",
            width: 80,
            fixed: "right",
            render: (_, record) => {
                const menuItems = [
                    // Only show Edit Project for programmers
                    ...(isProgrammer
                        ? [
                              {
                                  key: "editProject",
                                  label: "Edit Project",
                                  icon: <EditOutlined />,
                                  onClick: () => handleEditProject(record),
                              },
                          ]
                        : []),
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
                ];

                return (
                    <Dropdown
                        menu={{ items: menuItems }}
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
        <div className="min-h-screen bg-base-200 p-4">
            {/* Enhanced Navbar with all controls */}
            <div className="flex flex-col gap-4 mb-6">
                {/* Top Row: Back button and New Project/Ticket button */}
                <div className="flex items-center justify-between bg-base-100 px-5 py-3 rounded-xl shadow-sm border border-base-300">
                    <Link
                        href="/"
                        className="flex items-center gap-2 btn btn-outline btn-sm"
                    >
                        <LeftOutlined style={{ width: 16, height: 16 }} />
                        Back to Main
                    </Link>

                    <button
                        className="btn btn-primary btn-sm flex items-center gap-2"
                        onClick={handleNewProjectClick}
                        title={
                            isProgrammer
                                ? "Create new project"
                                : "Create new ticket"
                        }
                    >
                        <PlusCircleOutlined style={{ width: 16, height: 16 }} />
                        {isProgrammer ? "New Project" : "New Ticket"}
                    </button>
                </div>

                {/* Enhanced ProjectNavbar with additional filters */}
                <ProjectNavbar
                    searchValue={searchValue}
                    onSearch={handleSearch}
                    departments={departments}
                    onDepartmentChange={handleDepartmentChange}
                    setShowImportModal={setShowImportModal}
                    showAllDepartments={showAllDepartments}
                    onCreateProject={isProgrammer ? handleCreateProject : null}
                    // New props for additional filtering
                    assignedPeople={getAssignedPeopleOptions()}
                    statusCounts={statusCounts}
                    onAssignedToChange={handleAssignedToChange}
                    onStatusChange={handleStatusChange}
                    currentFilters={filters}
                    projectStatuses={projectStatuses}
                />
            </div>

            {/* Main Content */}
            <Spin spinning={loading}>
                {loading ? (
                    <ProjectTableSkeleton />
                ) : projects?.length > 0 ? (
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
                        scroll={{ x: 1400, y: "50vh" }}
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

            {/* ✏️ Create/Edit Project Drawer */}
            <ProjectEditDrawer
                isOpen={showEditDrawer}
                onClose={() => setShowEditDrawer(false)}
                project={selectedProject}
                mode={drawerMode}
                onUpdated={() => {
                    // Refetch table data after edit/create
                    const encoded = encodeParams(filters);
                    setLoading(true);
                    router.get(
                        route("project.list"),
                        { q: encoded },
                        {
                            preserveState: true,
                            onFinish: () => setLoading(false),
                        }
                    );
                }}
            />
        </div>
    );
}

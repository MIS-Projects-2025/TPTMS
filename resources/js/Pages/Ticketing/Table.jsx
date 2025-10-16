import React, { use } from "react";
import { Table, Input, Space, Select, Tag, Empty, Dropdown } from "antd";
import {
    Search,
    Filter,
    SortAsc,
    MoreVertical,
    Eye,
    Plus,
    CheckCircle,
    XCircle,
    Wrench,
    RefreshCw,
    UserPlus,
} from "lucide-react";
import {
    ThunderboltOutlined,
    CheckCircleOutlined,
    ExclamationCircleOutlined,
    ToolOutlined,
    AppstoreOutlined,
} from "@ant-design/icons";
import { usePage } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import StatCard from "./StatCard";
import useTicketTable from "@/Hooks/useTicketTable";

export default function TicketTable() {
    const { tickets } = usePage().props;
    console.log(usePage().props);
    const {
        searchText,
        setSearchText,
        selectedProject,
        setSelectedProject,
        sortOrder,
        setSortOrder,
        activeFilter,
        setActiveFilter,
        projects,
        statusCounts,
        filteredTickets,
    } = useTicketTable(tickets);

    // 🔹 Handles Action Navigation
    const handleAction = (action, ticketId, record) => {
        if (action === "VIEW") {
            const hash = btoa(`${ticketId}:VIEW`);
            window.location.href = route("tickets.view", hash);
            return;
        }
        if (action === "CREATE_CHILD") {
            const parentEncoded = btoa(ticketId.toString());
            const projectEncoded = btoa(record.project_name || "");
            const user = btoa(record.employid || "");
            const params = new URLSearchParams({
                parent: parentEncoded,
                project: projectEncoded,
                user: user,
            });

            window.location.href = `${route("tickets")}?${params.toString()}`;
            return;
        }

        const hash = btoa(`${ticketId}:${action}`);
        window.location.href = route("tickets.view", hash);
    };

    // 🔹 Check if ticket is open
    const isTicketOpen = (status) => {
        const closedStatuses = ["In Progress"];
        return closedStatuses.includes(status);
    };

    const renderActions = (_, record) => {
        const actions = Array.isArray(record.actions) ? record.actions : [];

        // Always available (View)
        const viewOption = {
            key: "view",
            label: (
                <div className="flex items-center gap-2">
                    <Eye className="w-4 h-4 text-primary" />
                    View
                </div>
            ),
            onClick: () => handleAction("VIEW", record.ticket_id, record),
        };

        // Create Child Ticket
        const canCreateChild = isTicketOpen(record.status);
        const createChildOption = canCreateChild
            ? {
                  key: "create_child",
                  label: (
                      <div className="flex items-center gap-2">
                          <Plus className="w-4 h-4 text-primary" />
                          Create Child Ticket
                      </div>
                  ),
                  onClick: () =>
                      handleAction("CREATE_CHILD", record.ticket_id, record),
              }
            : null;

        // Workflow actions with icons
        const workflowOptions = actions
            .filter(
                (action) =>
                    action &&
                    action.toLowerCase() !== "view" &&
                    action.trim() !== ""
            )
            .map((action) => {
                // Define icons per action
                const iconMap = {
                    assess: <Filter className="w-4 h-4 text-primary" />,
                    approve: <CheckCircle className="w-4 h-4 text-green-600" />,
                    assign: <UserPlus className="w-4 h-4 text-blue-500" />,
                    resolve: <Wrench className="w-4 h-4 text-purple-600" />,
                    close: <XCircle className="w-4 h-4 text-red-500" />,
                    test: <RefreshCw className="w-4 h-4 text-orange-500" />,
                };

                const icon = iconMap[action.toLowerCase()] || (
                    <Plus className="w-4 h-4 text-gray-500" />
                );

                return {
                    key: action.toLowerCase(),
                    label: (
                        <div className="flex items-center gap-2">
                            {icon}
                            <span className="text-sm">{action}</span>
                        </div>
                    ),
                    onClick: () =>
                        handleAction(action, record.ticket_id, record),
                };
            });

        // Combine all menu items (View always included)
        const allMenuItems = [
            viewOption,
            createChildOption,
            ...workflowOptions,
        ].filter(Boolean);

        // ✅ If only 1 action → show it as a standalone button
        if (allMenuItems.length === 1) {
            return (
                <button
                    onClick={allMenuItems[0].onClick}
                    className="btn btn-primary btn-sm flex items-center gap-1"
                >
                    <Eye className="w-4 h-4" />
                    View
                </button>
            );
        }

        // ✅ If more than 1 → show ellipsis dropdown only
        return (
            <Dropdown
                menu={{ items: allMenuItems }}
                trigger={["click"]}
                placement="bottomRight"
            >
                <button className="btn btn-ghost btn-sm flex items-center justify-center rounded-full border border-base-300 hover:bg-base-200">
                    <MoreVertical className="w-4 h-4" />
                </button>
            </Dropdown>
        );
    };

    const columns = [
        {
            title: "Ticket ID",
            dataIndex: "ticket_id",
            key: "ticket_id",
            width: 120,
        },
        {
            title: "Requestor",
            dataIndex: "emp_name",
            key: "emp_name",
            width: 150,
        },
        {
            title: "Project",
            dataIndex: "project_name",
            key: "project_name",
            width: 150,
        },
        {
            title: "Type",
            dataIndex: "type_of_request",
            key: "type_of_request",
            width: 150,
        },
        {
            title: "Status",
            dataIndex: "status",
            key: "status",
            width: 100,
            render: (status) => {
                const colorMap = {
                    New: "blue",
                    Triaged: "cyan",
                    Approved: "green",
                    "In Progress": "orange",
                    Resolved: "purple",
                    Closed: "green",
                    Rejected: "red",
                    "On Hold": "gold",
                };
                return (
                    <Tag color={colorMap[status] || "default"}>{status}</Tag>
                );
            },
        },
        {
            title: "Created At",
            dataIndex: "created_at",
            key: "created_at",
            width: 150,
        },
        {
            title: "Actions",
            key: "actions",
            width: 220,
            render: (_, record) => renderActions(_, record),
        },
    ];

    return (
        <AuthenticatedLayout>
            {/* --- Dashboard Stat Cards --- */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <StatCard
                    title="All Tickets"
                    value={statusCounts.all}
                    color="primary"
                    icon={AppstoreOutlined}
                    onClick={setActiveFilter}
                    isActive={activeFilter === "all"}
                    filterType="all"
                />
                <StatCard
                    title="Active"
                    value={statusCounts.active || 0}
                    color="info"
                    icon={ThunderboltOutlined}
                    onClick={setActiveFilter}
                    isActive={activeFilter === "active"}
                    filterType="active"
                />
                <StatCard
                    title="Urgent"
                    value={statusCounts.urgent || 0}
                    color="error"
                    icon={ExclamationCircleOutlined}
                    onClick={setActiveFilter}
                    isActive={activeFilter === "urgent"}
                    filterType="urgent"
                />
                <StatCard
                    title="In Progress"
                    value={statusCounts["in progress"] || 0}
                    color="warning"
                    icon={ToolOutlined}
                    onClick={setActiveFilter}
                    isActive={activeFilter === "in progress"}
                    filterType="in progress"
                />
                <StatCard
                    title="Closed"
                    value={statusCounts.closed || 0}
                    color="success"
                    icon={CheckCircleOutlined}
                    onClick={setActiveFilter}
                    isActive={activeFilter === "closed"}
                    filterType="closed"
                />
            </div>
            <div className="p-6 bg-base-200 min-h-screen transition-all duration-300 border border-base-300 rounded-xl shadow-sm">
                {/* Filters */}
                <div className="flex flex-wrap justify-between items-center mb-4 gap-3">
                    <div className="flex items-center gap-2">
                        <Search className="w-4 h-4 text-base-content/70" />
                        <input
                            type="text"
                            placeholder="Search tickets..."
                            value={searchText}
                            onChange={(e) => setSearchText(e.target.value)}
                            className="input input-bordered input-sm w-64"
                        />
                    </div>

                    <Space wrap>
                        <Select
                            placeholder="Filter by Project"
                            allowClear
                            style={{ width: 180 }}
                            onChange={setSelectedProject}
                            value={selectedProject}
                        >
                            {projects.map((p) => (
                                <Select.Option key={p} value={p}>
                                    {p}
                                </Select.Option>
                            ))}
                        </Select>

                        <button
                            onClick={() =>
                                setSortOrder((prev) =>
                                    prev === "asc" ? "desc" : "asc"
                                )
                            }
                            className="btn btn-outline btn-sm flex items-center gap-2"
                        >
                            <SortAsc className="w-4 h-4" />
                            Sort ({sortOrder})
                        </button>

                        <button className="btn btn-outline btn-sm flex items-center gap-2">
                            <Filter className="w-4 h-4" />
                            Filters
                        </button>
                    </Space>
                </div>

                {/* Table */}

                {filteredTickets && filteredTickets.length > 0 ? (
                    <Table
                        columns={columns}
                        dataSource={filteredTickets}
                        rowKey={(record) => record.ticket_id}
                        pagination={{ pageSize: 10 }}
                        bordered
                        size="middle"
                        scroll={{ x: 1200 }}
                        className="bg-base-100 rounded-xl shadow-md"
                    />
                ) : (
                    <div className="flex flex-col items-center justify-center py-20 bg-base-100 rounded-xl shadow-md">
                        <Empty
                            description={`No ${activeFilter} tickets found`}
                        />
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

import React, { useState, useMemo } from "react";
import { Table, Input, Button, Space, Select, Tag, Empty } from "antd";
import {
    SearchOutlined,
    FilterOutlined,
    SortAscendingOutlined,
    ThunderboltOutlined,
    CheckCircleOutlined,
    ExclamationCircleOutlined,
    ToolOutlined,
    AppstoreOutlined,
} from "@ant-design/icons";
import { usePage, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

// DaisyUI-themed StatCard
const StatCard = ({
    title,
    value,
    color,
    icon: Icon,
    onClick,
    isActive,
    filterType,
}) => (
    <div
        className={`card cursor-pointer transition-all duration-300 border shadow-md hover:shadow-lg
            ${
                isActive
                    ? "bg-base-100 border-primary"
                    : "bg-base-200 border-base-300 hover:bg-base-100"
            }`}
        onClick={() => onClick(filterType)}
    >
        <div className="card-body p-4 flex flex-row items-center justify-between">
            <div>
                <p className={`text-sm font-medium text-${color}`}>{title}</p>
                <p className={`text-2xl font-bold text-${color}`}>{value}</p>
            </div>
            <Icon className={`text-${color} text-3xl`} />
        </div>
    </div>
);

export default function TicketTable() {
    const { tickets } = usePage().props;

    const [searchText, setSearchText] = useState("");
    const [selectedProject, setSelectedProject] = useState(null);
    const [sortOrder, setSortOrder] = useState("desc");
    const [activeFilter, setActiveFilter] = useState("all");

    // Extract project list
    const projects = [...new Set(tickets.map((t) => t.project_name))];

    // Count per status
    const statusCounts = useMemo(() => {
        const counts = { all: tickets.length };
        tickets.forEach((t) => {
            const s = t.status?.toLowerCase() || "unknown";
            counts[s] = (counts[s] || 0) + 1;
        });
        return counts;
    }, [tickets]);

    // Filter logic
    const filteredTickets = useMemo(() => {
        return tickets
            .filter((t) => {
                const matchesSearch =
                    t.ticket_id
                        .toLowerCase()
                        .includes(searchText.toLowerCase()) ||
                    t.project_name
                        .toLowerCase()
                        .includes(searchText.toLowerCase());
                const matchesProject =
                    !selectedProject || t.project_name === selectedProject;
                const matchesStatus =
                    activeFilter === "all" ||
                    t.status?.toLowerCase() === activeFilter.toLowerCase();
                return matchesSearch && matchesProject && matchesStatus;
            })
            .sort((a, b) =>
                sortOrder === "asc"
                    ? new Date(a.created_at) - new Date(b.created_at)
                    : new Date(b.created_at) - new Date(a.created_at)
            );
    }, [tickets, searchText, selectedProject, sortOrder, activeFilter]);

    const columns = [
        { title: "Ticket ID", dataIndex: "ticket_id", key: "ticket_id" },
        { title: "Requestor", dataIndex: "emp_name", key: "emp_name" },
        { title: "Project", dataIndex: "project_name", key: "project_name" },
        { title: "Type", dataIndex: "type_of_request", key: "type_of_request" },
        {
            title: "Status",
            dataIndex: "status",
            key: "status",
            render: (status) => {
                const colorMap = {
                    Active: "blue",
                    Urgent: "red",
                    "In Progress": "orange",
                    Closed: "green",
                };
                return (
                    <Tag color={colorMap[status] || "default"}>{status}</Tag>
                );
            },
        },
        { title: "Created At", dataIndex: "created_at", key: "created_at" },
        {
            title: "Actions",
            dataIndex: "actions",
            key: "actions",
            render: (actions, record) =>
                Array.isArray(actions) && actions.length > 0 ? (
                    <Space>
                        {actions.map((action, index) => (
                            <Button
                                key={index}
                                type="primary"
                                size="small"
                                ghost
                                onClick={() =>
                                    handleAction(action, record.ticket_id)
                                }
                            >
                                {action}
                            </Button>
                        ))}
                    </Space>
                ) : (
                    <span className="text-base-content/40">No actions</span>
                ),
        },
    ];
    const handleAction = (action, ticketId) => {
        const encodedTicket = btoa(ticketId); // Base64 encode ticket ID
        const encodedAction = btoa(action); // Base64 encode action name

        console.log(
            `🧩 Encoded Info -> Ticket: ${encodedTicket}, Action: ${encodedAction}`
        );
    };

    return (
        <AuthenticatedLayout>
            <div className="p-6 bg-base-200 min-h-screen transition-all duration-300">
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

                {/* --- Filter Bar --- */}
                <div className="flex flex-wrap justify-between items-center mb-4 gap-3">
                    <Input
                        prefix={<SearchOutlined />}
                        placeholder="Search tickets..."
                        value={searchText}
                        onChange={(e) => setSearchText(e.target.value)}
                        className="input input-bordered w-64"
                    />
                    <Space wrap>
                        <Select
                            placeholder="Filter by Project"
                            allowClear
                            style={{ width: 180 }}
                            onChange={setSelectedProject}
                        >
                            {projects.map((p) => (
                                <Select.Option key={p} value={p}>
                                    {p}
                                </Select.Option>
                            ))}
                        </Select>
                        <Button
                            icon={<SortAscendingOutlined />}
                            onClick={() =>
                                setSortOrder((prev) =>
                                    prev === "asc" ? "desc" : "asc"
                                )
                            }
                            className="btn btn-outline btn-sm"
                        >
                            Sort ({sortOrder})
                        </Button>
                        <Button
                            icon={<FilterOutlined />}
                            className="btn btn-outline btn-sm"
                        >
                            Advanced Filters
                        </Button>
                    </Space>
                </div>

                {/* --- Table or Empty State --- */}
                {filteredTickets.length > 0 ? (
                    <Table
                        columns={columns}
                        dataSource={filteredTickets}
                        rowKey="ticket_id"
                        pagination={{ pageSize: 10 }}
                        bordered
                        size="middle"
                        className="bg-base-100 rounded-xl shadow-md"
                    />
                ) : (
                    <div className="flex flex-col items-center justify-center py-20 bg-base-100 rounded-xl shadow-md">
                        <Empty
                            description={`No ${activeFilter} tickets found`}
                            className="dark:text-base-content/70"
                        />
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

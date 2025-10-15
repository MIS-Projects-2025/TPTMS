import React, { use } from "react";
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

    const handleAction = (action, ticketId) => {
        if (action === "CREATE_CHILD") {
            // Redirect to ticket creation form with parent_ticket query param
            window.location.href =
                route("tickets") + `?parent_ticket=${ticketId}`;
            return;
        }
        const hash = btoa(`${ticketId}:${action}`);
        router.visit(route("tickets.view", hash), { method: "get" });
    };

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

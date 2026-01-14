import React, { useState, useEffect, useRef } from "react";
import { Table, Input, Space, Select, Tag, Empty, Dropdown, Spin } from "antd";
import {
    Search,
    Filter,
    MoreVertical,
    Eye,
    Plus,
    CheckCircle,
    XCircle,
    Wrench,
    RefreshCw,
    UserPlus,
    Lock,
    BadgeCheckIcon,
} from "lucide-react";
import {
    ThunderboltOutlined,
    CheckCircleOutlined,
    ExclamationCircleOutlined,
    ToolOutlined,
    AppstoreOutlined,
} from "@ant-design/icons";
import { usePage, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import StatCard from "./StatCard";
import { useNotifications } from "@/Context/NotificationContext";
import TicketFormSkeleton from "./TicketFormSkeleton";
export default function TicketTable() {
    const { tickets, pagination, projects, statusCounts, filters } =
        usePage().props;
    const [loading, setLoading] = useState(false);
    const searchTimeoutRef = useRef(null);
    const [searchValue, setSearchValue] = useState(filters?.search || "");
    const { markAsRead, markAllAsRead, notifications, unreadCount } =
        useNotifications();
    const prevNotificationCountRef = useRef(unreadCount);
    const [isLoading, setIsLoading] = useState(true);
    // Simulate data loading effect
    useEffect(() => {
        // Simulate loading delay for data initialization
        const timer = setTimeout(() => {
            setIsLoading(false);
        }, 800);

        return () => clearTimeout(timer);
    }, []);

    // Function to refresh tickets from server
    const refreshTickets = () => {
        // console.log("🔄 Refreshing tickets...");
        router.reload({
            only: ["tickets", "pagination", "statusCounts", "filters"],
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Smart refresh - only refresh for ticket-related notifications
    useEffect(() => {
        // Only process when unread count increases (new notification arrived)
        if (unreadCount > prevNotificationCountRef.current) {
            const countDiff = unreadCount - prevNotificationCountRef.current;

            // Get the new notifications (most recent ones)
            const newNotifications = notifications.slice(0, countDiff);

            // console.log("📬 New notification(s) detected:", {
            //     count: countDiff,
            //     newNotifications,
            // });

            // Check if any new notification is ticket-related
            const hasTicketUpdate = newNotifications.some((notif) => {
                // Parse the data field if it's a string
                const data =
                    typeof notif.data === "string"
                        ? JSON.parse(notif.data)
                        : notif.data;

                // Check for ticket-related notification types
                const ticketTypes = [
                    "TICKET_CREATED",
                    "TICKET_UPDATED",
                    "TICKET_ASSIGNED",
                    "TICKET_STATUS_CHANGED",
                    "TICKET_RESOLVED",
                    "TICKET_CLOSED",
                ];

                const isTicketNotification =
                    (data?.type && ticketTypes.includes(data.type)) ||
                    data?.ticket_id ||
                    notif.type?.includes("Ticket");

                // console.log("🔍 Checking notification:", {
                //     notificationId: notif.id,
                //     type: data?.type || notif.type,
                //     isTicketRelated: isTicketNotification,
                // });

                return isTicketNotification;
            });

            if (hasTicketUpdate) {
                // console.log(
                //     "🎫 Ticket-related notification detected! Refreshing table..."
                // );
                refreshTickets();
            } else {
                // console.log("ℹ️ Non-ticket notification, skipping refresh");
            }
        }

        prevNotificationCountRef.current = unreadCount;
    }, [unreadCount, notifications]);

    // Determine active filter from status
    const activeFilter = filters?.status || "all";
    // console.log(activeFilter);
    // Update search value when filters change from URL
    useEffect(() => {
        setSearchValue(filters?.search || "");
    }, [filters?.search]);

    // Handle pagination and sorting changes
    const handleTableChange = (paginationData, _, sorter) => {
        setLoading(true);

        const params = {
            page: paginationData.current,
            pageSize: paginationData.pageSize,
            search: filters?.search || "",
            project: filters?.project || "",
            status: filters?.status || "",
            sortField: sorter?.field || "created_at",
            sortOrder: sorter?.order === "ascend" ? "asc" : "desc",
        };

        const encodedParams = btoa(JSON.stringify(params));

        router.get(
            route("tickets.datatable"),
            { q: encodedParams },
            {
                onFinish: () => setLoading(false),
            }
        );
    };

    // Handle search with debounce
    const handleSearch = (value) => {
        setSearchValue(value);

        if (searchTimeoutRef.current) {
            clearTimeout(searchTimeoutRef.current);
        }

       searchTimeoutRef.current = setTimeout(() => {
    setLoading(true);

    const params = {
        page: 1,
        pageSize: pagination?.per_page || 10,
        search: value,
        project: filters?.project || "",
        status: filters?.status || "",
        sortField: filters?.sortField || "created_at",
        sortOrder: filters?.sortOrder || "desc",
    };

    const encodedParams = btoa(JSON.stringify(params));

    router.get(
        route("tickets.datatable"),
        { q: encodedParams },
        { onFinish: () => setLoading(false) }
    );
}, 500);

    };

    // Handle project filter
    const handleProjectChange = (value) => {
        setLoading(true);

        const params = {
            page: 1,
            pageSize: pagination?.per_page || 10,
            search: filters?.search || "",
            project: value || "",
            status: filters?.status || "",
            sortField: filters?.sortField || "created_at",
            sortOrder: filters?.sortOrder || "desc",
        };

        const encodedParams = btoa(JSON.stringify(params));

        router.get(
            route("tickets.datatable"),
            { q: encodedParams },
            {
                onFinish: () => setLoading(false),
            }
        );
    };

    // Handle status filter via stat cards
    const handleStatusFilter = (filterType) => {
        // console.log("🔄 Changing filter to:", filterType);
        setLoading(true);

      const params = {
    page: 1,
    pageSize: pagination?.per_page || 10,
    search: filters?.search || "",
    project: filters?.project || "",
    status: filterType,
    sortField: filters?.sortField || "created_at",
    sortOrder: filters?.sortOrder || "desc",
};

const encodedParams = btoa(JSON.stringify(params));
        // console.log("📤 Sending params:", params);

       router.get(
    route("tickets.datatable"),
    { q: encodedParams },
    {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => setLoading(false),
    });
       
    };

    // Handle action navigation
    const handleAction = async (action, ticketId, record) => {
        try {
            // 🔹 Find all unread notifications related to this ticket
            const relatedNotifs = notifications.filter((notif) => {
                const data =
                    typeof notif.data === "string"
                        ? JSON.parse(notif.data)
                        : notif.data;

                return (
                    !notif.read_at &&
                    (data?.ticket_id === ticketId ||
                        notif.ticket_id === ticketId)
                );
            });

            // 🔹 Mark them as read
            for (const notif of relatedNotifs) {
                await markAsRead(notif.id);
            }
        } catch (error) {
            console.warn("⚠️ Failed to mark notifications as read:", error);
        }

        // 🔹 Continue with existing navigation logic
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

    // Check if ticket is open
    const isTicketOpen = (status) => {
        const openStatuses = ["In Progress"];
        return openStatuses.includes(status);
    };

    const renderActions = (_, record) => {
        const actions = Array.isArray(record?.actions) ? record.actions : [];

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

        const canCreateChild = isTicketOpen(record?.status);
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

        const workflowOptions = actions
            .filter(
                (action) =>
                    action &&
                    action.toLowerCase() !== "view" &&
                    action.trim() !== ""
            )
            .map((action) => {
                const iconMap = {
                    assess: <Filter className="w-4 h-4 text-primary" />,
                    approve: <CheckCircle className="w-4 h-4 text-green-600" />,
                    assign: <UserPlus className="w-4 h-4 text-blue-500" />,
                    resolve: <Wrench className="w-4 h-4 text-purple-600" />,
                    close: (
                        <BadgeCheckIcon className="w-4 h-4 text-green-500" />
                    ),
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

        const allMenuItems = [
            viewOption,
            createChildOption,
            ...workflowOptions,
        ].filter(Boolean);

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
            sorter: true,
        },
        {
            title: "Requestor",
            dataIndex: "emp_name",
            key: "emp_name",
            width: 150,
            sorter: true,
        },
        {
            title: "Project",
            dataIndex: "project_name",
            key: "project_name",
            width: 150,
            sorter: true,
        },
        {
            title: "Type",
            dataIndex: "type_of_request",
            key: "type_of_request",
            width: 150,
            sorter: true,
        },
        {
            title: "Status",
            dataIndex: "status",
            key: "status",
            width: 100,
            sorter: true,
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
            sorter: true,
        },
        {
            title: "Actions",
            key: "actions",
            width: 100,
            render: (_, record) => renderActions(_, record),
        },
    ];

    return (
        <AuthenticatedLayout>
            {isLoading ? (
                <TicketFormSkeleton />
            ) : (
                <>
                    {/* Stat Cards */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                        <StatCard
                            title="All Tickets"
                            value={statusCounts?.all || 0}
                            color="primary"
                            icon={AppstoreOutlined}
                            onClick={() => handleStatusFilter("all")}
                            isActive={activeFilter === "all"}
                            filterType="all"
                        />
                        <StatCard
                            title="Active"
                            value={statusCounts?.active || 0}
                            color="info"
                            icon={ThunderboltOutlined}
                            onClick={() => handleStatusFilter("active")}
                            isActive={activeFilter === "active"}
                            filterType="active"
                        />
                        <StatCard
                            title="Urgent"
                            value={statusCounts?.urgent || 0}
                            color="error"
                            icon={ExclamationCircleOutlined}
                            onClick={() => handleStatusFilter("urgent")}
                            isActive={activeFilter === "urgent"}
                            filterType="urgent"
                        />
                        <StatCard
                            title="In Progress"
                            value={statusCounts?.in_progress || 0}
                            color="warning"
                            icon={ToolOutlined}
                            onClick={() => handleStatusFilter("in_progress")}
                            isActive={activeFilter === "in_progress"}
                            filterType="in_progress"
                        />
                        <StatCard
                            title="Closed"
                            value={statusCounts?.closed || 0}
                            color="success"
                            icon={CheckCircleOutlined}
                            onClick={() => handleStatusFilter("closed")}
                            isActive={activeFilter === "closed"}
                            filterType="closed"
                        />
                    </div>

                    {/* Table Container */}
                    <div className="p-6 bg-base-200 min-h-screen transition-all duration-300 border border-base-300 rounded-xl shadow-sm">
                        {/* Filters */}
                        <div className="flex flex-wrap justify-between items-center mb-4 gap-3">
                            <div className="flex items-center gap-2">
                                <Search className="w-4 h-4 text-base-content/70" />
                                <input
                                    type="text"
                                    placeholder="Search tickets..."
                                    value={searchValue}
                                    onChange={(e) =>
                                        handleSearch(e.target.value)
                                    }
                                    className="input input-bordered input-sm w-64"
                                />
                            </div>

                            <Space wrap>
                                <Select
                                    placeholder="Filter by Project"
                                    allowClear
                                    style={{ width: 180 }}
                                    onChange={handleProjectChange}
                                    value={filters?.project || undefined}
                                >
                                    {projects?.map((p) => (
                                        <Select.Option key={p} value={p}>
                                            {p}
                                        </Select.Option>
                                    ))}
                                </Select>

                                {/* <button className="btn btn-outline btn-sm flex items-center gap-2">
                                    <Filter className="w-4 h-4" />
                                    Filters
                                </button> */}
                            </Space>
                        </div>

                        {/* Table */}
                        <Spin spinning={loading}>
                            {tickets && tickets.length > 0 ? (
                                <Table
                                    columns={columns}
                                    dataSource={tickets}
                                    rowKey={(record) => record.ticket_id}
                                    pagination={{
                                        current: pagination?.current_page || 1,
                                        pageSize: pagination?.per_page || 10,
                                        total: pagination?.total || 0,
                                        showSizeChanger: true,
                                        showQuickJumper: true,
                                        pageSizeOptions: ["10", "20", "50"],
                                        showTotal: (total, range) =>
                                             `Showing ${range[0]}-${range[1]} of ${total} entries`,
                                    }}
                                    onChange={handleTableChange}
                                    bordered
                                    size="middle"
                                    scroll={{ x: 1000, y: "50vh" }}
                                    className="bg-base-100 rounded-xl shadow-md"
                                    loading={loading}
                                />
                            ) : (
                                <div className="flex flex-col items-center justify-center py-20 bg-base-100 rounded-xl shadow-md">
                                    <Empty description="No tickets found" />
                                </div>
                            )}
                        </Spin>
                    </div>
                </>
            )}
        </AuthenticatedLayout>
    );
}

import React, { useState } from "react";
import axios from "axios";
import { usePage, router } from "@inertiajs/react";
import {
    Table,
    Spin,
    Empty,
    Tag,
    Avatar,
    Tooltip,
    Modal,
    Timeline,
    Pagination,
} from "antd";
import { UserOutlined } from "@ant-design/icons";
import ProjectLayout from "@/Layouts/ProjectLayout";
import ProjectNavbar from "@/Components/ProjectNavbar";
import ImportModal from "./ImportModal";

export default function ProjectsTable() {
    const {
        projects,
        pagination,
        filters: initialFilters,
        departments,
        appName,
    } = usePage().props;
    console.log(usePage().props);

    const [loading, setLoading] = useState(false);
    const [searchValue, setSearchValue] = useState(
        initialFilters?.search || ""
    );
    const [filters, setFilters] = useState(initialFilters || {});
    const [showImportModal, setShowImportModal] = useState(false);

    // Logs modal state
    const [showLogsModal, setShowLogsModal] = useState(false);
    const [projectLogs, setProjectLogs] = useState([]);
    const [logsLoading, setLogsLoading] = useState(false);
    const [selectedProject, setSelectedProject] = useState(null);
    const [logPagination, setLogPagination] = useState({
        current: 1,
        total: 0,
    });

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

    // 📄 Table pagination
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

    // 🎨 Generate consistent avatar color
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

    // 🧠 Fetch project logs (server-side via Axios)
    const fetchProjectLogs = async (projectId, page = 1) => {
        setLogsLoading(true);
        try {
            const res = await axios.get(
                `/${appName}/projects/${projectId}/logs?page=${page}`
            );
            setProjectLogs(res.data.data);
            setLogPagination({
                current: res.data.current_page,
                total: res.data.total,
            });
        } catch (error) {
            console.error("Failed to load logs:", error);
        }
        setLogsLoading(false);
    };

    const handleViewLogs = (projectId) => {
        setSelectedProject(projectId);
        setShowLogsModal(true);
        fetchProjectLogs(projectId, 1);
    };

    // 🧱 Table columns
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
            width: 100,
            render: (assignedTo) => {
                if (!assignedTo || assignedTo.length === 0) {
                    return <span className="text-gray-400">Unassigned</span>;
                }
                const visible = assignedTo.slice(0, 2);
                const hidden = assignedTo.slice(2);
                const remaining = hidden.length;
                const hiddenInitials = hidden.map((p) => p.initials).join(", ");

                return (
                    <Avatar.Group max={{ count: 3 }} size="default">
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
            width: 80,
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
        {
            title: "Actions",
            key: "actions",
            width: 80,
            render: (_, record) => (
                <Tooltip title="View Logs">
                    <button
                        className="text-blue-600 hover:underline"
                        onClick={() => handleViewLogs(record.id)}
                    >
                        View Logs
                    </button>
                </Tooltip>
            ),
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
                            showSizeChanger: true,
                            showQuickJumper: true,
                            pageSizeOptions: ["10", "20", "50"],
                        }}
                        bordered
                        size="middle"
                        scroll={{ x: 1200, y: "50vh" }}
                        className="bg-base-100 rounded-xl shadow-md"
                        onChange={handleTableChange}
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

            {/* 🧾 Logs Modal (Grouped Timeline) */}
            <Modal
                title={`Project Logs - ${selectedProject || ""}`}
                open={showLogsModal}
                onCancel={() => setShowLogsModal(false)}
                footer={null}
                width={700}
            >
                <Spin spinning={logsLoading}>
                    {projectLogs?.length > 0 ? (
                        <div className="max-h-[60vh] overflow-y-auto pr-2">
                            {Object.entries(
                                projectLogs.reduce((groups, log) => {
                                    const logDate = new Date(log.UPDATE_AT);
                                    const today = new Date();
                                    const yesterday = new Date(today);
                                    yesterday.setDate(today.getDate() - 1);

                                    const isToday =
                                        logDate.toDateString() ===
                                        today.toDateString();
                                    const isYesterday =
                                        logDate.toDateString() ===
                                        yesterday.toDateString();

                                    let groupLabel;
                                    if (isToday) {
                                        groupLabel = "Today";
                                    } else if (isYesterday) {
                                        groupLabel = "Yesterday";
                                    } else {
                                        const diffDays = Math.floor(
                                            (today - logDate) /
                                                (1000 * 60 * 60 * 24)
                                        );
                                        groupLabel =
                                            diffDays <= 7
                                                ? "This Week"
                                                : "Older";
                                    }

                                    if (!groups[groupLabel])
                                        groups[groupLabel] = [];
                                    groups[groupLabel].push(log);
                                    return groups;
                                }, {})
                            ).map(([groupLabel, logs]) => (
                                <div key={groupLabel} className="mb-6">
                                    <h3 className="font-semibold text-gray-700 mb-3">
                                        {groupLabel}
                                    </h3>
                                    <Timeline
                                        items={logs.map((log) => ({
                                            color:
                                                log.PROJ_STATUS === "Completed"
                                                    ? "green"
                                                    : log.PROJ_STATUS ===
                                                      "In Progress"
                                                    ? "blue"
                                                    : log.PROJ_STATUS ===
                                                      "On Hold"
                                                    ? "orange"
                                                    : "gray",
                                            children: (
                                                <div>
                                                    <div className="flex justify-between items-center">
                                                        <h4 className="font-semibold text-base">
                                                            {log.ACTION_TYPE}
                                                        </h4>
                                                        <span className="text-xs text-gray-500">
                                                            {new Date(
                                                                log.UPDATE_AT
                                                            ).toLocaleString()}
                                                        </span>
                                                    </div>
                                                    <p className="text-sm text-gray-600 mt-1">
                                                        {log.DESCRIPTION ||
                                                            "No description provided"}
                                                    </p>
                                                    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                                        <Tag color="blue">
                                                            v
                                                            {
                                                                log.PROJECT_VERSION
                                                            }
                                                        </Tag>
                                                        <Tag color="purple">
                                                            {log.PROJ_STATUS}
                                                        </Tag>
                                                        <Tag color="geekblue">
                                                            {log.ACTION_BY}
                                                        </Tag>
                                                    </div>
                                                </div>
                                            ),
                                        }))}
                                    />
                                </div>
                            ))}
                        </div>
                    ) : (
                        <Empty description="No logs available for this project" />
                    )}

                    {/* Pagination */}
                    {logPagination.total > 10 && (
                        <div className="flex justify-center mt-4">
                            <Pagination
                                current={logPagination.current}
                                total={logPagination.total}
                                pageSize={10}
                                onChange={(page) =>
                                    fetchProjectLogs(selectedProject, page)
                                }
                            />
                        </div>
                    )}
                </Spin>
            </Modal>
        </ProjectLayout>
    );
}

import React from "react";
import { Table, Button, Dropdown, Tag, Avatar, Tooltip } from "antd";
import { CheckCircleOutlined, MoreOutlined } from "@ant-design/icons";
import { Ticket } from "lucide-react";

const TaskTable = ({
    tasks,
    loading,
    getStatusColor,
    getActionItems,
    handleQuickComplete,
    isSupervisor,
    getColorFromString,
}) => {
    const priorityColors = {
        1: "red",
        2: "orange",
        3: "blue",
        4: "gray",
        5: "default",
    };

    const priorityLabels = {
        1: "Urgent",
        2: "High",
        3: "Medium",
        4: "Low",
        5: "N/A",
    };

    const columns = [
        {
            title: "Task ID",
            dataIndex: "id",
            key: "id",
            width: 50,
            render: (id) => <strong>{id}</strong>,
        },
        {
            title: "Source Type",
            dataIndex: "source_type",
            key: "source_type",
            width: 100,
            render: (_, record) => {
                const formattedType = record.source_type
                    ? record.source_type
                          .toLowerCase()
                          .replace(/\b\w/g, (char) => char.toUpperCase())
                    : "";

                return (
                    <div>
                        <strong>{formattedType}</strong>
                        {record.source_name && (
                            <div className="text-sm text-gray-500">
                                {record.source_name}
                            </div>
                        )}
                        {record.source_type?.toLowerCase() !== "project" &&
                            record.source_id && (
                                <div className="flex items-center gap-1 text-xs text-blue-800">
                                    <Ticket color="blue" size={12} />{" "}
                                    {record.source_id}
                                </div>
                            )}
                    </div>
                );
            },
        },

        {
            title: "Title",
            dataIndex: "title",
            key: "title",
            width: 130,
            render: (title, record) => (
                <>
                    <div className="font-medium">{title}</div>
                    <div className="text-xs text-gray-500 mt-1">
                        {record.description?.substring(0, 60)}
                        {record.description?.length > 60 ? "..." : ""}
                    </div>
                </>
            ),
        },
        isSupervisor && {
            title: "Programmer",
            dataIndex: "employee_names",
            key: "employee_names",
            width: 50,
            align: "center", // ✅ helps keep avatars centered
            render: (employees) => {
                if (!employees || !employees.length)
                    return (
                        <span className="text-gray-400 text-xs block text-center">
                            No Data
                        </span>
                    );

                return (
                    <div
                        style={{
                            display: "flex",
                            flexDirection: "column",
                            alignItems: "center",
                            gap: "4px",
                            overflowY: "auto",
                            maxHeight: "60px",
                        }}
                    >
                        {employees.map((emp, index) => (
                            <Tooltip key={index} title={emp.fullName}>
                                <Avatar
                                    size={22}
                                    style={{
                                        backgroundColor: getColorFromString(
                                            emp.emp_id
                                        ),
                                        fontSize: "10px",
                                        lineHeight: "20px",
                                    }}
                                >
                                    {emp.initials}
                                </Avatar>
                            </Tooltip>
                        ))}
                    </div>
                );
            },
        },
        {
            title: "Status",
            dataIndex: "status",
            key: "status",
            width: 50,
            render: (status, record) => (
                <Tag color={getStatusColor(status)}>{record.status_label}</Tag>
            ),
        },
        {
            title: "Priority",
            dataIndex: "priority",
            key: "priority",
            width: 50,
            render: (priority) => (
                <Tag color={priorityColors[priority]}>
                    {priorityLabels[priority]}
                </Tag>
            ),
        },
        {
            title: "Date",
            dataIndex: "date",
            key: "date",
            width: 80,
            render: (date) => {
                if (!date) return "";
                const formattedDate = new Date(date).toLocaleDateString(
                    "en-US",
                    {
                        month: "short",
                        day: "numeric",
                        year: "numeric",
                    }
                );
                return formattedDate;
            },
        },

        !isSupervisor && {
            title: "Actions",
            key: "actions",
            width: 80,
            fixed: "right",
            render: (_, record) => (
                <div className="flex gap-2">
                    {record.status !== 3 && (
                        <Button
                            type="primary"
                            size="small"
                            icon={<CheckCircleOutlined />}
                            onClick={() => handleQuickComplete(record.id)}
                            loading={loading}
                        >
                            Done
                        </Button>
                    )}
                    <Dropdown
                        menu={{ items: getActionItems(record) }}
                        trigger={["click"]}
                    >
                        <Button size="small" icon={<MoreOutlined />} />
                    </Dropdown>
                </div>
            ),
        },
    ].filter(Boolean);

    return (
        <Table
            dataSource={tasks}
            columns={columns}
            rowKey="id"
            bordered
            pagination={{ pageSize: 10 }}
            size="middle"
            scroll={{ x: 1000 }}
            loading={loading}
            style={{ tableLayout: "fixed" }} // 🔒 Forces strict column widths
        />
    );
};

export default TaskTable;

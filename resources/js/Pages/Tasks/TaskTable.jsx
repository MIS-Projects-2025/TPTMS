import React from "react";
import { Table, Button, Dropdown, Tag } from "antd";
import { CheckCircleOutlined, MoreOutlined } from "@ant-design/icons";

const TaskTable = ({
    tasks,
    loading,
    getStatusColor,
    getActionItems,
    handleQuickComplete,
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
            width: 120,
            render: (id) => <strong>{id}</strong>,
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
        {
            title: "Status",
            dataIndex: "status",
            key: "status",
            width: 130,
            render: (status, record) => (
                <Tag color={getStatusColor(status)}>{record.status_label}</Tag>
            ),
        },
        {
            title: "Priority",
            dataIndex: "priority",
            key: "priority",
            width: 100,
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
            width: 110,
        },
        {
            title: "Actions",
            key: "actions",
            width: 100,
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
    ];

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
        />
    );
};

export default TaskTable;

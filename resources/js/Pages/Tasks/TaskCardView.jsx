import React from "react";
import { Card, Button, Dropdown, Tag, Avatar, Tooltip } from "antd";
import { CheckCircleOutlined, MoreOutlined } from "@ant-design/icons";

const TaskCardView = ({
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

    return (
        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            {tasks.map((task) => (
                <Card
                    key={task.id}
                    className="shadow-sm border border-base-300 hover:shadow-md transition-shadow"
                    actions={
                        !isSupervisor &&
                        [
                            task.status !== 3 && (
                                <Button
                                    type="link"
                                    icon={<CheckCircleOutlined />}
                                    onClick={() => handleQuickComplete(task.id)}
                                    loading={loading}
                                >
                                    Complete
                                </Button>
                            ),
                            <Dropdown
                                menu={{ items: getActionItems(task) }}
                                trigger={["click"]}
                            >
                                <Button type="link" icon={<MoreOutlined />}>
                                    More
                                </Button>
                            </Dropdown>,
                        ].filter(Boolean)
                    }
                >
                    <div className="mb-3">
                        <div className="flex justify-between items-start mb-2">
                            <span className="font-semibold text-lg">
                                {task.id}
                            </span>
                            <Tag color={getStatusColor(task.status)}>
                                {task.status_label}
                            </Tag>
                        </div>

                        {/* 👇 Show programmer avatars if supervisor */}
                        {isSupervisor && task.employee_names?.length > 0 && (
                            <div className="flex flex-wrap gap-1 mb-2">
                                {task.employee_names.map((emp, index) => (
                                    <Tooltip key={index} title={emp.fullName}>
                                        <Avatar
                                            size={24}
                                            style={{
                                                backgroundColor:
                                                    getColorFromString(
                                                        emp.emp_id
                                                    ),
                                                fontSize: "11px",
                                            }}
                                        >
                                            {emp.initials}
                                        </Avatar>
                                    </Tooltip>
                                ))}
                            </div>
                        )}
                    </div>

                    <h3 className="font-bold mb-2">{task.title}</h3>
                    <p className="text-sm text-gray-600 mb-3">
                        {task.description}
                    </p>

                    <div className="flex justify-between items-center text-xs text-gray-500">
                        <span>📅 {task.date}</span>
                        <Tag color={priorityColors[task.priority] || "default"}>
                            {priorityLabels[task.priority] || "N/A"}
                        </Tag>
                    </div>
                </Card>
            ))}
        </div>
    );
};

export default TaskCardView;

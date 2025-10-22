// Pages/TaskIndex.jsx
import React from "react";
import { usePage } from "@inertiajs/react";
import { Table, Card, Tag, Empty } from "antd";
import TaskLayout from "@/Layouts/TaskLayout";
import TaskNavbar from "@/Components/TaskNavBar";
import { useTask } from "@/hooks/useTask";

const TaskIndex = () => {
    const { tasks } = usePage().props;

    // 🎯 One hook to rule them all!
    const {
        // Filter states
        selectedDates,
        selectedStatus,
        searchTerm,

        // View state
        isCardView,

        // Computed
        filteredTasks,

        // Setters
        setSelectedDates,
        setSelectedStatus,
        setSearchTerm,

        // Actions
        resetFilters,
        toggleView,
    } = useTask(tasks);

    // Table columns configuration
    const columns = [
        { title: "Task ID", dataIndex: "TASK_ID", key: "TASK_ID" },
        { title: "Title", dataIndex: "TASK_TITLE", key: "TASK_TITLE" },
        {
            title: "Status",
            dataIndex: "STATUS",
            key: "STATUS",
            render: (status) => {
                const colors = {
                    1: "gold",
                    2: "blue",
                    3: "green",
                    4: "orange",
                    5: "red",
                };
                const labels = {
                    1: "Pending",
                    2: "In Progress",
                    3: "Completed",
                    4: "On Hold",
                    5: "Cancelled",
                };
                return <Tag color={colors[status]}>{labels[status]}</Tag>;
            },
        },
        { title: "Created By", dataIndex: "CREATED_BY", key: "CREATED_BY" },
        { title: "Date", dataIndex: "TASK_DATE", key: "TASK_DATE" },
    ];

    return (
        <TaskLayout
            selectedDates={selectedDates}
            onDateChange={setSelectedDates}
            onFilterStatus={setSelectedStatus}
            onResetFilters={resetFilters}
        >
            <TaskNavbar
                isCardView={isCardView}
                toggleView={toggleView}
                searchTerm={searchTerm}
                onSearch={setSearchTerm}
            />

            <div className="bg-base-100 rounded-xl shadow-md p-4">
                {isCardView ? (
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {filteredTasks.map((task) => (
                            <Card
                                key={task.TASK_ID}
                                title={task.TASK_TITLE}
                                className="shadow-sm border border-base-300"
                                extra={<Tag color="blue">{task.TASK_ID}</Tag>}
                            >
                                <p className="text-sm mb-2 text-gray-600">
                                    {task.TASK_DESCRIPTION}
                                </p>
                                <div className="flex justify-between text-sm mt-3">
                                    <span className="badge badge-outline">
                                        Status:{" "}
                                        {task.STATUS === 3
                                            ? "✅ Completed"
                                            : "🕓 Pending"}
                                    </span>
                                    <span className="text-xs text-gray-500">
                                        {task.TASK_DATE}
                                    </span>
                                </div>
                            </Card>
                        ))}
                    </div>
                ) : filteredTasks.length === 0 ? (
                    <Empty description="No tasks found." />
                ) : (
                    <Table
                        dataSource={filteredTasks}
                        columns={columns}
                        rowKey="TASK_ID"
                        bordered
                        pagination={{ pageSize: 10 }}
                        size="middle"
                        scroll={{ x: 800 }}
                    />
                )}
            </div>
        </TaskLayout>
    );
};

export default TaskIndex;

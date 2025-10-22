import React, { useState, useMemo } from "react";
import { usePage } from "@inertiajs/react";
import { Table, Card, Tag, Tooltip } from "antd";
import { List, LayoutGrid } from "lucide-react";
import TaskLayout from "@/Layouts/TaskLayout";
import TaskNavbar from "@/Components/TaskNavBar";

const TaskIndex = () => {
    const { tasks } = usePage().props;
    const [isCardView, setIsCardView] = useState(false);
    const [searchTerm, setSearchTerm] = useState("");
    const [sortKey, setSortKey] = useState(null);

    // 🔍 Filter + Sort logic combined
    const filteredTasks = useMemo(() => {
        let result = [...tasks];

        // ✅ Search filter
        if (searchTerm) {
            const lower = searchTerm.toLowerCase();
            result = result.filter(
                (task) =>
                    task.TASK_TITLE.toLowerCase().includes(lower) ||
                    task.TASK_DESCRIPTION.toLowerCase().includes(lower) ||
                    task.CREATED_BY.toLowerCase().includes(lower)
            );
        }

        // 🔽 Sorting
        if (sortKey === "date") {
            result.sort(
                (a, b) => new Date(b.TASK_DATE) - new Date(a.TASK_DATE)
            );
        } else if (sortKey === "priority") {
            result.sort((a, b) => a.PRIORITY - b.PRIORITY);
        } else if (sortKey === "status") {
            result.sort((a, b) => a.STATUS - b.STATUS);
        }

        return result;
    }, [tasks, searchTerm, sortKey]);

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

    // 🔄 Handlers passed to navbar
    const handleSearch = (value) => setSearchTerm(value);
    const handleSortChange = (value) => setSortKey(value);

    return (
        <TaskLayout>
            <TaskNavbar
                isCardView={isCardView}
                toggleView={() => setIsCardView(!isCardView)}
                onSearch={handleSearch}
                onAddTask={() => console.log("Add new task")}
                onSortChange={handleSortChange}
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
                ) : (
                    <Table
                        dataSource={filteredTasks}
                        columns={columns}
                        rowKey={(record) => record.TASK_ID}
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

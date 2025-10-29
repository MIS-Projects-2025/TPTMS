// Pages/TaskIndex.jsx
import React, { useState } from "react";
import { usePage, router } from "@inertiajs/react";
import {
    Table,
    Card,
    Tag,
    Empty,
    Button,
    Dropdown,
    Modal,
    Input,
    message,
    Alert,
    Spin,
} from "antd";
import {
    CheckCircleOutlined,
    PlayCircleOutlined,
    PauseCircleOutlined,
    CloseCircleOutlined,
    MoreOutlined,
    HistoryOutlined,
    CommentOutlined,
} from "@ant-design/icons";
import TaskLayout from "@/Layouts/TaskLayout";
import TaskNavbar from "@/Components/TaskNavBar";
import useTask from "@/Hooks/useTask";
import axios from "axios";

const TaskIndex = () => {
    const { emp_data, tasks, error } = usePage().props;

    const currentUser = emp_data.emp_id;

    const [loading, setLoading] = useState(false);
    const [historyModal, setHistoryModal] = useState({
        visible: false,
        taskId: null,
        logs: [],
    });
    const [noteModal, setNoteModal] = useState({
        visible: false,
        taskId: null,
    });
    const [noteText, setNoteText] = useState("");

    // Debug log to check what we're receiving
    console.log("Tasks from props:", tasks);
    console.log("Current user:", currentUser);
    console.log(usePage().props);

    const {
        selectedDates,
        selectedStatus,
        searchTerm,
        isCardView,
        filteredTasks,
        setSelectedDates,
        setSelectedStatus,
        setSearchTerm,
        resetFilters,
        toggleView,
    } = useTask(tasks);

    // Quick status update
    const handleStatusChange = async (taskId, newStatus, remarks = null) => {
        setLoading(true);
        try {
            await axios.post(route("tasks.status", taskId), {
                status: newStatus,
                remarks: remarks,
            });
            message.success("Task status updated successfully!");
            router.reload({ only: ["tasks"] });
        } catch (error) {
            console.error("Status update error:", error);
            message.error(
                error.response?.data?.error || "Failed to update task"
            );
        } finally {
            setLoading(false);
        }
    };

    // Quick complete
    const handleQuickComplete = async (taskId) => {
        Modal.confirm({
            title: "Mark as Complete?",
            content: "Are you sure you want to mark this task as completed?",
            okText: "Yes, Complete",
            cancelText: "Cancel",
            onOk: async () => {
                setLoading(true);
                try {
                    await axios.post(route("tasks.complete", taskId));
                    message.success("Task completed!");
                    router.reload({ only: ["tasks"] });
                } catch (error) {
                    console.error("Complete task error:", error);
                    message.error(
                        error.response?.data?.error || "Failed to complete task"
                    );
                } finally {
                    setLoading(false);
                }
            },
        });
    };

    // View history
    const handleViewHistory = async (taskId) => {
        try {
            const response = await axios.get(route("tasks.history", taskId));
            setHistoryModal({
                visible: true,
                taskId,
                logs: response.data.logs,
            });
        } catch (error) {
            console.error("History load error:", error);
            message.error("Failed to load task history");
        }
    };

    // Add note
    const handleAddNote = async () => {
        if (!noteText.trim()) {
            message.warning("Please enter a note");
            return;
        }

        try {
            await axios.post(route("tasks.note", noteModal.taskId), {
                note: noteText,
            });
            message.success("Note added successfully!");
            setNoteModal({ visible: false, taskId: null });
            setNoteText("");
        } catch (error) {
            console.error("Add note error:", error);
            message.error("Failed to add note");
        }
    };

    // Get status color
    const getStatusColor = (status) => {
        const colors = {
            1: "gold",
            2: "blue",
            3: "green",
            4: "orange",
            5: "red",
        };
        return colors[status] || "default";
    };

    // Get source badge color
    const getSourceColor = (sourceType) => {
        const colors = {
            1: "purple", // Ticket
            2: "cyan", // Project
            3: "default", // Manual
        };
        return colors[sourceType] || "default";
    };

    // Action menu items
    const getActionItems = (task) => {
        const items = [];

        // Status change actions
        if (task.status !== 3) {
            items.push({
                key: "complete",
                icon: <CheckCircleOutlined />,
                label: "Mark as Complete",
                onClick: () => handleQuickComplete(task.id),
            });
        }

        if (task.status !== 2 && task.status !== 3) {
            items.push({
                key: "start",
                icon: <PlayCircleOutlined />,
                label: "Start Task",
                onClick: () => handleStatusChange(task.id, 2),
            });
        }

        if (task.status === 2) {
            items.push({
                key: "pause",
                icon: <PauseCircleOutlined />,
                label: "Put On Hold",
                onClick: () => handleStatusChange(task.id, 4),
            });
        }

        if (task.status !== 5 && task.status !== 3) {
            items.push({
                key: "cancel",
                icon: <CloseCircleOutlined />,
                label: "Cancel Task",
                danger: true,
                onClick: () => {
                    Modal.confirm({
                        title: "Cancel Task?",
                        content: "Are you sure you want to cancel this task?",
                        okText: "Yes, Cancel",
                        okButtonProps: { danger: true },
                        onOk: () => handleStatusChange(task.id, 5),
                    });
                },
            });
        }

        items.push(
            { type: "divider" },
            {
                key: "note",
                icon: <CommentOutlined />,
                label: "Add Note",
                onClick: () => setNoteModal({ visible: true, taskId: task.id }),
            },
            {
                key: "history",
                icon: <HistoryOutlined />,
                label: "View History",
                onClick: () => handleViewHistory(task.id),
            }
        );

        return items;
    };

    // Table columns
    const columns = [
        {
            title: "Task ID",
            dataIndex: "id",
            key: "id",
            width: 120,
            render: (id, record) => (
                <div>
                    <div className="font-semibold">{id}</div>
                    {/* <Tag
                        color={getSourceColor(record.source_type)}
                        className="mt-1"
                    >
                        {record.source_label}
                    </Tag> */}
                </div>
            ),
        },
        {
            title: "Title",
            dataIndex: "title",
            key: "title",
            width: 130,
            render: (title, record) => (
                <div>
                    <div className="font-medium">{title}</div>
                    <div className="text-xs text-gray-500 mt-1">
                        {record.description?.substring(0, 60)}
                        {record.description?.length > 60 ? "..." : ""}
                    </div>
                </div>
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
            render: (priority) => {
                const colors = {
                    1: "red",
                    2: "orange",
                    3: "blue",
                    4: "gray",
                    5: "default",
                };
                const labels = {
                    1: "Urgent",
                    2: "High",
                    3: "Medium",
                    4: "Low",
                    5: "N/A",
                };
                return <Tag color={colors[priority]}>{labels[priority]}</Tag>;
            },
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

    // Card view
    const renderCardView = () => (
        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredTasks.map((task) => (
                <Card
                    key={task.id}
                    className="shadow-sm border border-base-300 hover:shadow-md transition-shadow"
                    actions={[
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
                    ].filter(Boolean)}
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
                        <Tag color={getSourceColor(task.source_type)}>
                            {task.source_label}
                        </Tag>
                    </div>

                    <h3 className="font-bold mb-2">{task.title}</h3>
                    <p className="text-sm text-gray-600 mb-3">
                        {task.description}
                    </p>

                    <div className="flex justify-between items-center text-xs text-gray-500">
                        <span>📅 {task.date}</span>
                        <Tag color={task.priority <= 2 ? "red" : "blue"}>
                            Priority: {task.priority}
                        </Tag>
                    </div>
                </Card>
            ))}
        </div>
    );

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

            {error && (
                <Alert
                    message="Error"
                    description={error}
                    type="error"
                    showIcon
                    closable
                    className="mb-4"
                />
            )}

            <div className="bg-base-100 rounded-xl shadow-md p-4">
                {!tasks || tasks.length === 0 ? (
                    <Empty
                        description="No tasks available. Tasks will appear here once created."
                        image={Empty.PRESENTED_IMAGE_SIMPLE}
                    />
                ) : filteredTasks.length === 0 ? (
                    <Empty description="No tasks found matching your filters." />
                ) : isCardView ? (
                    renderCardView()
                ) : (
                    <Table
                        dataSource={filteredTasks}
                        columns={columns}
                        rowKey="id"
                        bordered
                        pagination={{ pageSize: 10 }}
                        size="middle"
                        scroll={{ x: 1000 }}
                        loading={loading}
                    />
                )}
            </div>

            {/* History Modal */}
            <Modal
                title="Task History"
                open={historyModal.visible}
                onCancel={() =>
                    setHistoryModal({ visible: false, taskId: null, logs: [] })
                }
                footer={null}
                width={600}
            >
                <div className="space-y-3">
                    {historyModal.logs.map((log, index) => (
                        <div
                            key={index}
                            className="border-l-4 border-blue-500 pl-4 py-2"
                        >
                            <div className="font-semibold">
                                {log.action_type}
                            </div>
                            <div className="text-sm text-gray-600">
                                {log.description}
                            </div>
                            {log.old_status && log.new_status && (
                                <div className="text-xs text-gray-500 mt-1">
                                    {log.old_status} → {log.new_status}
                                </div>
                            )}
                            <div className="text-xs text-gray-400 mt-1">
                                by {log.created_by} • {log.created_at}
                            </div>
                        </div>
                    ))}
                </div>
            </Modal>

            {/* Note Modal */}
            <Modal
                title="Add Note"
                open={noteModal.visible}
                onCancel={() => {
                    setNoteModal({ visible: false, taskId: null });
                    setNoteText("");
                }}
                onOk={handleAddNote}
                okText="Add Note"
            >
                <Input.TextArea
                    rows={4}
                    value={noteText}
                    onChange={(e) => setNoteText(e.target.value)}
                    placeholder="Enter your note here..."
                    maxLength={1000}
                />
            </Modal>
        </TaskLayout>
    );
};

export default TaskIndex;

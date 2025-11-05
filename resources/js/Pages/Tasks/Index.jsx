import React, { use, useState } from "react";
import { usePage, router } from "@inertiajs/react";
import { Modal, message, Alert, Button } from "antd";
import {
    CheckCircleOutlined,
    PlayCircleOutlined,
    PauseCircleOutlined,
    CloseCircleOutlined,
    HistoryOutlined,
    CommentOutlined,
} from "@ant-design/icons";
import TaskLayout from "@/Layouts/TaskLayout";
import TaskNavbar from "@/Components/TaskNavBar";
import useTask from "@/Hooks/useTask";
import axios from "axios";
import TaskCardView from "./TaskCardView";
import TaskTable from "./TaskTable";
import TaskHistoryModal from "./TaskHistoryModal";
import TaskNoteModal from "./TaskNoteModal";
import NewTaskModal from "./NewTaskModal";
import TaskSkeleton from "./TaskSkeleton";
const TaskIndex = () => {
    const {
        emp_data,
        tasks,
        error,
        programmers = [],
        is_supervisor,
    } = usePage().props;
    console.log(usePage().props);
    const isSupervisor = is_supervisor;
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
    const [isModalOpen, setIsModalOpen] = useState(false);

    const handleCreateTask = async (data) => {
        console.log("Creating new task with data:", data);
        try {
            const response = await axios.post(route("tasks.store"), data);
            console.log("Task creation response:", response.data);

            if (response.data.success) {
                message.success("Tasks created successfully!");
                router.reload({ only: ["tasks"] });
            } else {
                message.error(response.data.error || "Failed to create tasks");
            }
        } catch (error) {
            console.error("Task creation error:", error);
            message.error(
                error.response?.data?.error || "Error creating tasks"
            );
        }
    };

    // Debug log to check what we're receiving
    console.log("Tasks from props:", tasks);
    console.log("Current user:", currentUser);
    console.log(usePage().props);

    const {
        selectedDates,
        selectedStatus,
        selectedProgrammer,
        searchTerm,
        isCardView,
        filteredTasks,
        setSelectedDates,
        setSelectedStatus,
        setSelectedProgrammer,
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
    const getColorFromString = (str) => {
        const colors = [
            "#f56a00",
            "#7265e6",
            "#ffbf00",
            "#00a2ae",
            "#13c2c2",
            "#eb2f96",
        ];
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        return colors[Math.abs(hash) % colors.length];
    };

    return (
        <TaskLayout
            selectedDates={selectedDates}
            selectedProgrammer={selectedProgrammer}
            onDateChange={setSelectedDates}
            onFilterStatus={setSelectedStatus}
            onProgrammerChange={setSelectedProgrammer}
            onResetFilters={resetFilters}
            programmers={programmers}
            isSupervisor={isSupervisor}
        >
            <TaskNavbar
                isCardView={isCardView}
                toggleView={toggleView}
                searchTerm={searchTerm}
                onSearch={setSearchTerm}
                setIsModalOpen={setIsModalOpen}
                isSupervisor={isSupervisor}
            />

            <>
                <NewTaskModal
                    open={isModalOpen}
                    onClose={() => setIsModalOpen(false)}
                    onCreate={handleCreateTask}
                    empId={emp_data?.emp_id}
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

                {loading ? (
                    <TaskSkeleton isCardView={isCardView} />
                ) : isCardView ? (
                    <TaskCardView
                        tasks={filteredTasks}
                        loading={loading}
                        getStatusColor={getStatusColor}
                        getActionItems={getActionItems}
                        handleQuickComplete={handleQuickComplete}
                        isSupervisor={isSupervisor}
                        getColorFromString={getColorFromString}
                    />
                ) : (
                    <TaskTable
                        tasks={filteredTasks}
                        loading={loading}
                        getStatusColor={getStatusColor}
                        getActionItems={getActionItems}
                        handleQuickComplete={handleQuickComplete}
                        isSupervisor={isSupervisor}
                        getColorFromString={getColorFromString}
                    />
                )}

                <TaskHistoryModal
                    visible={historyModal.visible}
                    logs={historyModal.logs}
                    onClose={() =>
                        setHistoryModal({
                            visible: false,
                            taskId: null,
                            logs: [],
                        })
                    }
                />

                <TaskNoteModal
                    visible={noteModal.visible}
                    noteText={noteText}
                    onCancel={() => {
                        setNoteModal({ visible: false, taskId: null });
                        setNoteText("");
                    }}
                    onChange={setNoteText}
                    onOk={handleAddNote}
                />
            </>
        </TaskLayout>
    );
};

export default TaskIndex;

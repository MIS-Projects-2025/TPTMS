// hooks/useTask.js
import { useState, useMemo } from "react";
import dayjs from "dayjs";
import isSameOrAfter from "dayjs/plugin/isSameOrAfter";
import isSameOrBefore from "dayjs/plugin/isSameOrBefore";

dayjs.extend(isSameOrAfter);
dayjs.extend(isSameOrBefore);

export default function useTask(tasks) {
    const today = dayjs();

    // ========================================
    // FILTER STATES
    // ========================================
    const [selectedDates, setSelectedDates] = useState(null);
    const [selectedStatus, setSelectedStatus] = useState(1); // Changed from 1 to null (show all by default)
    const [searchTerm, setSearchTerm] = useState("");
    const [sortKey, setSortKey] = useState(null);

    // ========================================
    // VIEW STATE
    // ========================================
    const [isCardView, setIsCardView] = useState(false);

    // ========================================
    // ACTIONS
    // ========================================

    /**
     * Reset all filters to default values
     */
    const resetFilters = () => {
        setSelectedStatus(null);
        setSelectedDates(null);
        setSearchTerm("");
        setSortKey(null);
    };

    /**
     * Toggle between card and table view
     */
    const toggleView = () => {
        setIsCardView((prev) => !prev);
    };

    // ========================================
    // COMPUTED VALUES
    // ========================================

    /**
     * Filtered and sorted tasks based on all active filters
     */
    const filteredTasks = useMemo(() => {
        let result = [...tasks];

        // Date filter
        const [start, end] =
            selectedDates && selectedDates.length === 2
                ? selectedDates
                : [null, null];

        if (start && end) {
            result = result.filter((task) => {
                const taskDate = dayjs(task.date).startOf("day"); // Updated: use 'date' instead of 'TASK_DATE'
                return (
                    taskDate.isSameOrAfter(start) &&
                    taskDate.isSameOrBefore(end)
                );
            });
        }

        // Status filter
        if (selectedStatus !== null && selectedStatus !== undefined) {
            result = result.filter((task) => task.status === selectedStatus); // Updated: use 'status' instead of 'STATUS'
        }

        // Search filter
        if (searchTerm) {
            const lower = searchTerm.toLowerCase();
            result = result.filter(
                (task) =>
                    task.title?.toLowerCase().includes(lower) || // Updated: use 'title' instead of 'TASK_TITLE'
                    task.description?.toLowerCase().includes(lower) || // Updated: use 'description' instead of 'TASK_DESCRIPTION'
                    task.created_by?.toLowerCase().includes(lower) || // Updated: use 'created_by' instead of 'CREATED_BY'
                    task.id?.toLowerCase().includes(lower) // Added: search by task ID
            );
        }

        // Sorting
        if (sortKey === "date") {
            result.sort(
                (a, b) => new Date(b.date) - new Date(a.date) // Updated: use 'date'
            );
        } else if (sortKey === "priority") {
            result.sort((a, b) => a.priority - b.priority); // Updated: use 'priority'
        } else if (sortKey === "status") {
            result.sort((a, b) => a.status - b.status); // Updated: use 'status'
        }

        return result;
    }, [tasks, searchTerm, sortKey, selectedDates, selectedStatus]);

    // ========================================
    // STATISTICS (Bonus feature)
    // ========================================
    const statistics = useMemo(() => {
        return {
            total: tasks.length,
            pending: tasks.filter((t) => t.status === 1).length,
            inProgress: tasks.filter((t) => t.status === 2).length,
            completed: tasks.filter((t) => t.status === 3).length,
            onHold: tasks.filter((t) => t.status === 4).length,
            cancelled: tasks.filter((t) => t.status === 5).length,
        };
    }, [tasks]);

    // ========================================
    // RETURN API
    // ========================================
    return {
        // Filter states
        selectedDates,
        selectedStatus,
        searchTerm,
        sortKey,

        // View state
        isCardView,

        // Computed values
        filteredTasks,
        statistics,

        // Filter setters
        setSelectedDates,
        setSelectedStatus,
        setSearchTerm,
        setSortKey,

        // View setters
        setIsCardView,

        // Actions
        resetFilters,
        toggleView,
    };
}

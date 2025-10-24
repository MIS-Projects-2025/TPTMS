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
    const [selectedStatus, setSelectedStatus] = useState(1);
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
        setSelectedDates([today.startOf("day"), today.endOf("day")]);
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
                const taskDate = dayjs(task.TASK_DATE).startOf("day");
                return (
                    taskDate.isSameOrAfter(start) &&
                    taskDate.isSameOrBefore(end)
                );
            });
        }

        // Status filter
        if (selectedStatus) {
            result = result.filter((task) => task.STATUS === selectedStatus);
        }

        // Search filter
        if (searchTerm) {
            const lower = searchTerm.toLowerCase();
            result = result.filter(
                (task) =>
                    task.TASK_TITLE?.toLowerCase().includes(lower) ||
                    task.TASK_DESCRIPTION?.toLowerCase().includes(lower) ||
                    task.CREATED_BY?.toLowerCase().includes(lower)
            );
        }

        // Sorting
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
    }, [tasks, searchTerm, sortKey, selectedDates, selectedStatus]);

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

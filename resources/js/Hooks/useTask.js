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
    const [selectedStatus, setSelectedStatus] = useState(null); // Show all by default
    const [selectedProgrammer, setSelectedProgrammer] = useState(null); // Add programmer filter state
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
        setSelectedProgrammer(null); // Reset programmer filter
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
        // console.log("All tasks:", tasks);
        // console.log("Selected programmer:", selectedProgrammer);

        let result = [...tasks];

        // Date filter
        const [start, end] =
            selectedDates && selectedDates.length === 2
                ? selectedDates
                : [null, null];

        if (start && end) {
            result = result.filter((task) => {
                const taskDate = dayjs(task.date).startOf("day");
                return (
                    taskDate.isSameOrAfter(start) &&
                    taskDate.isSameOrBefore(end)
                );
            });
        }

        // Status filter
        if (selectedStatus !== null && selectedStatus !== undefined) {
            result = result.filter((task) => task.status === selectedStatus);
        }

        // Programmer filter - DEBUGGING VERSION
        if (selectedProgrammer) {
            // console.log("Filtering by programmer:", selectedProgrammer);
            result = result.filter((task) => {
                // console.log("Task employee data:", {
                //     id: task.id,
                //     employee_ids: task.employee_ids,
                //     employid: task.employid, // Check if it's called employid instead
                //     assigned_to: task.assigned_to, // Check other possible field names
                // });

                // Try multiple possible field names
                const isAssignedToProgrammer =
                    task.employee_ids?.includes(selectedProgrammer) ||
                    task.employid === selectedProgrammer ||
                    task.assigned_to === selectedProgrammer ||
                    (Array.isArray(task.employee_ids) &&
                        task.employee_ids.includes(selectedProgrammer));

                // console.log(
                //     "Is assigned to programmer:",
                //     isAssignedToProgrammer
                // );
                return isAssignedToProgrammer;
            });
        }

        // Search filter
        if (searchTerm) {
            const lower = searchTerm.toLowerCase();
            result = result.filter(
                (task) =>
                    task.title?.toLowerCase().includes(lower) ||
                    task.description?.toLowerCase().includes(lower) ||
                    task.created_by?.toLowerCase().includes(lower) ||
                    task.id?.toLowerCase().includes(lower)
            );
        }

        // Sorting
        if (sortKey === "date") {
            result.sort((a, b) => new Date(b.date) - new Date(a.date));
        } else if (sortKey === "priority") {
            result.sort((a, b) => a.priority - b.priority);
        } else if (sortKey === "status") {
            result.sort((a, b) => a.status - b.status);
        }

        // console.log("Filtered result:", result);
        return result;
    }, [
        tasks,
        searchTerm,
        sortKey,
        selectedDates,
        selectedStatus,
        selectedProgrammer,
    ]);

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
        selectedProgrammer, // Add to return
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
        setSelectedProgrammer, // Add setter
        setSearchTerm,
        setSortKey,

        // View setters
        setIsCardView,

        // Actions
        resetFilters,
        toggleView,
    };
}

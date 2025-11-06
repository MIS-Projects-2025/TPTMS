import { useState, useEffect } from "react";
import axios from "axios";

// In-memory cache to avoid redundant API calls
let constantsCache = null;
let fetchPromise = null;

// Fallback constants
const FALLBACK_CONSTANTS = {
    projectStatuses: [
        { value: 1, label: "Planning", color: "gold" },
        { value: 2, label: "Ready", color: "cyan" },
        { value: 3, label: "In Progress", color: "processing" },
        { value: 4, label: "On Hold", color: "orange" },
        { value: 5, label: "Deployed", color: "geekblue" },
        { value: 6, label: "Cancelled", color: "red" },
        { value: 7, label: "Inactive", color: "default" },
    ],
    requestTypes: [
        { value: 1, label: "New System" },
        { value: 2, label: "Modification" },
        { value: 3, label: "Enhancement" },
        { value: 4, label: "Adjustment" },
        { value: 5, label: "Testing" },
        { value: 6, label: "Parallel Run" },
    ],
    ticketStatuses: [
        { value: 1, label: "New" },
        { value: 2, label: "Triaged" },
        { value: 3, label: "Approved" },
        { value: 4, label: "In Progress" },
        { value: 5, label: "Resolved" },
        { value: 6, label: "Closed" },
        { value: 7, label: "Rejected" },
        { value: 8, label: "On Hold" },
        { value: 9, label: "Returned" },
    ],
};

// Helper to find item by value (loose equality)
const findByValue = (arr, value, key = "label", defaultVal = "Unknown") =>
    arr?.find((x) => x.value == value)?.[key] || defaultVal;

// Normalize numeric IDs from API
const normalizeConstants = (data) => ({
    ...data,
    projectStatuses: data.projectStatuses.map((s) => ({
        ...s,
        value: Number(s.value),
    })),
    requestTypes: data.requestTypes.map((s) => ({
        ...s,
        value: Number(s.value),
    })),
    ticketStatuses: data.ticketStatuses.map((s) => ({
        ...s,
        value: Number(s.value),
    })),
});

export default function useProjectConstants() {
    const [constants, setConstants] = useState(constantsCache);
    const [loading, setLoading] = useState(!constantsCache);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (constantsCache) {
            setConstants(constantsCache);
            setLoading(false);
            return;
        }

        if (fetchPromise) {
            fetchPromise
                .then((data) => setConstants(data))
                .catch((err) => setError(err))
                .finally(() => setLoading(false));
            return;
        }

        const controller = new AbortController();

        fetchPromise = axios
            .get("/api/project-constants", { signal: controller.signal })
            .then((response) => {
                if (response.data.success) {
                    const normalized = normalizeConstants(response.data.data);
                    constantsCache = normalized;
                    setConstants(normalized);
                    return normalized;
                } else {
                    throw new Error("Failed to fetch project constants");
                }
            })
            .catch((err) => {
                console.error(
                    "Error fetching project constants:",
                    err.response?.data || err.message || err
                );
                setError(err);
                constantsCache = FALLBACK_CONSTANTS;
                setConstants(FALLBACK_CONSTANTS);
                return FALLBACK_CONSTANTS;
            })
            .finally(() => {
                setLoading(false);
                fetchPromise = null;
            });

        return () => controller.abort();
    }, []);

    return {
        constants,
        loading,
        error,
        projectStatuses: constants?.projectStatuses || [],
        requestTypes: constants?.requestTypes || [],
        ticketStatuses: constants?.ticketStatuses || [],
        getStatusLabel: (id) => findByValue(constants?.projectStatuses, id),
        getStatusColor: (id) =>
            findByValue(constants?.projectStatuses, id, "color", "default"),
        getRequestTypeLabel: (id) => findByValue(constants?.requestTypes, id),
        getTicketStatusLabel: (id) =>
            findByValue(constants?.ticketStatuses, id),
    };
}

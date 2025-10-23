import { useState } from "react";
import axios from "axios";

export default function useProjectLogs(appName) {
    const [projectLogs, setProjectLogs] = useState([]);
    const [logsLoading, setLogsLoading] = useState(false);
    const [pagination, setPagination] = useState({ current: 1, total: 0 });

    const fetchProjectLogs = async (projectId, page = 1) => {
        setLogsLoading(true);
        try {
            const res = await axios.get(
                `/${appName}/projects/${projectId}/logs?page=${page}`
            );
            setProjectLogs(res.data.data);
            setPagination({
                current: res.data.current_page,
                total: res.data.total,
            });
        } catch (error) {
            console.error("Failed to load logs:", error);
        }
        setLogsLoading(false);
    };

    return {
        projectLogs,
        logsLoading,
        pagination,
        fetchProjectLogs,
    };
}

import { useMemo, useState } from "react";

export default function useTicketTable(tickets) {
    const [searchText, setSearchText] = useState("");
    const [selectedProject, setSelectedProject] = useState(null);
    const [sortOrder, setSortOrder] = useState("desc");
    const [activeFilter, setActiveFilter] = useState("all");

    // Extract unique project list
    const projects = useMemo(
        () => [...new Set(tickets.map((t) => t.project_name))],
        [tickets]
    );

    // Count tickets per status group
    const statusCounts = useMemo(() => {
        const counts = {
            all: tickets.length,
            active: 0,
            urgent: 0,
            "in progress": 0,
            closed: 0,
        };

        tickets.forEach((t) => {
            const status = t.status?.toLowerCase();
            const type = t.type_of_request?.toLowerCase();

            // Active group: New, Triaged statuses (regardless of actions)
            if (["new", "triaged"].includes(status)) {
                counts.active += 1;
            }

            // In Progress
            if (status === "in progress") {
                counts["in progress"] += 1;
            }

            // Urgent group: Testing, Parallel Run request types
            if (["testing request", "parallel run request"].includes(type)) {
                counts.urgent += 1;
            }

            // Closed group
            if (status === "closed") {
                counts.closed += 1;
            }
        });

        return counts;
    }, [tickets]);

    // Filter + Sort logic
    const filteredTickets = useMemo(() => {
        return tickets
            .filter((t) => {
                const matchesSearch =
                    t.ticket_id
                        .toLowerCase()
                        .includes(searchText.toLowerCase()) ||
                    t.project_name
                        .toLowerCase()
                        .includes(searchText.toLowerCase());

                const matchesProject =
                    !selectedProject || t.project_name === selectedProject;

                let matchesStatus = false;
                switch (activeFilter) {
                    case "all":
                        matchesStatus = true;
                        break;
                    case "active":
                        // Active: tickets in New or Triaged status
                        matchesStatus = ["new", "triaged"].includes(
                            t.status?.toLowerCase()
                        );
                        break;
                    case "urgent":
                        // Urgent: Testing or Parallel Run request types
                        matchesStatus = [
                            "testing request",
                            "parallel run request",
                        ].includes(t.type_of_request?.toLowerCase());
                        break;
                    case "in progress":
                        matchesStatus =
                            t.status?.toLowerCase() === "in progress";
                        break;
                    case "closed":
                        matchesStatus = t.status?.toLowerCase() === "closed";
                        break;
                    default:
                        matchesStatus = true;
                }

                return matchesSearch && matchesProject && matchesStatus;
            })
            .sort((a, b) =>
                sortOrder === "asc"
                    ? new Date(a.created_at) - new Date(b.created_at)
                    : new Date(b.created_at) - new Date(a.created_at)
            );
    }, [tickets, searchText, selectedProject, sortOrder, activeFilter]);

    return {
        searchText,
        setSearchText,
        selectedProject,
        setSelectedProject,
        sortOrder,
        setSortOrder,
        activeFilter,
        setActiveFilter,
        projects,
        statusCounts,
        filteredTickets,
    };
}

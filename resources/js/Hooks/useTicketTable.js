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
        const counts = { all: tickets.length, active: 0, urgent: 0, closed: 0 };

        tickets.forEach((t) => {
            const status = t.status?.toLowerCase();
            const type = t.type_of_request?.toLowerCase();

            // Active group: New, Triaged, In Progress AND user has actions
            if (
                ["new", "triaged", "in progress"].includes(status) &&
                t.actions &&
                t.actions.length > 0
            ) {
                counts.active += 1;
            }

            // Urgent group: Testing, Parallel Run
            if (["testing", "parallel run"].includes(type)) counts.urgent += 1;

            // Closed group
            if (status === "closed") counts.closed += 1;
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
                        matchesStatus =
                            ["new", "triaged", "in progress"].includes(
                                t.status?.toLowerCase()
                            ) &&
                            t.actions &&
                            t.actions.length > 0;
                        break;
                    case "urgent":
                        matchesStatus = ["testing", "parallel run"].includes(
                            t.type_of_request?.toLowerCase()
                        );
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

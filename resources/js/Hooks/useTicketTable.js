import { useState, useMemo } from "react";

export default function useTicketTable(tickets = []) {
    const [searchText, setSearchText] = useState("");
    const [selectedProject, setSelectedProject] = useState(null);
    const [sortOrder, setSortOrder] = useState("asc");
    const [activeFilter, setActiveFilter] = useState("all");

    // --- Filter by search and project
    const filteredTickets = useMemo(() => {
        return tickets.filter((t) => {
            const matchesSearch = t.project_name
                ?.toLowerCase()
                .includes(searchText.toLowerCase());
            const matchesProject = selectedProject
                ? t.project_name === selectedProject
                : true;
            return matchesSearch && matchesProject;
        });
    }, [tickets, searchText, selectedProject]);

    // --- Categorization Logic
    const categorizeTicket = (ticket) => {
        const actions = Array.isArray(ticket.actions)
            ? ticket.actions.map((a) => a.toLowerCase())
            : [];

        const typeLabel = (ticket.type_of_request || "").toLowerCase();
        const statusLabel = (ticket.status || "").toLowerCase();

        const hasView = actions.includes("view");
        const hasOtherActions = actions.some(
            (a) => a !== "view" && a !== "test" && a !== "parallel"
        );

        const categories = new Set(["all"]);

        // 1️⃣ If only VIEW
        if (actions.length === 1 && hasView) {
            if (statusLabel === "closed") {
                categories.add("closed");
            }
            return Array.from(categories);
        }

        // 2️⃣ If VIEW + other actions OR other actions only
        if (hasOtherActions || (hasView && actions.length > 1)) {
            if (["testing", "parallel run"].includes(typeLabel)) {
                categories.add("urgent");
            } else {
                categories.add("active");
            }
        }

        return Array.from(categories);
    };

    // --- Count tickets per category
    const statusCounts = useMemo(() => {
        const counts = {
            all: 0,
            active: 0,
            urgent: 0,
            closed: 0,
        };

        tickets.forEach((t) => {
            const cats = categorizeTicket(t);
            cats.forEach((c) => {
                counts[c] = (counts[c] || 0) + 1;
            });
        });

        return counts;
    }, [tickets]);

    // --- Apply active filter
    const visibleTickets = useMemo(() => {
        if (activeFilter === "all") return filteredTickets;
        return filteredTickets.filter((t) =>
            categorizeTicket(t).includes(activeFilter)
        );
    }, [filteredTickets, activeFilter]);

    // --- Project list
    const projects = useMemo(() => {
        const unique = new Set(
            tickets.map((t) => t.project_name).filter(Boolean)
        );
        return Array.from(unique);
    }, [tickets]);

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
        filteredTickets: visibleTickets,
    };
}

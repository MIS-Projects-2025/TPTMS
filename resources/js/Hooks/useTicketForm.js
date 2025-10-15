import { useState, useEffect } from "react";

export const useTicketForm = ({
    requestType,
    selectedProject,
    selectedParentTicket,
    ticketOptions,
    ticketProjects,
    onProjectChange,
    onParentTicketChange,
}) => {
    const [filteredParentTickets, setFilteredParentTickets] =
        useState(ticketOptions);

    const isNewSystem = requestType === 1;
    const isTesting = requestType === 5 || requestType === 6;

    // --- Filter parent tickets based on selected project ---
    useEffect(() => {
        if (!selectedProject) {
            setFilteredParentTickets(ticketOptions);
            return;
        }

        const filtered = ticketOptions.filter(
            (t) => ticketProjects[t.value] === selectedProject
        );
        setFilteredParentTickets(filtered);

        // Clear parent ticket if it's not under the selected project
        if (
            selectedParentTicket &&
            ticketProjects[selectedParentTicket] !== selectedProject
        ) {
            onParentTicketChange(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedProject, selectedParentTicket, ticketOptions]);

    // --- Auto-fill project when parent ticket changes ---
    useEffect(() => {
        if (!selectedParentTicket) return;

        const projectForTicket = ticketProjects[selectedParentTicket];
        if (projectForTicket && projectForTicket !== selectedProject) {
            onProjectChange(projectForTicket);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedParentTicket]);

    // --- Handlers ---
    const handleRequestTypeChange = (value) => {
        if (value === 1) {
            onProjectChange(null);
            onParentTicketChange(null);
        }
    };

    return {
        filteredParentTickets,
        isNewSystem,
        isTesting,
        handleRequestTypeChange,
    };
};

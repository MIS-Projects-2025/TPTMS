// TicketFormTour.jsx
import React from "react";
import { Tour } from "antd";

const TicketFormTour = ({
    open,
    onClose,
    refs,
    isChildTicket,
    isNewSystem,
    isTesting,
}) => {
    const getTourDescription = () => {
        if (isChildTicket) {
            return "For child tickets, the project and parent ticket are pre-selected based on the URL.";
        }

        if (isNewSystem) {
            return "For New System requests, enter a unique project name. No project dropdown or parent ticket selection is needed.";
        }

        if (isTesting) {
            return "For Testing requests, select the project from the dropdown. You can optionally choose a parent ticket. If you select a parent ticket, the project will be automatically filled based on the parent ticket's project.";
        }

        // For Enhancement, Bug Fix, and other types
        return "Select the project from the dropdown. You can optionally choose a parent ticket to link this request. If you select a parent ticket, the project will be automatically filled based on the parent ticket's project name.";
    };

    const tourSteps = [
        {
            title: "Employee Information",
            description:
                "Your employee details are pre-filled here for reference.",
            target: null,
        },
        {
            title: "Type of Request",
            description:
                "Select the type of request you want to create. This determines what other fields are available. Different request types have different requirements and workflows.",
            target: () => refs.requestTypeRef.current,
        },
        {
            title: isChildTicket
                ? "Project & Parent Ticket"
                : isNewSystem
                ? "Project Name"
                : isTesting
                ? "Project & Testing Details"
                : "Project Selection",
            description: getTourDescription(),
            target: () => refs.projectRef.current,
        },
        // Add tester and target date steps only for testing
        ...(isTesting
            ? [
                  {
                      title: "Assign Tester",
                      description:
                          "Select one or multiple testers who will be responsible for testing this request. You can search and select multiple team members.",
                      target: () => refs.testerRef.current,
                  },
                  {
                      title: "Target Date",
                      description:
                          "Set the target completion date for this testing task. You cannot select past dates. This helps track testing deadlines.",
                      target: () => refs.targetDateRef.current,
                  },
              ]
            : []),
        {
            title: "Request Details",
            description:
                "Provide detailed information about your request. Be as specific as possible to help the team understand your needs. Include any relevant context, requirements, or steps to reproduce (for bugs).",
            target: () => refs.detailsRef.current,
        },
        {
            title: "Attachments",
            description:
                "Upload any relevant files, screenshots, or documents that support your request. Accepted file types include images, PDFs, and documents.",
            target: () => refs.attachmentsRef.current,
        },
        {
            title: "Submit Your Ticket",
            description:
                "Once you've filled out all required fields, click here to create your ticket. Make sure all information is accurate before submitting.",
            target: () => refs.submitRef.current,
        },
    ].filter(
        (step) => step.target !== null || step.title === "Employee Information"
    );

    return (
        <Tour
            open={open}
            onClose={onClose}
            steps={tourSteps}
            indicatorsRender={(current, total) => (
                <span>
                    {current + 1} / {total}
                </span>
            )}
        />
    );
};

export default TicketFormTour;

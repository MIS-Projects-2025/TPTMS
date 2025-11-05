import React from "react";
import { usePage, router } from "@inertiajs/react";
import { Tag } from "antd";
import { ArrowRight, Clock, Calendar } from "lucide-react";
import AttachmentUpload from "./AttachmentUpload";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ActionButton from "./ActionButton";
import ActivityTimeline from "./ActivityTimeline";
import ChildTicket from "./ChildTicket";

const ViewDetails = () => {
    const {
        action,
        ticket,
        childTickets = [],
        attachments,
        requestTypes,
        statusTypes,
        availableActions,
        workflowStage,
        ticketHistory = [],
        remarksHistory = [],
        assignedEmployees = [],
        testerInfo = [],
        userRoles = [],
    } = usePage().props;
    console.log(usePage().props);
    console.log(ticket);

    if (!ticket) return <div>No ticket data found</div>;
    const isResubmitAllowed =
        ticket.STATUS === "Returned" && ticket.EMPLOYID === empData.emp_id;

    // Check if ticket is Testing (5) or Parallel Run (6)
    const isTestingOrParallelRun = [5, 6].includes(ticket.TYPE_OF_REQUEST);

    const goToParentTicket = () => {
        if (!ticket.PARENT_TICKET_ID) return;
        const hash = btoa(`${ticket.PARENT_TICKET_ID}:VIEWONLY`);
        router.visit(route("tickets.view", hash), { method: "get" });
    };

    const statusColorMap = {
        New: "bg-blue-500",
        Triaged: "bg-blue-400",
        Approved: "bg-green-500",
        "In Progress": "bg-orange-500",
        Resolved: "bg-purple-500",
        Closed: "bg-green-700",
        Rejected: "bg-red-600",
        "On Hold": "bg-yellow-500",
    };

    const formatDate = (dateString) => {
        if (!dateString) return "N/A";
        const date = new Date(dateString);
        return date.toLocaleDateString("en-US", {
            year: "numeric",
            month: "long",
            day: "numeric",
        });
    };

    return (
        <AuthenticatedLayout>
            <div className="p-6 min-h-screen bg-base-200 flex justify-center">
                <div className="relative max-w-4xl w-full card bg-base-100 rounded-2xl shadow-xl overflow-hidden border-t-4 border-base-content/10 transition-all duration-300">
                    {/* Status + Target Date Badge */}
                    <div className="absolute top-4 right-4 z-10 flex flex-col items-end gap-2">
                        <div className="flex items-center gap-2">
                            {/* Target Date (if Testing or Parallel Run) */}
                            {isTestingOrParallelRun && ticket.TARGET_DATE && (
                                <div className="flex items-center gap-1 bg-orange-500/10 border border-orange-500/20 px-3 py-1 rounded-full text-xs font-medium">
                                    Target Date:
                                    <Calendar
                                        size={14}
                                        className="text-orange-600"
                                    />
                                    <span className="text-orange-700">
                                        {formatDate(ticket.TARGET_DATE)}
                                    </span>
                                </div>
                            )}

                            {/* Status Badge */}
                            <span
                                className={`text-white font-bold px-4 py-2 rounded-full shadow-lg ${
                                    statusColorMap[statusTypes[ticket.STATUS]]
                                }`}
                            >
                                {statusTypes[ticket.STATUS]}
                            </span>
                        </div>
                    </div>

                    {/* Header */}
                    <div className="px-6 py-4">
                        <h2 className="text-2xl font-bold mb-2">
                            Ticket #{ticket.TICKET_ID}
                        </h2>

                        {/* Parent Ticket Button */}
                        {ticket.PARENT_TICKET_ID && (
                            <button
                                onClick={goToParentTicket}
                                className="flex items-center gap-1 bg-base-300/20 hover:bg-base-300/40 px-3 py-1 rounded-full text-sm font-medium transition-colors"
                            >
                                Parent Ticket #{ticket.PARENT_TICKET_ID}{" "}
                                <ArrowRight size={16} />
                            </button>
                        )}

                        {/* Workflow Stage Info */}
                        {workflowStage && (
                            <div className="mt-4 p-4 bg-base-200 rounded-lg">
                                <div className="flex items-center gap-2 mb-2">
                                    <Clock
                                        size={16}
                                        className="text-base-content/60"
                                    />
                                    <span className="text-sm font-semibold">
                                        Workflow Status
                                    </span>
                                </div>
                                <p className="text-sm text-base-content/80">
                                    {workflowStage.pending_action}
                                </p>
                                {workflowStage.last_action && (
                                    <p className="text-xs text-base-content/60 mt-1">
                                        Last action: {workflowStage.last_action}{" "}
                                        by {workflowStage.last_action_by}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Body */}
                    <div className="px-6 py-6 space-y-6">
                        {/* Requester Info */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="space-y-1">
                                <p className="text-xs font-semibold text-base-content/60">
                                    Employee ID
                                </p>
                                <p className="text-lg font-medium">
                                    {ticket.EMPLOYID}
                                </p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-xs font-semibold text-base-content/60">
                                    Employee Name
                                </p>
                                <p className="text-lg font-medium">
                                    {ticket.EMPNAME}
                                </p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-xs font-semibold text-base-content/60">
                                    Department
                                </p>
                                <p className="text-lg font-medium">
                                    {ticket.DEPARTMENT}
                                </p>
                            </div>
                        </div>

                        {/* Request Info */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div className="space-y-1">
                                <p className="text-xs font-semibold text-base-content/60">
                                    Type of Request
                                </p>
                                <p className="text-lg font-medium">
                                    {requestTypes[ticket.TYPE_OF_REQUEST]}
                                </p>
                            </div>
                            <div className="space-y-1">
                                <p className="text-xs font-semibold text-base-content/60">
                                    Project Name
                                </p>
                                <p className="text-lg font-medium">
                                    {ticket.PROJECT_NAME}
                                </p>
                            </div>
                            {ticket.ASSIGNED_TO &&
                                assignedEmployees.length > 0 && (
                                    <div className="space-y-1">
                                        <p className="text-xs font-semibold text-base-content/60">
                                            Assigned To
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {assignedEmployees.map(
                                                (emp, idx) => (
                                                    <span
                                                        key={idx}
                                                        className="px-3 py-1 bg-primary/10 text-primary rounded-full text-sm font-medium"
                                                    >
                                                        {emp.display}
                                                    </span>
                                                )
                                            )}
                                        </div>
                                    </div>
                                )}
                        </div>

                        {/* Details */}
                        <div>
                            <p className="text-xs font-semibold text-base-content/60 mb-1">
                                Description
                            </p>
                            <p className="text-lg">{ticket.DETAILS}</p>
                        </div>

                        {/* Tester Info with Target Date */}
                        {Array.isArray(testerInfo) && testerInfo.length > 0 && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs font-semibold text-base-content/60 mb-1">
                                        Tester
                                    </p>
                                    <p className="text-lg">
                                        {testerInfo.map((t, i) => (
                                            <span key={i}>
                                                {t.TESTER_NAME}
                                                {i < testerInfo.length - 1 && (
                                                    <br />
                                                )}
                                            </span>
                                        ))}
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Attachments */}
                        <div>
                            <p className="text-xs font-semibold text-base-content/60 mb-1">
                                Attachments
                            </p>
                            <AttachmentUpload
                                viewOnly={!isResubmitAllowed}
                                existingFiles={(attachments || []).map(
                                    (file) => ({
                                        name: file.FILE_NAME,
                                        size: file.FILE_SIZE,
                                        type: file.FILE_TYPE,
                                        url: `/storage/${file.FILE_PATH}`,
                                        path: `/storage/${file.FILE_PATH}`,
                                        uid: `backend-${file.ID}`,
                                    })
                                )}
                            />
                        </div>

                        {/* Timestamps */}
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                            <div className="space-y-1">
                                <p className="text-xs font-semibold text-base-content/60">
                                    Created At
                                </p>
                                <p className="text-sm">
                                    {new Date(
                                        ticket.CREATED_AT
                                    ).toLocaleString()}
                                </p>
                            </div>
                            {ticket.RESOLVED_AT && (
                                <div className="space-y-1">
                                    <p className="text-xs font-semibold text-base-content/60">
                                        Resolved At
                                    </p>
                                    <p className="text-sm">
                                        {new Date(
                                            ticket.RESOLVED_AT
                                        ).toLocaleString()}
                                    </p>
                                </div>
                            )}
                            {ticket.CLOSED_AT && (
                                <div className="space-y-1">
                                    <p className="text-xs font-semibold text-base-content/60">
                                        Closed At
                                    </p>
                                    <p className="text-sm">
                                        {new Date(
                                            ticket.CLOSED_AT
                                        ).toLocaleString()}
                                    </p>
                                </div>
                            )}
                            {ticket.RATING && (
                                <div className="space-y-1">
                                    <p className="text-xs font-semibold text-base-content/60">
                                        Rating
                                    </p>
                                    <p className="text-sm">
                                        {"⭐".repeat(ticket.RATING)} (
                                        {ticket.RATING}/5)
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Actions */}
                        {action != "VIEW" &&
                            availableActions &&
                            availableActions.length > 0 && (
                                <div className="mt-6 pt-6 border-t border-base-content/10">
                                    <p className="text-sm font-semibold text-base-content/60 mb-4">
                                        Available Actions
                                    </p>
                                    <div className="flex flex-wrap gap-3">
                                        {availableActions.map((action) => (
                                            <ActionButton
                                                key={action}
                                                action={action}
                                                ticketId={ticket.TICKET_ID}
                                                ticketStatus={ticket.STATUS}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}

                        <ActivityTimeline
                            ticketHistory={ticketHistory}
                            remarksHistory={remarksHistory}
                            statusTypes={statusTypes}
                        />
                        <ChildTicket
                            childTickets={childTickets}
                            requestTypes={requestTypes}
                            statusTypes={statusTypes}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
};

export default ViewDetails;

import React from "react";
import { ChevronDown, ChevronRight, FileCode } from "lucide-react";
import { Collapse, Tag } from "antd";
import { router } from "@inertiajs/react";

const { Panel } = Collapse;
const ChildTicket = ({ childTickets, requestTypes, statusTypes }) => {
    return (
        <div>
            {childTickets && childTickets.length > 0 && (
                <div className="mt-6 pt-6 border-t border-base-content/10">
                    <h3 className="text-lg font-semibold text-base-content/80 mb-3 flex items-center gap-2">
                        <FileCode className="text-primary" size={18} />
                        Child Tickets ({childTickets.length})
                    </h3>

                    <Collapse
                        bordered={false}
                        expandIconPosition="end"
                        expandIcon={({ isActive }) =>
                            isActive ? <ChevronDown /> : <ChevronRight />
                        }
                        className="bg-base-100 rounded-lg shadow-sm"
                    >
                        {childTickets.map((child) => (
                            <Panel
                                key={child.TICKET_ID}
                                header={
                                    <div className="flex justify-between items-center w-full">
                                        <span className="font-medium text-base-content">
                                            #{child.TICKET_ID} —{" "}
                                            {
                                                requestTypes[
                                                    child.TYPE_OF_REQUEST
                                                ]
                                            }
                                        </span>
                                        <Tag color="blue">
                                            {statusTypes[child.STATUS]}
                                        </Tag>
                                    </div>
                                }
                            >
                                <div className="text-sm space-y-2">
                                    <p>
                                        <span className="font-semibold">
                                            Project:
                                        </span>{" "}
                                        {child.PROJECT_NAME}
                                    </p>
                                    <p>
                                        <span className="font-semibold">
                                            Details:
                                        </span>{" "}
                                        {child.DETAILS}
                                    </p>
                                    <p>
                                        <span className="font-semibold">
                                            Created At:
                                        </span>{" "}
                                        {new Date(
                                            child.CREATED_AT
                                        ).toLocaleString()}
                                    </p>
                                    <button
                                        onClick={() => {
                                            const hash = btoa(
                                                `${child.TICKET_ID}:VIEW`
                                            );
                                            router.visit(
                                                route("tickets.view", hash)
                                            );
                                        }}
                                        className="mt-3 px-3 py-1 bg-primary text-white rounded-lg hover:bg-primary/80 transition"
                                    >
                                        View Details
                                    </button>
                                </div>
                            </Panel>
                        ))}
                    </Collapse>
                </div>
            )}
        </div>
    );
};

export default ChildTicket;

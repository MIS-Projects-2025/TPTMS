import React, { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import { Modal, message } from "antd";
import {
    Check,
    X,
    RefreshCw,
    UserPlus,
    CheckCircle,
    XCircle,
    Pause,
} from "lucide-react";
import AttachmentUpload from "./AttachmentUpload";

const ActionButton = ({ action, ticketId, ticketStatus }) => {
    const [showModal, setShowModal] = useState(false);
    const [remarks, setRemarks] = useState("");
    const [selectedProgrammers, setSelectedProgrammers] = useState([]);
    const [rating, setRating] = useState(0);
    const [loading, setLoading] = useState(false);
    const [actionType, setActionType] = useState(null);
    const [editableAttachments, setEditableAttachments] = useState([]); // ADD THIS LINE

    const { programmerOptions = [] } = usePage().props;

    const handleAction = (type) => {
        setActionType(type);

        // Handle RESUBMIT separately before other validations
        if (action === "RESUBMIT") {
            if (editableAttachments.length === 0) {
                message.error(
                    "Please attach at least one file before resubmitting"
                );
                return;
            }

            setLoading(true);
            const formData = new FormData();
            formData.append("ticket_id", ticketId);
            formData.append("remarks", remarks);

            editableAttachments.forEach((file) => {
                if (file.originFileObj) {
                    formData.append("attachments[]", file.originFileObj);
                }
            });

            router.post(route("tickets.resubmit", ticketId), formData, {
                onSuccess: () => {
                    message.success("Ticket resubmitted successfully!");
                    setShowModal(false);
                    setEditableAttachments([]);
                    setRemarks("");
                },
                onError: (errors) => {
                    message.error(errors.message || "Failed to resubmit");
                },
                onFinish: () => setLoading(false),
            });
            return;
        }

        // Validation for other actions
        if (
            !remarks.trim() &&
            (type === "disapprove" ||
                ["RETURN", "RESOLVE", "CLOSE"].includes(action))
        ) {
            message.error("Please provide remarks");
            return;
        }

        if (action === "ASSIGN" && selectedProgrammers.length === 0) {
            message.error("Please select at least one programmer");
            return;
        }

        setLoading(true);

        // Build data object only with non-null/non-zero values for optional fields
        const data = {
            remarks,
            action_type: type,
        };

        if (action === "ASSIGN" && selectedProgrammers.length > 0) {
            data.assigned_to = selectedProgrammers;
        }

        if (action === "CLOSE" && rating > 0) {
            data.rating = rating;
        }

        const routeMap = {
            ASSESS: type === "approve" ? "tickets.assess" : "tickets.return",
            RETURN: "tickets.return",
            DH_APPROVE:
                type === "approve"
                    ? "tickets.approve.dh"
                    : "tickets.disapprove.dh",
            OD_APPROVE:
                type === "approve"
                    ? "tickets.approve.od"
                    : "tickets.disapprove.od",
            ASSIGN: "tickets.assign",
            RESOLVE: type === "approve" ? "tickets.resolve" : "tickets.hold",
            CLOSE: type === "approve" ? "tickets.close" : "tickets.reopen",
            ON_HOLD: "tickets.hold",
            TEST: type === "approve" ? "tickets.close" : "tickets.return",
        };

        router.post(route(routeMap[action], ticketId), data, {
            onSuccess: () => {
                message.success(getSuccessMessage(action, type));
                setShowModal(false);
                setRemarks("");
                setSelectedProgrammers([]);
                setRating(0);
                setActionType(null);
                setEditableAttachments([]);
            },
            onError: (errors) => {
                message.error(errors.message || "Action failed");
            },
            onFinish: () => setLoading(false),
        });
    };

    const getSuccessMessage = (actionType, type) => {
        if (type === "approve") {
            const messages = {
                ASSESS: "Ticket assessed successfully",
                DH_APPROVE: "Ticket approved by Department Head",
                OD_APPROVE: "Ticket approved by Operations Director",
                RESOLVE: "Ticket resolved successfully",
                CLOSE: "Ticket closed successfully",
            };
            return messages[actionType] || "Action completed";
        } else {
            const messages = {
                ASSESS: "Ticket returned to requestor",
                DH_APPROVE: "Ticket disapproved by Department Head",
                OD_APPROVE: "Ticket disapproved by Operations Director",
                RESOLVE: "Ticket put on hold",
                CLOSE: "Ticket reopened",
            };
            return messages[actionType] || "Action completed";
        }
    };

    const getButtonConfig = () => {
        const configs = {
            ASSESS: {
                label: "Review Ticket",
                icon: <Check className="w-4 h-4" />,
                approveText: "Assess",
                disapproveText: "Return",
                showBoth: true,
            },
            DH_APPROVE: {
                label: "Department Head Review",
                icon: <CheckCircle className="w-4 h-4" />,
                approveText: "Approve",
                disapproveText: "Disapprove",
                showBoth: true,
            },
            OD_APPROVE: {
                label: "Operations Director Review",
                icon: <CheckCircle className="w-4 h-4" />,
                approveText: "Approve",
                disapproveText: "Disapprove",
                showBoth: true,
            },
            ASSIGN: {
                label: "Assign",
                icon: <UserPlus className="w-4 h-4" />,
                approveText: "Assign",
                showBoth: false,
            },
            RESOLVE: {
                label: "Resolution Action",
                icon: <CheckCircle className="w-4 h-4" />,
                approveText: "Resolve",
                disapproveText: "On Hold",
                showBoth: true,
            },
            CLOSE: {
                label: "Closure Action",
                icon: <XCircle className="w-4 h-4" />,
                approveText: "Close",
                disapproveText: "Reopen",
                showBoth: true,
            },
            ON_HOLD: {
                label: "Put On Hold",
                icon: <Pause className="w-4 h-4" />,
                approveText: "On Hold",
                showBoth: false,
            },
            TEST: {
                label: "Test Action",
                icon: <RefreshCw className="w-4 h-4" />,
                approveText: "Close",
                disapproveText: "Return",
                showBoth: true,
            },
            RESUBMIT: {
                label: "Resubmit Ticket",
                icon: <RefreshCw className="w-4 h-4" />,
                approveText: "Resubmit",
                showBoth: false,
            },
        };

        return configs[action] || null;
    };

    const config = getButtonConfig();
    if (!config) return null;

    const handleResetModal = () => {
        setShowModal(false);
        setRemarks("");
        setSelectedProgrammers([]);
        setRating(0);
        setActionType(null);
        setEditableAttachments([]);
    };

    const renderModalContent = () => {
        switch (action) {
            case "ASSIGN":
                return (
                    <div className="space-y-4">
                        <div>
                            <label className="label">
                                <span className="label-text font-semibold">
                                    Select Programmer(s) *
                                </span>
                            </label>
                            <select
                                multiple
                                className="select select-bordered w-full h-32"
                                value={selectedProgrammers}
                                onChange={(e) => {
                                    const selected = Array.from(
                                        e.target.selectedOptions,
                                        (option) => option.value
                                    );
                                    setSelectedProgrammers(selected);
                                }}
                            >
                                {programmerOptions.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                            <label className="label">
                                <span className="label-text-alt text-base-content/60">
                                    Hold Ctrl/Cmd to select multiple
                                </span>
                            </label>
                        </div>
                        <div>
                            <label className="label">
                                <span className="label-text font-semibold">
                                    Remarks (Optional)
                                </span>
                            </label>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                placeholder="Additional notes..."
                                value={remarks}
                                onChange={(e) => setRemarks(e.target.value)}
                                rows={4}
                            />
                        </div>
                    </div>
                );
            case "RESUBMIT":
                return (
                    <div className="space-y-4">
                        <p className="text-sm font-semibold mb-3">
                            Edit Attachments
                        </p>
                        <AttachmentUpload
                            viewOnly={false}
                            onFilesChange={(files) => {
                                setEditableAttachments(
                                    files.map((f) => ({
                                        originFileObj: f,
                                        name: f.name,
                                        size: f.size,
                                        type: f.type,
                                        uid: `${f.name}-${
                                            f.size
                                        }-${Date.now()}`,
                                    }))
                                );
                            }}
                        />
                        <div>
                            <label className="label">
                                <span className="label-text font-semibold">
                                    Remarks (Optional)
                                </span>
                            </label>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                placeholder="Add remarks for resubmission..."
                                value={remarks}
                                onChange={(e) => setRemarks(e.target.value)}
                                rows={4}
                            />
                        </div>
                    </div>
                );
            case "CLOSE":
                return (
                    <div className="space-y-4">
                        <div>
                            <label className="label">
                                <span className="label-text font-semibold">
                                    Rate your experience (Optional)
                                </span>
                            </label>
                            <div className="rating rating-lg">
                                {[1, 2, 3, 4, 5].map((star) => (
                                    <input
                                        key={star}
                                        type="radio"
                                        name="rating"
                                        className="mask mask-star-2 bg-orange-400"
                                        checked={rating === star}
                                        onChange={() => setRating(star)}
                                    />
                                ))}
                            </div>
                        </div>
                        <div>
                            <label className="label">
                                <span className="label-text font-semibold">
                                    Closure Remarks *
                                </span>
                            </label>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                placeholder="Please confirm the issue is resolved..."
                                value={remarks}
                                onChange={(e) => setRemarks(e.target.value)}
                                rows={4}
                            />
                        </div>
                    </div>
                );
            case "RESOLVE":
                return (
                    <div className="space-y-4">
                        <div>
                            <label className="label">
                                <span className="label-text font-semibold">
                                    Resolution Details *
                                </span>
                            </label>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                placeholder="Describe what was done to resolve the ticket..."
                                value={remarks}
                                onChange={(e) => setRemarks(e.target.value)}
                                rows={6}
                            />
                        </div>
                    </div>
                );
            case "ASSESS":
                return (
                    <div className="space-y-4">
                        <div className="alert alert-info">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                className="stroke-current shrink-0 w-6 h-6"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                ></path>
                            </svg>
                            <span className="text-sm">
                                Choose to assess and proceed, or return to
                                requestor for clarification.
                            </span>
                        </div>
                        <div>
                            <label className="label">
                                <span className="label-text font-semibold">
                                    Notes (Optional)
                                </span>
                            </label>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                placeholder="Add any notes..."
                                value={remarks}
                                onChange={(e) => setRemarks(e.target.value)}
                                rows={3}
                            />
                        </div>
                    </div>
                );
            case "DH_APPROVE":
            case "OD_APPROVE":
                return (
                    <div className="space-y-4">
                        <div className="alert alert-info">
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                className="stroke-current shrink-0 w-6 h-6"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                ></path>
                            </svg>
                            <span className="text-sm">
                                You are reviewing this ticket as{" "}
                                {action === "DH_APPROVE"
                                    ? "Department Head"
                                    : "Operations Director"}
                                .
                            </span>
                        </div>
                        <div>
                            <label className="label">
                                <span className="label-text font-semibold">
                                    Review Notes (Optional)
                                </span>
                            </label>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                placeholder="Add review notes..."
                                value={remarks}
                                onChange={(e) => setRemarks(e.target.value)}
                                rows={3}
                            />
                        </div>
                    </div>
                );
            case "TEST":
                return (
                    <div className="space-y-4">
                        <div>
                            <label className="label">
                                <span className="label-text font-semibold">
                                    Remarks (Optional)
                                </span>
                            </label>
                            <textarea
                                className="textarea textarea-bordered w-full"
                                placeholder="Add any remarks about your test result..."
                                value={remarks}
                                onChange={(e) => setRemarks(e.target.value)}
                                rows={4}
                            />
                        </div>
                    </div>
                );
            default:
                return null;
        }
    };

    return (
        <>
            <button
                onClick={() => setShowModal(true)}
                className="btn btn-primary btn-sm gap-2"
            >
                {config.icon}
                {config.label}
            </button>

            <Modal
                title={
                    <div className="flex items-center gap-3">
                        <div className="flex items-center justify-center w-10 h-10 rounded-full bg-primary/10 text-primary">
                            {config.icon}
                        </div>
                        <span className="text-lg font-bold">
                            {config.label}
                        </span>
                    </div>
                }
                open={showModal}
                onCancel={handleResetModal}
                footer={
                    <div className="flex justify-end gap-2">
                        <button
                            onClick={handleResetModal}
                            className="btn btn-ghost"
                            disabled={loading}
                        >
                            Cancel
                        </button>
                        {action === "RESUBMIT" ? (
                            <button
                                onClick={() => handleAction("approve")}
                                className="btn btn-success gap-2"
                                disabled={loading}
                            >
                                <Check className="w-4 h-4" /> Resubmit
                            </button>
                        ) : config.showBoth ? (
                            <>
                                <button
                                    onClick={() => handleAction("disapprove")}
                                    className="btn btn-error gap-2"
                                    disabled={loading}
                                >
                                    <X className="w-4 h-4" />
                                    {config.disapproveText}
                                </button>
                                <button
                                    onClick={() => handleAction("approve")}
                                    className="btn btn-success gap-2"
                                    disabled={loading}
                                >
                                    <Check className="w-4 h-4" />
                                    {config.approveText}
                                </button>
                            </>
                        ) : (
                            <button
                                onClick={() => handleAction("approve")}
                                className="btn btn-primary gap-2"
                                disabled={loading}
                            >
                                <Check className="w-4 h-4" />
                                {config.approveText}
                            </button>
                        )}
                    </div>
                }
                width={600}
            >
                <div className="py-4">{renderModalContent()}</div>
            </Modal>
        </>
    );
};

export default ActionButton;

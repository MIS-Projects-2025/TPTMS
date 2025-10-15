import { useState } from "react";
import { Modal } from "antd";
import {
    ChevronDown,
    ChevronUp,
    Maximize2,
    Clock,
    User,
    MessageSquare,
    History,
    Lock,
    Activity,
} from "lucide-react";

export default function ActivityTimeline({
    ticketHistory = [],
    remarksHistory = [],
    statusTypes = {},
}) {
    const [expanded, setExpanded] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);

    const toggleExpanded = () => setExpanded((prev) => !prev);

    // Combine and sort all timeline items
    const allItems = [
        ...ticketHistory.map((item) => ({
            type: "workflow",
            timestamp: new Date(item.ACTION_AT),
            data: item,
        })),
        ...remarksHistory.map((item) => ({
            type: "remark",
            timestamp: new Date(item.CREATED_AT),
            data: item,
        })),
    ].sort((a, b) => b.timestamp - a.timestamp);

    const renderTimelineItems = () => {
        if (allItems.length === 0) {
            return (
                <div className="flex flex-col items-center justify-center py-12 text-base-content/50">
                    <Activity className="w-12 h-12 mb-3 opacity-30" />
                    <p className="text-sm">No activity yet</p>
                </div>
            );
        }

        return (
            <div className="space-y-4">
                {allItems.map((item, idx) => {
                    if (item.type === "workflow") {
                        const history = item.data;
                        return (
                            <div key={idx} className="flex gap-4">
                                <div className="flex flex-col items-center">
                                    <div className="flex items-center justify-center w-10 h-10 rounded-full bg-blue-500/10 text-blue-500 border-2 border-blue-500/20">
                                        <History className="w-5 h-5" />
                                    </div>
                                    {idx < allItems.length - 1 && (
                                        <div className="w-0.5 h-full min-h-[40px] bg-gradient-to-b from-blue-500/20 to-transparent mt-2" />
                                    )}
                                </div>
                                <div className="flex-1 pb-6">
                                    <div className="card bg-base-100 shadow-sm border border-base-300 hover:shadow-md transition-shadow">
                                        <div className="card-body p-4">
                                            <div className="flex flex-wrap items-center gap-2 mb-2">
                                                <span className="badge badge-info badge-sm">
                                                    {history.ACTION_TYPE.replace(
                                                        /_/g,
                                                        " "
                                                    )}
                                                </span>
                                                <div className="flex items-center gap-1 text-xs text-base-content/60 ml-auto">
                                                    <Clock className="w-3 h-3" />
                                                    {item.timestamp.toLocaleString()}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2 text-sm">
                                                <User className="w-4 h-4 text-base-content/60" />
                                                <span className="font-semibold">
                                                    {history.action_by_name}
                                                </span>
                                            </div>
                                            {history.REMARKS && (
                                                <div className="mt-3 p-3 bg-base-200 rounded-lg text-sm italic text-base-content/80">
                                                    "{history.REMARKS}"
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    } else {
                        const remark = item.data;
                        return (
                            <div key={idx} className="flex gap-4">
                                <div className="flex flex-col items-center">
                                    <div
                                        className={`flex items-center justify-center w-10 h-10 rounded-full ${
                                            remark.IS_INTERNAL
                                                ? "bg-warning/10 text-warning border-2 border-warning/20"
                                                : "bg-success/10 text-success border-2 border-success/20"
                                        }`}
                                    >
                                        {remark.IS_INTERNAL ? (
                                            <Lock className="w-5 h-5" />
                                        ) : (
                                            <MessageSquare className="w-5 h-5" />
                                        )}
                                    </div>
                                    {idx < allItems.length - 1 && (
                                        <div
                                            className={`w-0.5 h-full min-h-[40px] bg-gradient-to-b ${
                                                remark.IS_INTERNAL
                                                    ? "from-warning/20"
                                                    : "from-success/20"
                                            } to-transparent mt-2`}
                                        />
                                    )}
                                </div>
                                <div className="flex-1 pb-6">
                                    <div
                                        className={`card bg-base-100 shadow-sm hover:shadow-md transition-shadow ${
                                            remark.IS_INTERNAL
                                                ? "border-l-4 border-l-warning border-t border-r border-b border-base-300"
                                                : "border border-base-300"
                                        }`}
                                    >
                                        <div className="card-body p-4">
                                            <div className="flex flex-wrap items-center gap-2 mb-2">
                                                <span className="font-semibold text-sm">
                                                    {remark.created_by_name}
                                                </span>
                                                <span className="badge badge-ghost badge-sm">
                                                    {remark.REMARK_TYPE}
                                                </span>
                                                {remark.IS_INTERNAL && (
                                                    <span className="badge badge-warning badge-sm gap-1">
                                                        <Lock className="w-3 h-3" />
                                                        Internal
                                                    </span>
                                                )}
                                                <div className="flex items-center gap-1 text-xs text-base-content/60 ml-auto">
                                                    <Clock className="w-3 h-3" />
                                                    {item.timestamp.toLocaleString()}
                                                </div>
                                            </div>
                                            <p className="text-sm text-base-content/90 leading-relaxed">
                                                {remark.REMARK_TEXT}
                                            </p>
                                            {(remark.OLD_STATUS ||
                                                remark.NEW_STATUS) && (
                                                <div className="mt-3 p-2 bg-base-200 rounded-lg flex items-center gap-2 text-xs">
                                                    <span className="text-base-content/60">
                                                        Status:
                                                    </span>
                                                    <span className="badge badge-sm badge-ghost">
                                                        {
                                                            statusTypes[
                                                                remark
                                                                    .OLD_STATUS
                                                            ]
                                                        }
                                                    </span>
                                                    <span className="text-base-content/60">
                                                        →
                                                    </span>
                                                    <span className="badge badge-sm badge-info">
                                                        {
                                                            statusTypes[
                                                                remark
                                                                    .NEW_STATUS
                                                            ]
                                                        }
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    }
                })}
            </div>
        );
    };

    const timelineContent = (
        <div className="max-h-[600px] overflow-y-auto pr-2">
            {renderTimelineItems()}
        </div>
    );

    return (
        <div className="mt-6 pt-6 border-t border-base-300">
            <div className="flex justify-between items-center mb-4">
                <div className="flex items-center gap-3">
                    <h3 className="text-lg font-bold flex items-center gap-2">
                        <Activity className="w-5 h-5" />
                        Activity Timeline
                    </h3>
                    <div className="badge badge-primary badge-sm">
                        {allItems.length}
                    </div>
                </div>
                <div className="flex gap-2">
                    <button
                        onClick={toggleExpanded}
                        className="btn btn-ghost btn-sm gap-1"
                    >
                        {expanded ? (
                            <>
                                <ChevronUp className="w-4 h-4" />
                                Collapse
                            </>
                        ) : (
                            <>
                                <ChevronDown className="w-4 h-4" />
                                Expand
                            </>
                        )}
                    </button>
                    <button
                        onClick={() => setModalOpen(true)}
                        className="btn btn-outline btn-sm gap-1"
                    >
                        <Maximize2 className="w-4 h-4" />
                        Full View
                    </button>
                </div>
            </div>

            {expanded && (
                <div className="animate-[slideDown_0.3s_ease-out] max-h-96 overflow-y-auto">
                    {timelineContent}
                </div>
            )}

            <Modal
                title={
                    <div className="flex items-center gap-3">
                        <div className="flex items-center justify-center w-10 h-10 rounded-full bg-primary/10 text-primary">
                            <History className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="text-lg font-bold m-0">
                                Activity Timeline
                            </h3>
                            <p className="text-xs text-base-content/60 m-0">
                                {allItems.length} total activities
                            </p>
                        </div>
                    </div>
                }
                open={modalOpen}
                onCancel={() => setModalOpen(false)}
                footer={
                    <button
                        onClick={() => setModalOpen(false)}
                        className="btn btn-primary"
                    >
                        Close
                    </button>
                }
                width={900}
                style={{ top: 20 }}
            >
                {timelineContent}
            </Modal>

            <style>{`
                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `}</style>
        </div>
    );
}

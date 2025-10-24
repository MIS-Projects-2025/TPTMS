import React from "react";
import { Modal, Spin, Empty, Timeline, Tag, Pagination } from "antd";
import { Clock } from "lucide-react";

export default function ProjectLogsModal({
    isOpen,
    onClose,
    logs,
    loading,
    pagination,
    onPageChange,
    projectName,
}) {
    const groupLogsByDate = (logs) => {
        return logs.reduce((groups, log) => {
            const logDate = new Date(log.UPDATE_AT);
            const today = new Date();
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);

            const isToday = logDate.toDateString() === today.toDateString();
            const isYesterday =
                logDate.toDateString() === yesterday.toDateString();

            let groupLabel;
            if (isToday) groupLabel = "Today";
            else if (isYesterday) groupLabel = "Yesterday";
            else {
                const diffDays = Math.floor(
                    (today - logDate) / (1000 * 60 * 60 * 24)
                );
                groupLabel = diffDays <= 7 ? "This Week" : "Older";
            }

            if (!groups[groupLabel]) groups[groupLabel] = [];
            groups[groupLabel].push(log);
            return groups;
        }, {});
    };

    const groupedLogs = groupLogsByDate(logs);

    return (
        <Modal
            title={`Project Logs - ${projectName || ""}`}
            open={isOpen}
            onCancel={onClose}
            footer={null}
            width={700}
        >
            <Spin spinning={loading}>
                {logs.length > 0 ? (
                    <div className="max-h-[60vh] overflow-y-auto pr-2">
                        {Object.entries(groupedLogs).map(([label, group]) => (
                            <div key={label} className="mb-6">
                                <h3 className="font-semibold text-gray-700 mb-3">
                                    {label}
                                </h3>
                                <Timeline
                                    items={group
                                        .sort(
                                            (a, b) =>
                                                new Date(b.UPDATE_AT) -
                                                new Date(a.UPDATE_AT)
                                        ) // sort oldest → newest
                                        .map((log) => ({
                                            color:
                                                log.PROJ_STATUS === "Deployed"
                                                    ? "green"
                                                    : log.PROJ_STATUS ===
                                                      "In Progress"
                                                    ? "blue"
                                                    : log.PROJ_STATUS ===
                                                      "Ready"
                                                    ? "orange"
                                                    : "gray",
                                            children: (
                                                <div>
                                                    <div className="flex justify-between items-center">
                                                        <h4 className="font-semibold text-base">
                                                            {log.ACTION_TYPE}
                                                        </h4>
                                                        <span className="text-xs text-gray-500 flex items-center gap-1">
                                                            <Clock className="w-3 h-3" />
                                                            {new Date(
                                                                log.UPDATE_AT
                                                            ).toLocaleString()}
                                                        </span>
                                                    </div>
                                                    <p className="text-sm text-gray-600 mt-1">
                                                        {log.DESCRIPTION ||
                                                            "No description provided"}
                                                    </p>
                                                    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                                        <Tag color="blue">
                                                            v
                                                            {
                                                                log.PROJECT_VERSION
                                                            }
                                                        </Tag>
                                                        <Tag color="purple">
                                                            {log.PROJ_STATUS}
                                                        </Tag>
                                                        <Tag color="geekblue">
                                                            {log.ACTION_BY}
                                                        </Tag>
                                                        <Tag color="magenta">
                                                            {log.REQUEST_TYPE}
                                                        </Tag>{" "}
                                                        {/* added */}
                                                        <Tag color="gold">
                                                            {log.TICKET_ID}
                                                        </Tag>{" "}
                                                        {/* added */}
                                                    </div>
                                                </div>
                                            ),
                                        }))}
                                />
                            </div>
                        ))}
                    </div>
                ) : (
                    <Empty description="No logs available for this project" />
                )}

                {pagination.total > 10 && (
                    <div className="flex justify-center mt-4">
                        <Pagination
                            current={pagination.current}
                            total={pagination.total}
                            pageSize={10}
                            onChange={onPageChange}
                        />
                    </div>
                )}
            </Spin>
        </Modal>
    );
}

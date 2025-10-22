import { Bell, Check, ExternalLink } from "lucide-react";
import { useState } from "react";
import { useNotifications } from "@/Context/NotificationContext";

export default function NotificationBell() {
    const [isOpen, setIsOpen] = useState(false);
    const { notifications, unreadCount, markAsRead, markAllAsRead } =
        useNotifications();

    const formatDate = (dateString) => {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return "Just now";
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString();
    };

    // Handle notification click - redirect to ticket and mark as read
    const handleNotificationClick = async (notif) => {
        // Mark as read if unread
        if (!notif.read_at) {
            await markAsRead(notif.id);
        }

        // Close the dropdown
        setIsOpen(false);

        // Redirect to ticket view
        if (notif.ticket_id) {
            const hash = btoa(`${notif.ticket_id}:VIEW`);
            window.location.href = route("tickets.view", hash);
        }
    };

    // Get notification badge color based on type
    const getNotificationStyle = (type) => {
        const styles = {
            TICKET_CREATED: "bg-blue-500/10 border-l-blue-500",
            TICKET_UPDATED: "bg-yellow-500/10 border-l-yellow-500",
            TICKET_ASSIGNED: "bg-purple-500/10 border-l-purple-500",
            TICKET_STATUS_CHANGED: "bg-orange-500/10 border-l-orange-500",
            TICKET_RESOLVED: "bg-green-500/10 border-l-green-500",
            TICKET_CLOSED: "bg-gray-500/10 border-l-gray-500",
        };
        return styles[type] || "bg-info/10 border-l-info";
    };

    // Get notification icon/emoji based on type
    const getNotificationIcon = (type) => {
        const icons = {
            TICKET_CREATED: "🎫",
            TICKET_UPDATED: "✏️",
            TICKET_ASSIGNED: "👤",
            TICKET_STATUS_CHANGED: "🔄",
            TICKET_RESOLVED: "✅",
            TICKET_CLOSED: "🔒",
        };
        return icons[type] || "📢";
    };

    return (
        <div className="dropdown dropdown-end">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="btn btn-ghost btn-circle indicator hover:bg-base-200 transition-colors"
            >
                {unreadCount > 0 && (
                    <span className="indicator-item badge badge-xs badge-error animate-bounce">
                        {unreadCount > 9 ? "9+" : unreadCount}
                    </span>
                )}
                <Bell size={24} />
            </button>

            {isOpen && (
                <div className="dropdown-content card card-compact w-96 shadow-xl bg-base-100 border border-base-300 p-0 rounded-lg z-50">
                    <div className="p-4 border-b border-base-300 flex justify-between items-center bg-base-200 rounded-t-lg">
                        <h3 className="font-bold text-lg flex items-center gap-2">
                            <Bell size={20} />
                            Notifications
                        </h3>
                        {unreadCount > 0 && (
                            <button
                                onClick={() => {
                                    markAllAsRead();
                                }}
                                className="text-xs btn btn-link btn-xs text-primary no-underline hover:underline"
                            >
                                Mark all as read
                            </button>
                        )}
                    </div>

                    <div className="overflow-y-auto flex-1 max-h-96">
                        {notifications.length === 0 ? (
                            <div className="p-8 text-center text-base-content/50">
                                <Bell
                                    size={48}
                                    className="mx-auto mb-3 opacity-30"
                                />
                                <p className="text-sm font-medium">
                                    No notifications yet
                                </p>
                                <p className="text-xs mt-1">
                                    You'll see updates here
                                </p>
                            </div>
                        ) : (
                            notifications.map((notif, index) => {
                                // Parse data if it's a string
                                const notifData =
                                    typeof notif.data === "string"
                                        ? JSON.parse(notif.data)
                                        : notif.data || {};

                                const ticketId =
                                    notifData.ticket_id || notif.ticket_id;
                                const message =
                                    notifData.message || notif.message;
                                const type = notifData.type || notif.type;
                                const project =
                                    notifData.project_name || notif.project;
                                const requestType = notifData.request_type;

                                return (
                                    <div
                                        key={notif.id}
                                        onClick={() =>
                                            handleNotificationClick({
                                                ...notif,
                                                ticket_id: ticketId,
                                            })
                                        }
                                        className={`p-4 border-b border-base-300 hover:bg-base-200 transition-all cursor-pointer group relative ${
                                            !notif.read_at
                                                ? `${getNotificationStyle(
                                                      type
                                                  )} border-l-4`
                                                : "hover:border-l-4 hover:border-l-base-300"
                                        } ${
                                            index === notifications.length - 1
                                                ? "border-b-0"
                                                : ""
                                        }`}
                                    >
                                        <div className="flex justify-between items-start gap-3">
                                            <div className="flex-1">
                                                {/* Ticket ID with icon */}
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="text-lg">
                                                        {getNotificationIcon(
                                                            type
                                                        )}
                                                    </span>
                                                    <p className="font-bold text-sm text-primary">
                                                        {ticketId}
                                                    </p>
                                                    {!notif.read_at && (
                                                        <span className="badge badge-xs badge-primary">
                                                            NEW
                                                        </span>
                                                    )}
                                                </div>

                                                {/* Message */}
                                                <p className="text-sm text-base-content/80 mt-1 line-clamp-2">
                                                    {message}
                                                </p>

                                                {/* Project and Request Type */}
                                                <div className="flex flex-wrap gap-2 mt-2">
                                                    {project && (
                                                        <span className="badge badge-sm badge-outline">
                                                            📁 {project}
                                                        </span>
                                                    )}
                                                    {requestType && (
                                                        <span className="badge badge-sm badge-ghost">
                                                            {requestType}
                                                        </span>
                                                    )}
                                                </div>

                                                {/* Timestamp */}
                                                <p className="text-xs text-base-content/50 mt-2 flex items-center gap-1">
                                                    🕐{" "}
                                                    {formatDate(
                                                        notif.created_at
                                                    )}
                                                </p>
                                            </div>

                                            {/* Action buttons */}
                                            <div className="flex flex-col gap-1">
                                                {/* View ticket button */}
                                                <button
                                                    className="btn btn-ghost btn-xs btn-circle hover:bg-primary hover:text-primary-content transition-all tooltip tooltip-left"
                                                    data-tip="View ticket"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        handleNotificationClick(
                                                            {
                                                                ...notif,
                                                                ticket_id:
                                                                    ticketId,
                                                            }
                                                        );
                                                    }}
                                                >
                                                    <ExternalLink size={14} />
                                                </button>

                                                {/* Mark as read button */}
                                                {!notif.read_at && (
                                                    <button
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            markAsRead(
                                                                notif.id
                                                            );
                                                        }}
                                                        className="btn btn-ghost btn-xs btn-circle hover:bg-success hover:text-success-content transition-all tooltip tooltip-left"
                                                        data-tip="Mark as read"
                                                    >
                                                        <Check size={14} />
                                                    </button>
                                                )}
                                            </div>
                                        </div>

                                        {/* Hover effect indicator */}
                                        <div className="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></div>
                                    </div>
                                );
                            })
                        )}
                    </div>

                    {/* Footer with action */}
                    {notifications.length > 0 && (
                        <div className="p-3 border-t border-base-300 bg-base-200 rounded-b-lg text-center">
                            <button
                                onClick={() => setIsOpen(false)}
                                className="text-xs text-base-content/60 hover:text-primary transition-colors"
                            >
                                Close notifications
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

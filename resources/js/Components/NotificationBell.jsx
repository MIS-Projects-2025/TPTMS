import { Bell, Check } from "lucide-react";
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

    return (
        <div className="dropdown dropdown-end">
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="btn btn-ghost btn-circle indicator hover:bg-base-200 transition-colors"
            >
                {unreadCount > 0 && (
                    <span className="indicator-item badge badge-sm badge-error">
                        {unreadCount > 9 ? "9+" : unreadCount}
                    </span>
                )}
                <Bell size={24} />
            </button>

            {isOpen && (
                <div className="dropdown-content card card-compact w-96 shadow-xl bg-base-100 border border-base-300 p-0 rounded-lg z-50">
                    <div className="p-4 border-b border-base-300 flex justify-between items-center bg-base-200 rounded-t-lg">
                        <h3 className="font-bold text-lg">Notifications</h3>
                        {unreadCount > 0 && (
                            <button
                                onClick={() => {
                                    markAllAsRead();
                                    setIsOpen(false);
                                }}
                                className="text-xs btn btn-link btn-xs text-primary no-underline hover:underline"
                            >
                                Mark all as read
                            </button>
                        )}
                    </div>

                    <div className="overflow-y-auto flex-1 max-h-80">
                        {notifications.length === 0 ? (
                            <div className="p-8 text-center text-base-content/50">
                                <Bell
                                    size={32}
                                    className="mx-auto mb-2 opacity-30"
                                />
                                <p className="text-sm">No notifications yet</p>
                            </div>
                        ) : (
                            notifications.map((notif, index) => (
                                <div
                                    key={notif.id}
                                    className={`p-4 border-b border-base-300 hover:bg-base-200 transition-colors cursor-pointer group ${
                                        !notif.read_at
                                            ? "bg-info/20 border-l-4 border-l-info"
                                            : ""
                                    } ${
                                        index === notifications.length - 1
                                            ? "border-b-0"
                                            : ""
                                    }`}
                                >
                                    <div className="flex justify-between items-start gap-3">
                                        <div className="flex-1">
                                            <p className="font-semibold text-sm">
                                                {notif.ticket_id}
                                            </p>
                                            <p className="text-sm text-base-content/70 mt-1 line-clamp-2">
                                                {notif.message}
                                            </p>
                                            {notif.project && (
                                                <p className="text-xs text-base-content/60 mt-2">
                                                    <span className="font-semibold">
                                                        Project:
                                                    </span>{" "}
                                                    {notif.project}
                                                </p>
                                            )}
                                            <p className="text-xs text-base-content/50 mt-2">
                                                {formatDate(notif.created_at)}
                                            </p>
                                        </div>
                                        {!notif.read_at && (
                                            <button
                                                onClick={() =>
                                                    markAsRead(notif.id)
                                                }
                                                className="btn btn-ghost btn-xs btn-circle hover:bg-primary hover:text-primary-content opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0"
                                                title="Mark as read"
                                            >
                                                <Check size={16} />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

import {
    createContext,
    useContext,
    useEffect,
    useState,
    useCallback,
} from "react";

const NotificationContext = createContext();

export function NotificationProvider({ children, userId }) {
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [echoConnected, setEchoConnected] = useState(false);

    /** Merge notifications and remove duplicates */
    const mergeNotifications = (prev, newNotifs) => {
        const combined = [...newNotifs, ...prev];
        const unique = combined.reduce((acc, notif) => {
            if (!acc.find((n) => n.id === notif.id)) acc.push(notif);
            return acc;
        }, []);
        return unique;
    };

    /** Fetch notifications from API (polling fallback) */
    const fetchNotifications = useCallback(async () => {
        try {
            const response = await fetch("/api/notifications");
            const data = await response.json();

            setNotifications((prev) => {
                const merged = mergeNotifications(prev, data);
                setUnreadCount(merged.filter((n) => !n.read_at).length);
                return merged;
            });
        } catch (error) {
            console.error("Error fetching notifications:", error);
        }
    }, []);

    /** Add a new realtime notification */
    const addNotification = useCallback((data) => {
        setNotifications((prev) => {
            if (prev.some((n) => n.id === data.id)) return prev; // avoid duplicates
            const updated = [data, ...prev];
            setUnreadCount(updated.filter((n) => !n.read_at).length);
            return updated;
        });

        if (Notification.permission === "granted") {
            new Notification(`Ticket ${data.ticket_id}`, {
                body: data.message,
                tag: "ticket-notification",
            });
        }
    }, []);

    /** Realtime listener */
    useEffect(() => {
        if (!userId || !window.Echo) return;

        const channelName = `App.Models.User.${userId}`;
        const channel = window.Echo.channel(channelName);

        // Listen for Laravel Notification events
        channel.listen(
            "App\\Notifications\\TicketCreatedNotification",
            (data) => {
                addNotification({
                    id: data.id || Date.now(),
                    ticket_id: data.ticket_id,
                    message: data.message,
                    type: data.type,
                    project_name: data.project_name,
                    created_at: data.timestamp || new Date().toISOString(),
                    read_at: null,
                });
            }
        );

        window.Echo.connector.pusher.connection.bind("connected", () => {
            console.log("✅ Echo connected, disabling polling");
            setEchoConnected(true);
        });

        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }

        return () => window.Echo.leave(channelName);
    }, [userId, addNotification]);

    /** Polling fallback every 30s, only if realtime not connected */
    useEffect(() => {
        if (echoConnected) return; // stop polling if realtime works
        fetchNotifications();
        const interval = setInterval(fetchNotifications, 30000);
        return () => clearInterval(interval);
    }, [fetchNotifications, echoConnected]);

    /** Mark one notification as read */
    const markAsRead = useCallback(async (notificationId) => {
        try {
            await fetch(`/api/notifications/${notificationId}/read`, {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    ).content,
                },
                credentials: "include", // <<< very important
            });
            setNotifications((prev) => {
                const updated = prev.map((n) =>
                    n.id === notificationId ? { ...n, read_at: new Date() } : n
                );
                setUnreadCount(updated.filter((n) => !n.read_at).length);
                return updated;
            });
        } catch (error) {
            console.error("Failed to mark as read:", error);
        }
    }, []);

    /** Mark all notifications as read */
    const markAllAsRead = useCallback(async () => {
        try {
            await fetch("/api/notifications/read-all", {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                },
            });
            setNotifications((prev) =>
                prev.map((n) => ({ ...n, read_at: new Date() }))
            );
            setUnreadCount(0);
        } catch (error) {
            console.error("Failed to mark all as read:", error);
        }
    }, []);

    return (
        <NotificationContext.Provider
            value={{
                notifications,
                unreadCount,
                markAsRead,
                markAllAsRead,
                fetchNotifications,
            }}
        >
            {children}
        </NotificationContext.Provider>
    );
}

export function useNotifications() {
    const context = useContext(NotificationContext);
    if (!context)
        throw new Error(
            "useNotifications must be used within NotificationProvider"
        );
    return context;
}

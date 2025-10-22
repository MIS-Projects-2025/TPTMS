import {
    createContext,
    useContext,
    useEffect,
    useState,
    useCallback,
    useRef,
} from "react";

const NotificationContext = createContext();

export function NotificationProvider({ children, userId }) {
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isConnected, setIsConnected] = useState(false);
    const loadingRef = useRef(false);
    const channelRef = useRef(null);

    /** Fetch notifications from API (initial load only) */
    const fetchNotifications = useCallback(async () => {
        if (loadingRef.current) return;

        loadingRef.current = true;
        try {
            const response = await fetch("/api/notifications");
            const data = await response.json();

            setNotifications(data);
            setUnreadCount(data.filter((n) => !n.read_at).length);
        } catch (error) {
            console.error("Error fetching notifications:", error);
        } finally {
            loadingRef.current = false;
        }
    }, []);

    /** Setup WebSocket connection */
    useEffect(() => {
        if (!userId) {
            console.warn("No userId provided for notifications");
            return;
        }

        // Fetch initial notifications
        fetchNotifications();

        // Subscribe to user's private channel
        const channel = echo.private(`users.${userId}`);
        console.log(`Joining channel: users.${userId}`);

        channelRef.current = channel;

        // Listen for notification events - FIXED: Use correct event name
        channel.listen(".notification.created", (notification) => {
            console.log("Real-time notification received:", notification);

            // Add new notification to the list
            setNotifications((prev) => {
                const newNotif = {
                    id: notification.id || Date.now(),
                    ticket_id: notification.ticket_id,
                    message: notification.message,
                    type: notification.type,
                    project: notification.project_name,
                    created_at:
                        notification.timestamp || new Date().toISOString(),
                    read_at: null,
                };

                const updated = [newNotif, ...prev];
                setUnreadCount(updated.filter((n) => !n.read_at).length);
                return updated;
            });
        });

        // Connection event handlers
        channel
            .subscribed(() => {
                console.log(`✅ Subscribed to users.${userId}`);
                setIsConnected(true);
            })
            .error((error) => {
                console.error("❌ Channel subscription error:", error);
                setIsConnected(false);
            });

        // Cleanup on unmount - FIXED: Use channelRef
        return () => {
            console.log(`Leaving channel: users.${userId}`);
            if (channelRef.current) {
                channelRef.current.stopListening(".notification.created");
                echo.leave(`users.${userId}`);
            }
            channelRef.current = null;
            setIsConnected(false);
        };
    }, [userId, fetchNotifications]);

    /** Mark one notification as read */
    const markAsRead = useCallback(async (notificationId) => {
        try {
            await fetch(`/api/notifications/${notificationId}/read`, {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                },
                credentials: "include",
            });

            setNotifications((prev) =>
                prev.map((n) =>
                    n.id === notificationId
                        ? { ...n, read_at: new Date().toISOString() }
                        : n
                )
            );

            setUnreadCount((prevCount) => Math.max(0, prevCount - 1));
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
                prev.map((n) => ({ ...n, read_at: new Date().toISOString() }))
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
                isConnected,
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

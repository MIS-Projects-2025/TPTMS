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

    if (typeof window.echo === "undefined") {
        console.error("❌ Laravel echo is not initialized!");
        return;
    }

    console.log("🔄 Initializing notifications for user:", userId);

    // Load notifications once on init
    fetchNotifications();

    try {
        // Subscribe to private user channel
        const channel = echo.private(`users.${userId}`);
        console.log(`🎯 Joining channel: users.${userId}`);

        channelRef.current = channel;

        // Listen ONLY to the proper Laravel Echo event
        channel.listen(".notification.created", (notification) => {
            console.log("📨 Real-time notification received:", notification);
            handleNewNotification(notification);
        });

        // Subscribe connection status
        channel
            .subscribed(() => {
                console.log(`✅ Successfully subscribed to users.${userId}`);
                setIsConnected(true);
            })
            .error((error) => {
                console.error("❌ Channel subscription error:", error);
                setIsConnected(false);
            });

    } catch (error) {
        console.error("❌ Error setting up echo channel:", error);
    }

    // Cleanup
    return () => {
        console.log(`👋 Cleaning up notifications for user: ${userId}`);

        if (channelRef.current) {
            channelRef.current.stopListening(".notification.created");
            window.echo.leave(`users.${userId}`);
        }

        channelRef.current = null;
        setIsConnected(false);
    };
}, [userId, fetchNotifications]);


/** Handle new notification */
const handleNewNotification = (notification) => {
    setNotifications((prev) => {
        const newNotif = {
            id: notification.id || `notif_${Date.now()}`,
            ticket_id: notification.ticket_id,
            message: notification.message,
            type: notification.type,
            project: notification.project_name,
            request_type: notification.request_type,
            action_required: notification.action_required,
            created_at: notification.timestamp || new Date().toISOString(),
            read_at: null,
        };

        console.log("➕ Adding new notification:", newNotif);
        const updated = [newNotif, ...prev];
        setUnreadCount(updated.filter((n) => !n.read_at).length);
        return updated;
    });
};

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

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
    const [loading, setLoading] = useState(false);
    const lastFetchRef = useRef(null);

    /** Merge notifications and remove duplicates by ID */
    const mergeNotifications = (prev, newNotifs) => {
        const combined = [...newNotifs, ...prev];
        const uniqueMap = new Map();
        combined.forEach((notif) => {
            uniqueMap.set(notif.id, notif);
        });
        return Array.from(uniqueMap.values());
    };

    /** Fetch notifications from API */
    const fetchNotifications = useCallback(async () => {
        if (loading) return;

        try {
            setLoading(true);
            const response = await fetch("/api/notifications");
            const data = await response.json();

            setNotifications((prev) => {
                const merged = mergeNotifications(prev, data);
                setUnreadCount(merged.filter((n) => !n.read_at).length);
                return merged;
            });

            lastFetchRef.current = Date.now();
        } catch (error) {
            console.error("Error fetching notifications:", error);
        } finally {
            setLoading(false);
        }
    }, [loading]);

    /** Fetch on component mount */
    useEffect(() => {
        fetchNotifications();
    }, [fetchNotifications]);

    /** Poll every 30 seconds */
    useEffect(() => {
        const interval = setInterval(fetchNotifications, 30000); // 30 sec
        return () => clearInterval(interval);
    }, [fetchNotifications]);

    /** Mark one notification as read */
    const markAsRead = useCallback(
        async (notificationId) => {
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
                            ? { ...n, read_at: new Date() }
                            : n
                    )
                );
                setUnreadCount(
                    (prev) =>
                        notifications.filter(
                            (n) => n.id !== notificationId && !n.read_at
                        ).length
                );
            } catch (error) {
                console.error("Failed to mark as read:", error);
            }
        },
        [notifications]
    );

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

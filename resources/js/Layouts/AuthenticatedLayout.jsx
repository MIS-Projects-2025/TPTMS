import NavBar from "@/Components/NavBar";
import Sidebar from "@/Components/Sidebar/SideBar";
import LoadingScreen from "@/Components/LoadingScreen";
import { usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import axios from "axios";

export default function AuthenticatedLayout({ children }) {
    const { emp_data, auth } = usePage().props;
    const idleMinutes = auth?.idle_timeout_minutes ?? 30; // Restore idleMinutes
    const [showWarning, setShowWarning] = useState(false);
    const warningSeconds = 30; // show warning 30 seconds before logout

    useEffect(() => {
        if (!idleMinutes || idleMinutes === 0) return;

        let idleTimer;
        let warningTimer;
        const idleSeconds = idleMinutes * 60; // convert minutes to seconds

        const startIdleTimer = () => {
            clearTimeout(idleTimer);
            clearTimeout(warningTimer);

            // Show warning before logout
            warningTimer = setTimeout(
                () => {
                    setShowWarning(true);
                },
                (idleSeconds - warningSeconds) * 1000,
            );

            // Actual logout using Ziggy route
            idleTimer = setTimeout(async () => {
                try {
                    await axios.get(route("logout")); // Ziggy generates the URL
                } catch (error) {
                    console.error("Logout request failed:", error);
                } finally {
                    window.location.href = route("logout"); // redirect after logout
                }
            }, idleSeconds * 1000);
        };

        const handleVisibilityChange = () => {
            if (document.hidden) {
                startIdleTimer(); // start timer only when tab is hidden/minimized
            } else {
                clearTimeout(idleTimer);
                clearTimeout(warningTimer);
                setShowWarning(false);
            }
        };

        document.addEventListener("visibilitychange", handleVisibilityChange);

        return () => {
            clearTimeout(idleTimer);
            clearTimeout(warningTimer);
            document.removeEventListener(
                "visibilitychange",
                handleVisibilityChange,
            );
        };
    }, [idleMinutes]);

    if (!emp_data) {
        return <LoadingScreen text="Loading user data..." />;
    }

    return (
        <div className="flex h-screen overflow-hidden">
            <Sidebar />
            <div className="flex-1 flex flex-col min-w-0">
                <NavBar />
                <main className="flex-1 px-2 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-6 pb-[70px] overflow-y-auto">
                    {children}
                </main>
                <footer className="px-6 py-1.5 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-end">
                    <span className="text-[9px] text-zinc-400 dark:text-zinc-600">
                        Developed by:
                        <span className="font-semibold text-zinc-500 dark:text-zinc-500">
                            Jester Ryan B. Tañada
                        </span>
                    </span>
                </footer>

                {/* Warning modal */}
                {showWarning && (
                    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                        <div className="bg-white dark:bg-zinc-800 p-6 rounded shadow-lg text-center">
                            <p className="text-zinc-800 dark:text-zinc-200 mb-4">
                                You were away. You will be logged out in{" "}
                                {warningSeconds} seconds.
                            </p>
                            <button
                                onClick={() => setShowWarning(false)}
                                className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                            >
                                Stay Logged In
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

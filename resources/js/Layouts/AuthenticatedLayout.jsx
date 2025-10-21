import NavBar from "@/Components/NavBar";
import Sidebar from "@/Components/Sidebar/SideBar";
import LoadingScreen from "@/Components/LoadingScreen";
import { usePage, router } from "@inertiajs/react";
import { useEffect, useState } from "react";

export default function AuthenticatedLayout({ children }) {
    const { url } = usePage();
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        authCheck();
    }, [url]);

    const authCheck = () => {
        setIsLoading(true);

        // 1️⃣ Get token from URL
        const queryParams = new URLSearchParams(url.split("?")[1]);
        const queryToken = queryParams.get("key");

        if (queryToken) {
            // 2️⃣ Store token in localStorage for smooth client-side UX
            localStorage.setItem("authify-token", queryToken);

            // 3️⃣ Remove query params from URL
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);

            // 4️⃣ Trigger an Inertia visit to refresh page and let middleware set session
            router.get(
                window.location.pathname,
                {},
                { preserveState: true, preserveScroll: true }
            );
        }

        setIsLoading(false);
    };

    return (
        <div className="flex flex-col">
            {isLoading && <LoadingScreen text="Please wait..." />}
            <div className="flex h-screen overflow-hidden">
                <Sidebar />
                <div className="flex-1 min-w-0">
                    <NavBar />
                    <main className="h-screen px-4 sm:px-6 py-6 pb-[70px] overflow-y-auto">
                        <div>{children}</div>
                    </main>
                </div>
            </div>
        </div>
    );
}

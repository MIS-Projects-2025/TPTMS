import NavBar from "@/Components/NavBar";
import Sidebar from "@/Components/Sidebar/SideBar";
import LoadingScreen from "@/Components/LoadingScreen";
import { usePage } from "@inertiajs/react";

export default function AuthenticatedLayout({ children }) {
    const { emp_data } = usePage().props;

    if (!emp_data) {
        return <LoadingScreen text="Loading user data..." />;
    }

    return (
        <div className="flex h-screen overflow-hidden">
            <Sidebar /> {/* vertical sidebar */}
            <div className="flex-1 flex flex-col min-w-0">
                <NavBar /> {/* top navbar */}
                <main className="flex-1 px-2 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-6 overflow-y-auto">
                    {children}
                </main>
                {/* ── Footer ── */}
                <footer className="px-6 py-1.5 border-t border-base-300 flex items-center justify-end">
                    <span className="text-[9px] text-base-content/40">
                        Developed by:
                        <span className="font-semibold text-base-content/60">
                            Jester Ryan B. Tañada
                        </span>
                    </span>
                </footer>
            </div>
        </div>
    );
}

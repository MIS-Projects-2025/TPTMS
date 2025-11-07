import NavBar from "@/Components/NavBar";
import Sidebar from "@/Components/Sidebar/SideBar";
import LoadingScreen from "@/Components/LoadingScreen";
import { usePage } from "@inertiajs/react";
import { NotificationProvider } from "@/Context/NotificationContext";

export default function AuthenticatedLayout({ children }) {
    const { emp_data } = usePage().props; // get emp_data
    // console.log(props);

    if (!emp_data) {
        return <LoadingScreen text="Loading user data..." />;
    }

    return (
        <NotificationProvider userId={emp_data.emp_id}>
            <div className="flex h-screen overflow-hidden">
                <Sidebar /> {/* vertical sidebar */}
                <div className="flex-1 flex flex-col min-w-0">
                    <NavBar /> {/* top navbar */}
                    <main className="flex-1 px-2 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-6 pb-[70px] overflow-y-auto">
                        {children}
                    </main>
                </div>
            </div>
        </NotificationProvider>
    );
}

import Dropdown from "@/Components/sidebar/Dropdown";
import SidebarLink from "@/Components/sidebar/SidebarLink";
import { usePage } from "@inertiajs/react";
import {
    LayoutDashboard,
    TicketPlus,
    Ticket,
    FolderKanban,
    ListTodo,
} from "lucide-react";

export default function NavLinks({ isSidebarOpen }) {
    const { emp_data } = usePage().props;

    // Define ticket dropdown links
    const ticketLinks = [
        {
            href: route("tickets"),
            label: "Generate Ticket",
            icon: <TicketPlus className="w-4 h-4" />,
        },
        {
            href: route("tickets.datatable"),
            label: "Ticket List",
            icon: <Ticket className="w-4 h-4" />,
        },
    ];

    return (
        <nav
            className="flex flex-col flex-grow space-y-1 overflow-y-auto"
            style={{ scrollbarWidth: "none" }}
        >
            <SidebarLink
                href={route("dashboard")}
                label="Dashboard"
                icon={<LayoutDashboard className="w-5 h-5" />}
                isSidebarOpen={isSidebarOpen}
            />

            {/* Tickets Dropdown */}
            <Dropdown
                label="Tickets"
                icon={<Ticket className="w-5 h-5" />}
                links={ticketLinks}
            />

            {/* Admin section */}
            {["superadmin", "admin"].includes(emp_data?.emp_system_role) && (
                <SidebarLink
                    href={route("admin")}
                    label="Administrators"
                    icon={
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            strokeWidth="1.5"
                            stroke="currentColor"
                            className="w-5 h-5"
                        >
                            {/* User head and body */}
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"
                            />
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M4.5 20.25v-1.5A4.5 4.5 0 019 14.25h2.25"
                            />
                            {/* Shield */}
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M17.25 14.25l2.25.75v2.25c0 2.25-2.25 3-2.25 3s-2.25-.75-2.25-3v-2.25l2.25-.75z"
                            />
                        </svg>
                    }
                    isSidebarOpen={isSidebarOpen}
                />
            )}
        </nav>
    );
}

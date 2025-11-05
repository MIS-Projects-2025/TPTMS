import { useEffect, useState } from "react";
import axios from "axios";
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
import { Avatar, Badge } from "antd";

export default function NavLinks({ isSidebarOpen }) {
    const { emp_data } = usePage().props;
    const [ticketCount, setTicketCount] = useState(0);

    useEffect(() => {
        axios
            .get(route("tickets.count"))
            .then((res) => setTicketCount(res.data.count))
            .catch(() => setTicketCount(0));
    }, []);

    const ticketLinks = [
        {
            href: route("tickets"),
            label: "Generate Ticket",
            icon: <TicketPlus className="w-4 h-4" />,
        },
        {
            href: route("tickets.datatable"),
            label: (
                <div className="flex items-center justify-between w-full">
                    <span>Ticket List</span>
                    {ticketCount > 0 && (
                        <span className="ml-2 bg-blue-600 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                            {ticketCount}
                        </span>
                    )}
                </div>
            ),
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

            <Dropdown
                label={
                    <span className="flex items-center justify-between w-full">
                        <span>Tickets</span>
                        {ticketCount > 0 && (
                            <Badge dot={ticketCount > 0} className="ml-2" />
                        )}
                    </span>
                }
                icon={<Ticket className="w-5 h-5" />}
                links={ticketLinks}
                isSidebarOpen={isSidebarOpen}
            />

            <SidebarLink
                href={route("project.list")}
                label="Projects"
                icon={<FolderKanban className="w-5 h-5" />}
                isSidebarOpen={isSidebarOpen}
            />

            {emp_data.emp_system_role === "Programmer" && (
                <SidebarLink
                    href={route("tasks")}
                    label="Tasks"
                    icon={<ListTodo className="w-5 h-5" />}
                    isSidebarOpen={isSidebarOpen}
                />
            )}
        </nav>
    );
}

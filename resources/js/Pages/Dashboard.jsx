import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, usePage } from "@inertiajs/react";
import { useState } from "react";
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
} from "chart.js";
import { Bar, Line, Doughnut } from "react-chartjs-2";

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    ArcElement,
    Title,
    Tooltip,
    Legend
);

export default function Dashboard() {
    const { props } = usePage();
    const {
        quickStats = {},
        recentActivity = {},
        notifications = [],
        userRoles = [],
        programmerData,
        supervisorData,
        dhData,
        odData,
        executiveData,
        chartData = {},
    } = props;

    // Role-based access indicators
    const isProgrammer = userRoles.includes("PROGRAMMER");
    const isSupervisor = userRoles.includes("MIS_SUPERVISOR");
    const isManager = userRoles.includes("MIS_MANAGER");
    const isDepartmentHead = userRoles.includes("DEPARTMENT_HEAD");
    const isOD = userRoles.includes("OD");
    const isDirector = userRoles.includes("DIRECTOR");
    const isPresident = userRoles.includes("PRESIDENT");
    const isRequestor = userRoles.includes("REQUESTOR");
    const isExecutive = isDirector || isPresident;

    // Technical roles (can see tasks)
    const isTechnical = isProgrammer || isSupervisor || isManager;

    // Chart configurations
    const ticketStatusChart = {
        labels: chartData.ticketStatus?.labels || [],
        datasets: [
            {
                label: "Tickets by Status",
                data: chartData.ticketStatus?.data || [],
                backgroundColor: chartData.ticketStatus?.colors || [],
                borderColor:
                    chartData.ticketStatus?.colors?.map((color) =>
                        color.replace("0.2", "1")
                    ) || [],
                borderWidth: 1,
            },
        ],
    };

    const monthlyTrendChart = {
        labels: chartData.monthlyTrend?.labels || [],
        datasets: [
            {
                label: "Tickets Created",
                data: chartData.monthlyTrend?.data || [],
                borderColor: "rgb(59, 130, 246)",
                backgroundColor: "rgba(59, 130, 246, 0.1)",
                tension: 0.1,
            },
        ],
    };

    const projectStatusChart = {
        labels: chartData.projectStatus?.labels || [],
        datasets: [
            {
                label: "Projects by Status",
                data: chartData.projectStatus?.data || [],
                backgroundColor: [
                    "rgba(34, 197, 94, 0.2)",
                    "rgba(59, 130, 246, 0.2)",
                    "rgba(249, 115, 22, 0.2)",
                    "rgba(239, 68, 68, 0.2)",
                ],
                borderColor: [
                    "rgb(34, 197, 94)",
                    "rgb(59, 130, 246)",
                    "rgb(249, 115, 22)",
                    "rgb(239, 68, 68)",
                ],
                borderWidth: 1,
            },
        ],
    };

    const chartOptions = {
        responsive: true,
        plugins: {
            legend: {
                position: "top",
            },
        },
    };

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            {/* Quick Stats Section */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                {/* Executive Stats */}
                {isExecutive && (
                    <>
                        <div className="card bg-base-100 shadow-lg">
                            <div className="card-body">
                                <div className="flex items-center">
                                    <div className="p-3 bg-primary/20 rounded-lg">
                                        <svg
                                            className="w-6 h-6 text-primary"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                            />
                                        </svg>
                                    </div>
                                    <div className="ml-4">
                                        <h3 className="text-sm font-medium text-base-content/70">
                                            Total Tickets
                                        </h3>
                                        <p className="text-2xl font-semibold text-base-content">
                                            {quickStats.total_tickets || 0}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="card bg-base-100 shadow-lg">
                            <div className="card-body">
                                <div className="flex items-center">
                                    <div className="p-3 bg-success/20 rounded-lg">
                                        <svg
                                            className="w-6 h-6 text-success"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                            />
                                        </svg>
                                    </div>
                                    <div className="ml-4">
                                        <h3 className="text-sm font-medium text-base-content/70">
                                            Total Projects
                                        </h3>
                                        <p className="text-2xl font-semibold text-base-content">
                                            {quickStats.total_projects || 0}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="card bg-base-100 shadow-lg">
                            <div className="card-body">
                                <div className="flex items-center">
                                    <div className="p-3 bg-warning/20 rounded-lg">
                                        <svg
                                            className="w-6 h-6 text-warning"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"
                                            />
                                        </svg>
                                    </div>
                                    <div className="ml-4">
                                        <h3 className="text-sm font-medium text-base-content/70">
                                            Open Tickets
                                        </h3>
                                        <p className="text-2xl font-semibold text-base-content">
                                            {quickStats.open_tickets || 0}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="card bg-base-100 shadow-lg">
                            <div className="card-body">
                                <div className="flex items-center">
                                    <div className="p-3 bg-info/20 rounded-lg">
                                        <svg
                                            className="w-6 h-6 text-info"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                            />
                                        </svg>
                                    </div>
                                    <div className="ml-4">
                                        <h3 className="text-sm font-medium text-base-content/70">
                                            Completed
                                        </h3>
                                        <p className="text-2xl font-semibold text-base-content">
                                            {quickStats.completed_tickets || 0}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </>
                )}

                {/* Role-specific stats */}
                {!isExecutive && (
                    <>
                        {(isProgrammer || isSupervisor || isManager) && (
                            <div className="card bg-base-100 shadow-lg">
                                <div className="card-body">
                                    <div className="flex items-center">
                                        <div className="p-3 bg-primary/20 rounded-lg">
                                            <svg
                                                className="w-6 h-6 text-primary"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                />
                                            </svg>
                                        </div>
                                        <div className="ml-4">
                                            <h3 className="text-sm font-medium text-base-content/70">
                                                Assigned Tickets
                                            </h3>
                                            <p className="text-2xl font-semibold text-base-content">
                                                {quickStats.assigned_tickets ||
                                                    0}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {isRequestor && (
                            <div className="card bg-base-100 shadow-lg">
                                <div className="card-body">
                                    <div className="flex items-center">
                                        <div className="p-3 bg-success/20 rounded-lg">
                                            <svg
                                                className="w-6 h-6 text-success"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                                />
                                            </svg>
                                        </div>
                                        <div className="ml-4">
                                            <h3 className="text-sm font-medium text-base-content/70">
                                                My Pending Tickets
                                            </h3>
                                            <p className="text-2xl font-semibold text-base-content">
                                                {quickStats.my_pending_tickets ||
                                                    0}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {isTechnical && (
                            <div className="card bg-base-100 shadow-lg">
                                <div className="card-body">
                                    <div className="flex items-center">
                                        <div className="p-3 bg-warning/20 rounded-lg">
                                            <svg
                                                className="w-6 h-6 text-warning"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                        </div>
                                        <div className="ml-4">
                                            <h3 className="text-sm font-medium text-base-content/70">
                                                Pending Tasks
                                            </h3>
                                            <p className="text-2xl font-semibold text-base-content">
                                                {quickStats.pending_tasks || 0}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {isDepartmentHead && (
                            <div className="card bg-base-100 shadow-lg">
                                <div className="card-body">
                                    <div className="flex items-center">
                                        <div className="p-3 bg-secondary/20 rounded-lg">
                                            <svg
                                                className="w-6 h-6 text-secondary"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                                />
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                                />
                                            </svg>
                                        </div>
                                        <div className="ml-4">
                                            <h3 className="text-sm font-medium text-base-content/70">
                                                Pending DH Approvals
                                            </h3>
                                            <p className="text-2xl font-semibold text-base-content">
                                                {quickStats.pending_dh_approvals ||
                                                    0}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {isOD && (
                            <div className="card bg-base-100 shadow-lg">
                                <div className="card-body">
                                    <div className="flex items-center">
                                        <div className="p-3 bg-accent/20 rounded-lg">
                                            <svg
                                                className="w-6 h-6 text-accent"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                                />
                                            </svg>
                                        </div>
                                        <div className="ml-4">
                                            <h3 className="text-sm font-medium text-base-content/70">
                                                Pending OD Approvals
                                            </h3>
                                            <p className="text-2xl font-semibold text-base-content">
                                                {quickStats.pending_od_approvals ||
                                                    0}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            {/* Charts Section */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                {/* Ticket Status Chart */}
                <div className="card bg-base-100 shadow-lg">
                    <div className="card-body">
                        <h2 className="card-title">
                            Ticket Status Distribution
                        </h2>
                        <div className="h-80">
                            <Doughnut
                                data={ticketStatusChart}
                                options={chartOptions}
                            />
                        </div>
                    </div>
                </div>

                {/* Monthly Trend Chart */}
                <div className="card bg-base-100 shadow-lg">
                    <div className="card-body">
                        <h2 className="card-title">Monthly Ticket Trend</h2>
                        <div className="h-80">
                            <Line
                                data={monthlyTrendChart}
                                options={chartOptions}
                            />
                        </div>
                    </div>
                </div>

                {/* Project Status Chart for Executives */}
                {isExecutive && chartData.projectStatus && (
                    <div className="card bg-base-100 shadow-lg lg:col-span-2">
                        <div className="card-body">
                            <h2 className="card-title">
                                Project Status Distribution
                            </h2>
                            <div className="h-80">
                                <Bar
                                    data={projectStatusChart}
                                    options={chartOptions}
                                />
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Recent Activity Section */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                {/* Recent Tickets */}
                <div className="card bg-base-100 shadow-lg">
                    <div className="card-body">
                        <h2 className="card-title text-lg">Recent Tickets</h2>
                        <div className="divider m-0"></div>
                        <div className="space-y-4">
                            {recentActivity.tickets &&
                            recentActivity.tickets.length > 0 ? (
                                recentActivity.tickets
                                    .slice(0, 5)
                                    .map((ticket) => (
                                        <div
                                            key={ticket.TICKET_ID}
                                            className="flex items-center justify-between py-2"
                                        >
                                            <div className="flex-1">
                                                <p className="font-semibold text-base-content">
                                                    {ticket.TICKET_ID}
                                                </p>
                                                <p className="text-sm text-base-content/70">
                                                    {ticket.PROJECT_NAME}
                                                </p>
                                                {isExecutive && (
                                                    <p className="text-xs text-base-content/50">
                                                        By: {ticket.EMPLOYID}
                                                    </p>
                                                )}
                                            </div>
                                            <span
                                                className={`badge badge-sm ${
                                                    ticket.STATUS === 5
                                                        ? "badge-success"
                                                        : ticket.STATUS === 4
                                                        ? "badge-info"
                                                        : "badge-warning"
                                                }`}
                                            >
                                                {ticket.STATUS === 1
                                                    ? "New"
                                                    : ticket.STATUS === 2
                                                    ? "Triaged"
                                                    : ticket.STATUS === 3
                                                    ? "Approved"
                                                    : ticket.STATUS === 4
                                                    ? "In Progress"
                                                    : ticket.STATUS === 5
                                                    ? "Resolved"
                                                    : "Unknown"}
                                            </span>
                                        </div>
                                    ))
                            ) : (
                                <p className="text-base-content/70 text-center py-4">
                                    No recent tickets
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                {/* Recent Tasks (only for technical roles) */}
                {isTechnical && (
                    <div className="card bg-base-100 shadow-lg">
                        <div className="card-body">
                            <h2 className="card-title text-lg">Recent Tasks</h2>
                            <div className="divider m-0"></div>
                            <div className="space-y-4">
                                {recentActivity.tasks &&
                                recentActivity.tasks.length > 0 ? (
                                    recentActivity.tasks
                                        .slice(0, 5)
                                        .map((task) => (
                                            <div
                                                key={task.TASK_ID}
                                                className="flex items-center justify-between py-2"
                                            >
                                                <div className="flex-1">
                                                    <p className="font-semibold text-base-content truncate">
                                                        {task.TASK_TITLE}
                                                    </p>
                                                    <p className="text-sm text-base-content/70">
                                                        {task.SOURCE_TYPE}
                                                    </p>
                                                </div>
                                                <span
                                                    className={`badge badge-sm ${
                                                        task.STATUS === 5
                                                            ? "badge-success"
                                                            : "badge-warning"
                                                    }`}
                                                >
                                                    {task.STATUS === 1
                                                        ? "Pending"
                                                        : task.STATUS === 2
                                                        ? "In Progress"
                                                        : task.STATUS === 3
                                                        ? "Review"
                                                        : task.STATUS === 4
                                                        ? "Testing"
                                                        : task.STATUS === 5
                                                        ? "Completed"
                                                        : "Unknown"}
                                                </span>
                                            </div>
                                        ))
                                ) : (
                                    <p className="text-base-content/70 text-center py-4">
                                        No recent tasks
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* Recent Projects for Executives */}
                {isExecutive && (
                    <div className="card bg-base-100 shadow-lg lg:col-span-2">
                        <div className="card-body">
                            <h2 className="card-title text-lg">
                                Recent Projects
                            </h2>
                            <div className="divider m-0"></div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {recentActivity.projects &&
                                recentActivity.projects.length > 0 ? (
                                    recentActivity.projects
                                        .slice(0, 6)
                                        .map((project) => (
                                            <div
                                                key={project.PROJ_ID}
                                                className="flex items-center justify-between p-4 bg-base-200 rounded-lg"
                                            >
                                                <div className="flex-1">
                                                    <p className="font-semibold text-base-content">
                                                        {project.PROJ_NAME}
                                                    </p>
                                                    <p className="text-sm text-base-content/70">
                                                        ID: {project.PROJ_ID}
                                                    </p>
                                                </div>
                                                <span
                                                    className={`badge ${
                                                        project.PROJ_STATUS ===
                                                        "DEPLOYED"
                                                            ? "badge-success"
                                                            : project.PROJ_STATUS ===
                                                              "IN_PROGRESS"
                                                            ? "badge-info"
                                                            : project.PROJ_STATUS ===
                                                              "READY"
                                                            ? "badge-warning"
                                                            : "badge-neutral"
                                                    }`}
                                                >
                                                    {project.PROJ_STATUS}
                                                </span>
                                            </div>
                                        ))
                                ) : (
                                    <p className="text-base-content/70 text-center py-4 col-span-2">
                                        No recent projects
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Role-specific Sections */}
            {isTechnical && programmerData && (
                <div className="card bg-base-100 shadow-lg mb-6">
                    <div className="card-body">
                        <h2 className="card-title text-lg">
                            Technical Overview
                        </h2>
                        <div className="divider m-0"></div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 className="font-semibold mb-3 text-base-content">
                                    Assigned Tickets
                                </h3>
                                {programmerData.assigned_tickets &&
                                programmerData.assigned_tickets.length > 0 ? (
                                    <div className="space-y-2">
                                        {programmerData.assigned_tickets
                                            .slice(0, 5)
                                            .map((ticket) => (
                                                <div
                                                    key={ticket.TICKET_ID}
                                                    className="text-sm text-base-content/80"
                                                >
                                                    <span className="font-medium">
                                                        {ticket.TICKET_ID}
                                                    </span>{" "}
                                                    - {ticket.PROJECT_NAME}
                                                </div>
                                            ))}
                                    </div>
                                ) : (
                                    <p className="text-base-content/70">
                                        No assigned tickets
                                    </p>
                                )}
                            </div>
                            <div>
                                <h3 className="font-semibold mb-3 text-base-content">
                                    Pending Tasks
                                </h3>
                                {programmerData.pending_tasks &&
                                programmerData.pending_tasks.length > 0 ? (
                                    <div className="space-y-2">
                                        {programmerData.pending_tasks
                                            .slice(0, 5)
                                            .map((task) => (
                                                <div
                                                    key={task.TASK_ID}
                                                    className="text-sm text-base-content/80"
                                                >
                                                    {task.TASK_TITLE}
                                                </div>
                                            ))}
                                    </div>
                                ) : (
                                    <p className="text-base-content/70">
                                        No pending tasks
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Executive Overview */}
            {isExecutive && executiveData && (
                <div className="card bg-base-100 shadow-lg mb-6">
                    <div className="card-body">
                        <h2 className="card-title text-lg">
                            Executive Overview
                        </h2>
                        <div className="divider m-0"></div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 className="font-semibold mb-3 text-base-content">
                                    Recent Tickets
                                </h3>
                                {executiveData.all_tickets &&
                                executiveData.all_tickets.length > 0 ? (
                                    <div className="space-y-2">
                                        {executiveData.all_tickets
                                            .slice(0, 5)
                                            .map((ticket) => (
                                                <div
                                                    key={ticket.TICKET_ID}
                                                    className="text-sm text-base-content/80"
                                                >
                                                    <span className="font-medium">
                                                        {ticket.TICKET_ID}
                                                    </span>{" "}
                                                    - {ticket.PROJECT_NAME}
                                                </div>
                                            ))}
                                    </div>
                                ) : (
                                    <p className="text-base-content/70">
                                        No tickets
                                    </p>
                                )}
                            </div>
                            <div>
                                <h3 className="font-semibold mb-3 text-base-content">
                                    Recent Projects
                                </h3>
                                {executiveData.all_projects &&
                                executiveData.all_projects.length > 0 ? (
                                    <div className="space-y-2">
                                        {executiveData.all_projects
                                            .slice(0, 5)
                                            .map((project) => (
                                                <div
                                                    key={project.PROJ_ID}
                                                    className="text-sm text-base-content/80"
                                                >
                                                    <span className="font-medium">
                                                        {project.PROJ_ID}
                                                    </span>{" "}
                                                    - {project.PROJ_NAME}
                                                </div>
                                            ))}
                                    </div>
                                ) : (
                                    <p className="text-base-content/70">
                                        No projects
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Debug Info */}
            <div className="mt-8 card bg-base-200">
                <div className="card-body">
                    <h4 className="card-title text-sm">
                        Debug Info (Remove in Production)
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div className="text-base-content/80">
                            <strong>User Roles:</strong> {userRoles.join(", ")}
                        </div>
                        <div className="text-base-content/80">
                            <strong>Employee ID:</strong>{" "}
                            {props.emp_data?.emp_id}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

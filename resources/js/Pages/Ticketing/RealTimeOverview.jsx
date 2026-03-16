import React, { useState } from "react";
import { Radio, Card } from "antd";

export default function RealtimeNotificationDiagram() {
    const [activeFlow, setActiveFlow] = useState("overview");

    const flows = {
        overview: {
            title: "Complete System Overview",
            steps: [
                {
                    phase: "AUTHENTICATION",
                    color: "bg-blue-100 border-blue-400",
                    items: [
                        {
                            step: "1",
                            desc: "User accesses app with SSO token",
                            tech: "AuthMiddleware",
                        },
                        {
                            step: "2",
                            desc: "Verify token in authify database",
                            tech: "MySQL Query",
                        },
                        {
                            step: "3",
                            desc: "Create/update NotificationUser record",
                            tech: "NotificationUser Model",
                        },
                        {
                            step: "4",
                            desc: "Store session data (emp_data)",
                            tech: "Laravel Session",
                        },
                    ],
                },
                {
                    phase: "WEBSOCKET CONNECTION",
                    color: "bg-purple-100 border-purple-400",
                    items: [
                        {
                            step: "5",
                            desc: "React app initializes Echo",
                            tech: "Laravel Echo",
                        },
                        {
                            step: "6",
                            desc: "Subscribe to private channel",
                            tech: "users.{emp_id}",
                        },
                        {
                            step: "7",
                            desc: "Auth request to /broadcasting/auth",
                            tech: "POST Request",
                        },
                        {
                            step: "8",
                            desc: "Verify session & authorize channel",
                            tech: "Broadcast::auth()",
                        },
                        {
                            step: "9",
                            desc: "WebSocket connection established",
                            tech: "Laravel Reverb",
                        },
                    ],
                },
                {
                    phase: "TICKET CREATION",
                    color: "bg-green-100 border-green-400",
                    items: [
                        {
                            step: "10",
                            desc: "User creates ticket",
                            tech: "React Form",
                        },
                        {
                            step: "11",
                            desc: "Store ticket in database",
                            tech: "Tickets Table",
                        },
                        {
                            step: "12",
                            desc: "NotificationService determines recipients",
                            tech: "NotificationService",
                        },
                        {
                            step: "13",
                            desc: "Create/fetch NotificationUser for each",
                            tech: "masterlist DB",
                        },
                    ],
                },
                {
                    phase: "NOTIFICATION BROADCAST",
                    color: "bg-orange-100 border-orange-400",
                    items: [
                        {
                            step: "14",
                            desc: "Send TicketCreatedNotification",
                            tech: "Laravel Notification",
                        },
                        {
                            step: "15",
                            desc: "Save to notifications table",
                            tech: "toDatabase()",
                        },
                        {
                            step: "16",
                            desc: "Broadcast to Reverb server",
                            tech: "toBroadcast()",
                        },
                        {
                            step: "17",
                            desc: "Reverb pushes to WebSocket",
                            tech: "Private Channel",
                        },
                    ],
                },
                {
                    phase: "REAL-TIME UPDATE",
                    color: "bg-pink-100 border-pink-400",
                    items: [
                        {
                            step: "18",
                            desc: "React receives notification event",
                            tech: "channel.listen()",
                        },
                        {
                            step: "19",
                            desc: "Update notifications state",
                            tech: "useState",
                        },
                        {
                            step: "20",
                            desc: "Increment unreadCount",
                            tech: "setUnreadCount()",
                        },
                        {
                            step: "21",
                            desc: "Trigger table refresh",
                            tech: "useEffect",
                        },
                        {
                            step: "22",
                            desc: "Fetch updated tickets",
                            tech: "Inertia.reload()",
                        },
                    ],
                },
            ],
        },
        authentication: {
            title: "Authentication & Session Flow",
            steps: [
                {
                    phase: "SSO LOGIN",
                    color: "bg-indigo-100 border-indigo-400",
                    items: [
                        {
                            step: "1",
                            desc: "User clicks login button",
                            tech: "Browser",
                        },
                        {
                            step: "2",
                            desc: "Redirect to SSO server",
                            tech: "http://192.168.2.221:8080/authify",
                        },
                        {
                            step: "3",
                            desc: "SSO validates credentials",
                            tech: "Authify System",
                        },
                        {
                            step: "4",
                            desc: "Generate unique token",
                            tech: "UUID Token",
                        },
                        {
                            step: "5",
                            desc: "Store in authify_sessions table",
                            tech: "authify.authify_sessions",
                        },
                        {
                            step: "6",
                            desc: "Redirect back with ?key=TOKEN",
                            tech: "URL Parameter",
                        },
                    ],
                },
                {
                    phase: "SESSION SETUP",
                    color: "bg-blue-100 border-blue-400",
                    items: [
                        {
                            step: "7",
                            desc: "AuthMiddleware intercepts request",
                            tech: "Middleware",
                        },
                        {
                            step: "8",
                            desc: "Query token from authify DB",
                            tech: 'DB::connection("authify")',
                        },
                        {
                            step: "9",
                            desc: "Extract user data",
                            tech: "emp_id, emp_name, emp_dept",
                        },
                        {
                            step: "10",
                            desc: "Store in Laravel session",
                            tech: "session([emp_data => ...])",
                        },
                        {
                            step: "11",
                            desc: "Create NotificationUser record",
                            tech: "NotificationUser::create()",
                        },
                    ],
                },
                {
                    phase: "BROADCAST AUTH",
                    color: "bg-purple-100 border-purple-400",
                    items: [
                        {
                            step: "12",
                            desc: "Echo requests channel subscription",
                            tech: "POST /broadcasting/auth",
                        },
                        {
                            step: "13",
                            desc: "Read session data",
                            tech: 'session("emp_data")',
                        },
                        {
                            step: "14",
                            desc: "Find/create NotificationUser",
                            tech: "firstOrCreate()",
                        },
                        {
                            step: "15",
                            desc: "Verify channel authorization",
                            tech: "channels.php",
                        },
                        {
                            step: "16",
                            desc: "Return auth signature",
                            tech: "Broadcast::auth()",
                        },
                    ],
                },
            ],
        },
        notification: {
            title: "Notification Creation & Broadcasting",
            steps: [
                {
                    phase: "RECIPIENT DETERMINATION",
                    color: "bg-cyan-100 border-cyan-400",
                    items: [
                        {
                            step: "1",
                            desc: "Ticket saved to database",
                            tech: "tickets table",
                        },
                        {
                            step: "2",
                            desc: "NotificationService called",
                            tech: "notifyTicketCreated()",
                        },
                        {
                            step: "3",
                            desc: "Determine request type",
                            tech: "Type 1-6",
                        },
                        {
                            step: "4",
                            desc: "Query recipients from masterlist",
                            tech: "getMISProgrammers()",
                        },
                        {
                            step: "5",
                            desc: "Get emp_ids: [751, 1328, 1705...]",
                            tech: "SQL Query",
                        },
                    ],
                },
                {
                    phase: "USER PREPARATION",
                    color: "bg-teal-100 border-teal-400",
                    items: [
                        {
                            step: "6",
                            desc: "Loop through each emp_id",
                            tech: "foreach $recipients",
                        },
                        {
                            step: "7",
                            desc: "Check if NotificationUser exists",
                            tech: 'where("emp_id", $id)',
                        },
                        {
                            step: "8",
                            desc: "If not, query masterlist DB",
                            tech: "employee_masterlist",
                        },
                        {
                            step: "9",
                            desc: "Create NotificationUser",
                            tech: "emp_id, emp_name, emp_dept",
                        },
                        {
                            step: "10",
                            desc: "Prepare notification instance",
                            tech: "new TicketCreatedNotification()",
                        },
                    ],
                },
                {
                    phase: "NOTIFICATION DISPATCH",
                    color: "bg-green-100 border-green-400",
                    items: [
                        {
                            step: "11",
                            desc: "Call $user->notify()",
                            tech: "Laravel Notification",
                        },
                        {
                            step: "12",
                            desc: "Execute via() method",
                            tech: '["database", "broadcast"]',
                        },
                        {
                            step: "13",
                            desc: "Save to notifications table",
                            tech: "toDatabase()",
                        },
                        {
                            step: "14",
                            desc: "Prepare broadcast payload",
                            tech: "toBroadcast()",
                        },
                        {
                            step: "15",
                            desc: "Determine channel",
                            tech: "broadcastOn() → users.{emp_id}",
                        },
                        {
                            step: "16",
                            desc: "Set event name",
                            tech: "broadcastAs() → notification.created",
                        },
                    ],
                },
                {
                    phase: "REVERB BROADCAST",
                    color: "bg-orange-100 border-orange-400",
                    items: [
                        {
                            step: "17",
                            desc: "Push to Reverb server",
                            tech: "Laravel Reverb",
                        },
                        {
                            step: "18",
                            desc: "Reverb validates channel",
                            tech: "Private Channel Check",
                        },
                        {
                            step: "19",
                            desc: "Send to subscribed connections",
                            tech: "WebSocket Push",
                        },
                        {
                            step: "20",
                            desc: "Multiple users receive simultaneously",
                            tech: "Real-time",
                        },
                    ],
                },
            ],
        },
        realtime: {
            title: "Real-time Frontend Update Flow",
            steps: [
                {
                    phase: "WEBSOCKET LISTENER",
                    color: "bg-violet-100 border-violet-400",
                    items: [
                        {
                            step: "1",
                            desc: "Echo listens on private channel",
                            tech: 'echo.private("users.1705")',
                        },
                        {
                            step: "2",
                            desc: "Event received from Reverb",
                            tech: ".notification.created",
                        },
                        {
                            step: "3",
                            desc: "Parse notification payload",
                            tech: "JSON Data",
                        },
                        {
                            step: "4",
                            desc: "Log to console",
                            tech: 'console.log("Real-time...")',
                        },
                    ],
                },
                {
                    phase: "STATE UPDATE",
                    color: "bg-fuchsia-100 border-fuchsia-400",
                    items: [
                        {
                            step: "5",
                            desc: "Create notification object",
                            tech: "id, ticket_id, message...",
                        },
                        {
                            step: "6",
                            desc: "Update notifications array",
                            tech: "setNotifications([new, ...prev])",
                        },
                        {
                            step: "7",
                            desc: "Count unread notifications",
                            tech: "filter(n => !n.read_at)",
                        },
                        {
                            step: "8",
                            desc: "Update unread count",
                            tech: "setUnreadCount(count)",
                        },
                    ],
                },
                {
                    phase: "TABLE REFRESH TRIGGER",
                    color: "bg-pink-100 border-pink-400",
                    items: [
                        {
                            step: "9",
                            desc: "useEffect watches unreadCount",
                            tech: "useEffect([unreadCount])",
                        },
                        {
                            step: "10",
                            desc: "Detect count increase",
                            tech: "if (current > previous)",
                        },
                        {
                            step: "11",
                            desc: "Log refresh event",
                            tech: 'console.log("📬 New notification...")',
                        },
                        {
                            step: "12",
                            desc: "Call refreshTickets()",
                            tech: "router.reload()",
                        },
                    ],
                },
                {
                    phase: "DATA RELOAD",
                    color: "bg-rose-100 border-rose-400",
                    items: [
                        {
                            step: "13",
                            desc: "Inertia partial reload",
                            tech: 'only: ["tickets", "statusCounts"]',
                        },
                        {
                            step: "14",
                            desc: "Fetch updated data from server",
                            tech: "GET /tickets/datatable",
                        },
                        {
                            step: "15",
                            desc: "Preserve scroll position",
                            tech: "preserveScroll: true",
                        },
                        {
                            step: "16",
                            desc: "Table re-renders with new data",
                            tech: "React Component",
                        },
                        {
                            step: "17",
                            desc: "User sees new ticket instantly!",
                            tech: "🎉 Real-time",
                        },
                    ],
                },
            ],
        },
        database: {
            title: "Database Architecture",
            steps: [
                {
                    phase: "AUTHENTICATION DB",
                    color: "bg-blue-100 border-blue-400",
                    items: [
                        {
                            step: "",
                            desc: "authify.authify_sessions",
                            tech: "SSO tokens & user data",
                        },
                        {
                            step: "",
                            desc: "Columns: token, emp_id, emp_name...",
                            tech: "Session storage",
                        },
                        {
                            step: "",
                            desc: "Query on every auth check",
                            tech: 'DB::connection("authify")',
                        },
                    ],
                },
                {
                    phase: "MASTERLIST DB",
                    color: "bg-cyan-100 border-cyan-400",
                    items: [
                        {
                            step: "",
                            desc: "employee_masterlist",
                            tech: "Employee directory",
                        },
                        {
                            step: "",
                            desc: "Columns: EMPLOYID, EMPNAME, DEPARTMENT",
                            tech: "HR data",
                        },
                        {
                            step: "",
                            desc: "Used to find notification recipients",
                            tech: "MIS programmers, DH, OD",
                        },
                    ],
                },
                {
                    phase: "APPLICATION DB",
                    color: "bg-green-100 border-green-400",
                    items: [
                        { step: "", desc: "tickets", tech: "Main ticket data" },
                        {
                            step: "",
                            desc: "notification_users",
                            tech: "Local user cache for notifications",
                        },
                        {
                            step: "",
                            desc: "notifications",
                            tech: "Laravel notification log",
                        },
                        {
                            step: "",
                            desc: "Columns: notifiable_id, data, read_at",
                            tech: "Notification history",
                        },
                    ],
                },
            ],
        },
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 p-8">
            <div className="max-w-7xl mx-auto">
                <h1 className="text-4xl font-bold text-slate-800 mb-2 text-center">
                    Laravel Reverb + React Notification System
                </h1>
                <p className="text-slate-600 text-center mb-8">
                    Real-time notification architecture with SSO authentication
                </p>

                <div className="mb-8 flex justify-center">
                    <Radio.Group
                        value={activeFlow}
                        onChange={(e) => setActiveFlow(e.target.value)}
                        buttonStyle="solid"
                        size="large"
                    >
                        <Radio.Button value="overview">
                            Complete Overview
                        </Radio.Button>
                        <Radio.Button value="authentication">
                            Authentication
                        </Radio.Button>
                        <Radio.Button value="notification">
                            Notification Flow
                        </Radio.Button>
                        <Radio.Button value="realtime">
                            Real-time Updates
                        </Radio.Button>
                        <Radio.Button value="database">
                            Database Structure
                        </Radio.Button>
                    </Radio.Group>
                </div>

                <Card className="shadow-lg border-2 border-slate-200">
                    <h2 className="text-2xl font-bold text-slate-700 mb-6 text-center">
                        {flows[activeFlow].title}
                    </h2>

                    <div className="space-y-8">
                        {flows[activeFlow].steps.map((section, idx) => (
                            <div key={idx} className="space-y-4">
                                <div
                                    className={`${section.color} border-2 rounded-lg p-4`}
                                >
                                    <h3 className="text-lg font-bold text-slate-800 mb-3">
                                        {section.phase}
                                    </h3>
                                    <div className="space-y-3">
                                        {section.items.map((item, itemIdx) => (
                                            <div
                                                key={itemIdx}
                                                className="bg-white rounded-lg p-4 shadow-sm border border-slate-200 hover:shadow-md transition-shadow"
                                            >
                                                <div className="flex items-start gap-3">
                                                    {item.step && (
                                                        <div className="flex-shrink-0 w-8 h-8 bg-slate-700 text-white rounded-full flex items-center justify-center font-bold text-sm">
                                                            {item.step}
                                                        </div>
                                                    )}
                                                    <div className="flex-1">
                                                        <p className="text-slate-800 font-medium mb-1">
                                                            {item.desc}
                                                        </p>
                                                        <code className="text-xs bg-slate-100 text-slate-700 px-2 py-1 rounded">
                                                            {item.tech}
                                                        </code>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="bg-blue-50 border-2 border-blue-200">
                        <h4 className="font-bold text-blue-900 mb-2">
                            Key Technologies
                        </h4>
                        <ul className="text-sm text-blue-800 space-y-1">
                            <li>• Laravel Reverb (WebSocket)</li>
                            <li>• Laravel Echo (Client)</li>
                            <li>• Private Channels</li>
                            <li>• Inertia.js + React</li>
                        </ul>
                    </Card>

                    <Card className="bg-green-50 border-2 border-green-200">
                        <h4 className="font-bold text-green-900 mb-2">
                            Data Flow
                        </h4>
                        <ul className="text-sm text-green-800 space-y-1">
                            <li>• SSO → Session → NotificationUser</li>
                            <li>• Ticket → Notification → Broadcast</li>
                            <li>• WebSocket → React → Table Refresh</li>
                        </ul>
                    </Card>

                    <Card className="bg-purple-50 border-2 border-purple-200">
                        <h4 className="font-bold text-purple-900 mb-2">
                            Real-time Features
                        </h4>
                        <ul className="text-sm text-purple-800 space-y-1">
                            <li>• Instant notifications (no polling)</li>
                            <li>• Multi-user broadcast</li>
                            <li>• Auto table refresh</li>
                            <li>• Unread count sync</li>
                        </ul>
                    </Card>
                </div>
            </div>
        </div>
    );
}

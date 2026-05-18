# TPTMS - Turnover Documentation

## System Overview

**TPTMS (Technical Project & Task Management System)** is a Laravel 12 + React/Inertia.js application for managing IT support tickets, software development projects, and programmer tasks — with a multi-level approval workflow and real-time WebSocket notifications.

### Core Purposes

- Submit and track IT support tickets through a structured approval chain
- Manage software development projects linked to ticket requests
- Track individual programmer tasks auto-created from ticket assignments
- Multi-level approval workflow: Assessment → DH Approval → OD Approval → Assignment → Resolution → Close
- Real-time notifications via WebSocket (Laravel Reverb)

---

## System Configuration

### 1. Environment Variables (`.env`)

```env
# Application
APP_NAME=tptms
APP_ENV=local
APP_DEBUG=true
APP_URL=https://tptms.local

# Main Database (SQLite — Laravel internals only)
DB_CONNECTION=sqlite

# Projects Database (MySQL)
PMS_HOST=192.168.x.x
PMS_PORT=3306
PMS_DATABASE=pms
PMS_USERNAME=root
PMS_PASSWORD=

# Tasks/Tickets Database (MySQL)
TMS_HOST=192.168.x.x
TMS_PORT=3306
TMS_DATABASE=tms
TMS_USERNAME=root
TMS_PASSWORD=

# Employee Masterlist (MySQL)
MDB_HOST=192.168.x.x
MDB_PORT=3306
MDB_DATABASE=masterlist
MDB_USERNAME=root
MDB_PASSWORD=

# Authify SSO (MySQL)
ADB_HOST=192.168.2.221
ADB_PORT=3306
ADB_DATABASE=authify
ADB_USERNAME=root
ADB_PASSWORD=

# Reverb WebSocket
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=sockets.example.com
REVERB_PORT=443
REVERB_SCHEME=https

# Pusher (fallback/alternative)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_FORCE_TLS=true
```

### 2. Database Connections (`config/database.php`)

| Connection Key | Purpose                                   | Key Tables                                                                                      |
| -------------- | ----------------------------------------- | ----------------------------------------------------------------------------------------------- |
| `projects`     | Project management (PMS_DB)               | `project_list`, `project_logs`                                                                  |
| `task`         | Tickets & tasks (TMS_DB)                  | `tickets`, `ticket_workflow`, `ticket_remarks`, `ticket_attachments`, `ticket_testers`, `tasks` |
| `masterlist`   | Employee data & approver lookups (MDB_DB) | `employee_masterlist`                                                                           |
| `authify`      | SSO session tokens (ADB)                  | `authify_sessions`                                                                              |
| `sqlite`       | Laravel internals                         | Cache, jobs, sessions                                                                           |

### 3. Web Server Configuration (Apache with HTTPS + Reverb)

```apache
# SSL Certificate required for WebSocket
SSLEngine on
SSLCertificateFile /path/to/cert.crt
SSLCertificateKeyFile /path/to/cert.key

# Proxy to Laravel application server
ProxyPass / http://127.0.0.1:8000/
ProxyPassReverse / http://127.0.0.1:8000/

# WebSocket proxy for Reverb
RewriteEngine On
RewriteCond %{HTTP:Upgrade} =websocket [NC]
RewriteRule /(.*)           ws://127.0.0.1:8080/$1 [P,L]
RewriteCond %{HTTP:Upgrade} !=websocket [NC]
RewriteRule /(.*)           http://127.0.0.1:8080/$1 [P,L]
```

### 4. Running the Application

```bash
# Install dependencies
composer install
npm install

# Development — all-in-one (server + queue + logs + vite)
composer run dev

# Or run individually:
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start
npm run dev

# Production build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Authentication & Access Control

### 1. SSO Authentication Flow

```
User accesses any route
        │
        ▼
AuthMiddleware resolves token (priority order):
  1. ?key= query parameter in URL
  2. SSO Cookie in browser
  3. Existing session token
        │
        ├── No token found?
        │       └──▶ Redirect to Authify login (192.168.2.221:8200)
        │
        └── Token found → Query authify_sessions table
                │
                ├── Token invalid/expired?
                │       └──▶ Redirect to Authify login
                │
                └── Token valid → Load employee record
                        │
                        │ Access control checks:
                        │   ① emp_from must be NULL (no external/vendor users)
                        │   ② ONE of the following must be true:
                        │      • emp_position >= 2
                        │      • Job title contains "programmer"
                        │      • Job title contains "MIS Senior Supervisor"
                        │      • User listed in project_list.PROJ_HANDLER
                        │
                        ├── Fails checks? → Access Denied (403)
                        │
                        └── Passes? → Session populated with emp_data:
                                {
                                    token,
                                    emp_id,
                                    emp_name,
                                    emp_firstname,
                                    emp_jobtitle,
                                    emp_dept,
                                    emp_prodline,
                                    emp_station,
                                    emp_position,
                                    emp_system_role,  ← "Programmer" if applicable
                                    generated_at
                                }
```

**`emp_system_role` is set to `"Programmer"` if:**

- Job title contains `"programmer"`, OR
- Job title contains `"MIS Senior Supervisor"`

This role value is used by `ProgrammerMiddleware` to restrict task-only routes.

### 2. Role Detection (Runtime, Per-Request)

Roles are not stored in the database. They are **dynamically computed** by `TicketService::getUserRoles()` (Lines 996–1053) from the session's `emp_data` at the time of each request.

A user can hold **multiple roles simultaneously**.

#### Role Hierarchy & Detection Logic

```
getUserRoles($empData) evaluates in this order:

1. MIS_SUPERVISOR
   └── emp_dept === 'MIS' (case-insensitive)
       AND job title contains "supervisor"

2. PROGRAMMER
   └── emp_dept === 'MIS'
       AND (job title contains "programmer"
            OR (job title contains "mis" AND "supervisor"))

3. DEPARTMENT_HEAD
   └── DB lookup: employee exists as APPROVER2 or APPROVER3
       in employee_masterlist table
       (cross-database query to masterlist connection)

4. OD (Operations Director)
   └── emp_dept === 'OPERATIONS'
       OR job title === 'OPERATIONS DIRECTOR' (case-insensitive)

5. REQUESTOR
   └── Default — user does not match any of the above roles
```

### 3. Route-Level Access Gates

```
All routes → AuthMiddleware
                │
                ├── /tickets/*    → TicketingController
                ├── /projects/*   → ProjectController
                └── /tasks/*      → ProgrammerMiddleware (additional gate)
                                        └── Requires emp_system_role === 'Programmer'
                                            If not: returns 403 Forbidden
```

---

## RBAC — Full Permission Matrix

### Ticket Actions by Role

| Action                     |   PROGRAMMER    | MIS_SUPERVISOR  | DEPARTMENT_HEAD | OD  |       REQUESTOR        |
| -------------------------- | :-------------: | :-------------: | :-------------: | :-: | :--------------------: |
| Create Ticket              |        ✓        |        ✓        |        ✓        |  ✓  |           ✓            |
| View Own Tickets           |        ✓        |        ✓        |        ✓        |  ✓  |           ✓            |
| View All Tickets           |        ✓        |        ✓        |      ✗ \*       |  ✓  |           ✗            |
| Assess Ticket              |        ✓        |        ✓        |        ✗        |  ✗  |           ✗            |
| DH Approve/Reject          |        ✗        |        ✗        |        ✓        |  ✗  |           ✗            |
| OD Approve/Reject          |        ✗        |        ✗        |        ✗        |  ✓  |           ✗            |
| Assign (full/DH workflows) |        ✗        |        ✓        |        ✗        |  ✗  |           ✗            |
| Assign (direct/testing)    |        ✓        |        ✓        |        ✗        |  ✗  |           ✗            |
| Resolve Ticket             | ✓ (if assigned) | ✓ (if assigned) |        ✗        |  ✗  |           ✗            |
| Close Ticket               |        ✗        |        ✗        |        ✗        |  ✗  |        ✓ (own)         |
| Return Ticket              |        ✓        |        ✓        |        ✗        |  ✗  |           ✗            |
| Resubmit Ticket            |        ✗        |        ✗        |        ✗        |  ✗  |        ✓ (own)         |
| Put On Hold / Resume       |        ✓        |        ✓        |        ✗        |  ✗  |           ✗            |
| Submit Test Result         |        ✗        |        ✗        |        ✗        |  ✗  | ✓ (if assigned tester) |

> \* Department Heads only see tickets where they are listed as the requestor's approver

### Project Actions by Role

| Action            | All Authenticated Users | Notes                                                      |
| ----------------- | :---------------------: | ---------------------------------------------------------- |
| View Project List |            ✓            | All pass AuthMiddleware                                    |
| Create Project    |            ✓            | Manual creation; also auto-created from New System tickets |
| Update Project    |            ✓            | Any authenticated user                                     |
| Import via Excel  |            ✓            | Required columns: PROJ_NAME, PROJ_DEPT, PROJ_STATUS        |
| Deploy Project    |            ✓            | Only allowed when all linked tickets are CLOSED            |

### Task Actions by Role

| Action             | PROGRAMMER / MIS Senior Supervisor | Other Roles |
| ------------------ | :--------------------------------: | :---------: |
| View Tasks         |                 ✓                  |   ✗ (403)   |
| Create Task        |                 ✓                  |   ✗ (403)   |
| Update Task Status |                 ✓                  |   ✗ (403)   |
| Complete Task      |                 ✓                  |   ✗ (403)   |
| Add Task Note      |                 ✓                  |   ✗ (403)   |
| View Task History  |                 ✓                  |   ✗ (403)   |

> Task routes are double-gated: AuthMiddleware + ProgrammerMiddleware

---

## Ticket Workflow In Detail

### Workflow Types

| Type               | Applies To                            | Approval Steps                            |
| ------------------ | ------------------------------------- | ----------------------------------------- |
| `FULL_APPROVAL`    | New System, Modification, Enhancement | Assess → DH Approve → OD Approve → Assign |
| `DH_APPROVAL_ONLY` | Adjustment                            | Assess → DH Approve → Assign              |
| `DIRECT_ASSIGN`    | Testing, Parallel Run                 | Assign directly (no approvals)            |

### Status Transition Reference

```
Status Flow:
  NEW (1) → TRIAGED (2) → APPROVED (3) → IN_PROGRESS (4) → RESOLVED (5) → CLOSED (6)

Lateral transitions (from any active status):
  Any → ON_HOLD (8) → back to prior status (via RESUME)
  NEW/TRIAGED → RETURNED (9) → NEW (1) [via RESUBMIT]
  TRIAGED → REJECTED (7) [via DH_REJECT or OD_REJECT]
```

### FULL_APPROVAL Step-by-Step

```
Step 1: REQUESTOR submits ticket
        → Status: NEW
        → Notification sent to MIS team

Step 2: PROGRAMMER or MIS_SUPERVISOR assesses ticket
        → Validates request details, sets target date/effort
        → Action logged: ASSESS
        → Status: NEW → TRIAGED

Step 3: DEPARTMENT_HEAD approves
        → Can only act if ASSESS action exists in workflow history
        → Action logged: DH_APPROVE (or DH_REJECT)
        → On rejection: Status → REJECTED (terminal)
        → On approval: Status stays TRIAGED, DH_APPROVE logged

Step 4: OD (Operations Director) approves
        → Can only act if DH_APPROVE exists in workflow history
        → Action logged: OD_APPROVE (or OD_REJECT)
        → On rejection: Status → REJECTED (terminal)
        → On approval: Status: TRIAGED → APPROVED

Step 5: MIS_SUPERVISOR assigns to programmer(s)
        → Can only assign when status = APPROVED
        → ASSIGNED_TO field populated (comma-separated emp_ids)
        → Action logged: ASSIGN
        → Status: APPROVED → IN_PROGRESS
        → Tasks auto-created for each assigned programmer

Step 6: Assigned PROGRAMMER resolves
        → Must be in ASSIGNED_TO list
        → Action logged: RESOLVE
        → Status: IN_PROGRESS → RESOLVED
        → Notification sent to original requestor

Step 7: REQUESTOR closes
        → Only the original requestor can close
        → Action logged: CLOSE
        → Status: RESOLVED → CLOSED
        → If linked to New System project: project status updated
```

### DH_APPROVAL_ONLY Step-by-Step (Adjustment)

```
Step 1: REQUESTOR submits → Status: NEW
Step 2: PROGRAMMER/MIS_SUPERVISOR assesses → Status: TRIAGED
Step 3: DEPARTMENT_HEAD approves → Status: APPROVED
        (No OD step)
Step 4: MIS_SUPERVISOR assigns → Status: IN_PROGRESS
Step 5: Assigned PROGRAMMER resolves → Status: RESOLVED
Step 6: REQUESTOR closes → Status: CLOSED
```

### DIRECT_ASSIGN Step-by-Step (Testing / Parallel Run)

```
Step 1: REQUESTOR submits → Status: NEW
Step 2: PROGRAMMER assigns testers directly
        → No assessment or approval required
        → ASSIGNED_TO populated with tester emp_ids
        → Records added to ticket_testers table
        → Status: NEW → IN_PROGRESS

Step 3: Each tester submits test result
        → Action logged: TEST (with result: PASSED or FAILED)
        → If FAILED:
            → Status: IN_PROGRESS → RETURNED
            → REQUESTOR must RESUBMIT → Status: RETURNED → NEW
            → Cycle repeats from Step 2
        → If all testers PASSED:
            → Status: IN_PROGRESS → RESOLVED

Step 4: REQUESTOR closes
        → Status: RESOLVED → CLOSED
```

### Return & Resubmit Flow

```
RETURN (can happen at NEW or TRIAGED status):
  Actor: PROGRAMMER or MIS_SUPERVISOR
  Action: RETURN logged, reason/remarks added
  Result: Status → RETURNED
  Next: REQUESTOR receives notification

RESUBMIT (only from RETURNED status):
  Actor: Original REQUESTOR
  Action: RESUBMIT logged, updated information provided
  Result: Status → NEW
  Next: Ticket re-enters workflow from the beginning
  Special case for Testing tickets: the "returned by" must be a tester
```

### On-Hold Flow

```
PUT ON HOLD (from any active status):
  Actor: PROGRAMMER or MIS_SUPERVISOR
  Action: PUT_ON_HOLD logged
  Result: Status → ON_HOLD
  Project linked: also set to ON_HOLD

RESUME:
  Actor: PROGRAMMER or MIS_SUPERVISOR
  Action: RESUMED logged
  Result: Status restored to previous active status
```

### Overdue / Auto-Hold

- Applies to Testing and Parallel Run tickets only
- Command: `php artisan ticket:check-due`
- If `target_date` has passed and ticket is still active → automatically transitions to ON_HOLD
- Linked project is also set to ON_HOLD
- Logged as an automated system action

---

## Project Lifecycle

### Project-Ticket Relationship

New System tickets **automatically create** a linked project when submitted. Other request types can be manually associated with an existing project.

```
New System ticket submitted
        │
        ▼
Auto-create project (ProjectService)
        │
        ▼
project_list record created:
  PROJ_NAME    = ticket's PROJECT_NAME
  PROJ_DEPT    = requestor's department
  PROJ_STATUS  = 1 (PLANNING)
  CREATED_BY   = requestor emp_id
        │
        │ Project status syncs with ticket lifecycle:
        │
        │   Ticket TRIAGED      → Project TRIAGED (2)
        │   Ticket IN_PROGRESS  → Project IN_PROGRESS (3)
        │   Ticket ON_HOLD      → Project ON_HOLD (4)
        │   All tickets CLOSED  → Project eligible for DEPLOYED (5)
        ▼
Project set to DEPLOYED manually once all linked tickets are CLOSED
```

### Project Status Reference

| ID  | Status          | Meaning                          |
| --- | --------------- | -------------------------------- |
| 1   | Planning        | Project created, not yet started |
| 2   | Triaged / Ready | Approved and ready to begin      |
| 3   | In Progress     | Active development               |
| 4   | On Hold         | Paused                           |
| 5   | Deployed        | Released to production           |
| 6   | Cancelled       | Cancelled                        |
| 7   | Inactive        | Dormant                          |

### Excel Import

Projects can be bulk-imported via Excel:

- Route: `POST /{app}/projects/import`
- Required columns: `PROJ_NAME`, `PROJ_DEPT`, `PROJ_STATUS`
- `PROJ_STATUS` accepts: text labels (e.g., "Planning", "In Progress") or numeric values (1–7)

---

## Task System

### Auto-Task Creation

When a ticket is assigned (`ASSIGN` action):

1. For each emp_id in `ASSIGNED_TO`
2. A `tasks` record is created with:
    - `SOURCE = 'TICKET'`
    - `STATUS = 1` (Pending)
    - `TICKET_ID` linked
    - `ASSIGNED_TO` = programmer's emp_id

### Task Status Flow

```
PENDING → IN_PROGRESS → COMPLETED
    │                       │
    └──── ON_HOLD ──────────┘
    └──── CANCELLED
```

### Task Notes & History

Each task supports:

- Notes/comments added by the assigned programmer
- Full history log of status changes

---

## Notification System

### What Triggers Notifications

| Event              | Who Gets Notified               |
| ------------------ | ------------------------------- |
| Ticket created     | MIS Supervisors and Programmers |
| Ticket assessed    | Requestor's Department Head     |
| Ticket DH approved | OD (if workflow requires)       |
| Ticket OD approved | MIS Supervisors                 |
| Ticket assigned    | Assigned programmer(s)          |
| Ticket resolved    | Original requestor              |
| Ticket closed      | Assigned programmer(s)          |
| Ticket returned    | Original requestor              |

### How It Works

- Real-time delivery via **Laravel Reverb** (WebSocket)
- Stored in `notifications` table and `notification_users` table
- Each user subscribes to private channel: `users.{emp_id}`
- Browser React app listens via Pusher JS client / Echo

---

## Database Schema Reference

### `tickets` Table (TMS_DB)

| Column            | Type     | Description                                                                          |
| ----------------- | -------- | ------------------------------------------------------------------------------------ |
| `ID`              | int      | Primary key                                                                          |
| `TICKET_ID`       | varchar  | Unique ticket number (e.g., TKT-2024-001)                                            |
| `EMPLOYID`        | varchar  | Requestor's emp_id                                                                   |
| `TYPE_OF_REQUEST` | int      | 1=New System, 2=Modification, 3=Enhancement, 4=Adjustment, 5=Testing, 6=Parallel Run |
| `PROJECT_NAME`    | varchar  | Associated project name                                                              |
| `STATUS`          | int      | Current status (1–9)                                                                 |
| `DETAILS`         | text     | Ticket description                                                                   |
| `ASSIGNED_TO`     | varchar  | Comma-separated emp_ids of assignees                                                 |
| `CREATED_AT`      | datetime | Submission time                                                                      |
| `CLOSED_AT`       | datetime | Closure time (nullable)                                                              |

### `ticket_workflow` Table (TMS_DB)

| Column        | Type     | Description                                                                                                                            |
| ------------- | -------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| `ID`          | int      | Primary key                                                                                                                            |
| `TICKET_ID`   | int      | FK to tickets                                                                                                                          |
| `ACTION_TYPE` | varchar  | ASSESS, DH_APPROVE, DH_REJECT, OD_APPROVE, OD_REJECT, ASSIGN, ACKNOWLEDGE, RESOLVE, CLOSE, RETURN, PUT_ON_HOLD, RESUME, RESUBMIT, TEST |
| `ACTION_BY`   | varchar  | emp_id of who performed action                                                                                                         |
| `OLD_STATUS`  | int      | Status before action                                                                                                                   |
| `NEW_STATUS`  | int      | Status after action                                                                                                                    |
| `ACTION_AT`   | datetime | When action occurred                                                                                                                   |

### `project_list` Table (PMS_DB)

| Column           | Type    | Description                                   |
| ---------------- | ------- | --------------------------------------------- |
| `PROJ_ID`        | int     | Primary key                                   |
| `PROJ_NAME`      | varchar | Project name                                  |
| `PROJ_DESC`      | text    | Description                                   |
| `PROJ_DEPT`      | varchar | Department                                    |
| `PROJ_STATUS`    | int     | Status (1–7)                                  |
| `ASSIGNED_PROGS` | varchar | Assigned programmers                          |
| `PROJ_HANDLER`   | varchar | Project handler emp_id (grants system access) |
| `DATE_START`     | date    | Start date                                    |
| `DATE_END`       | date    | Target end date                               |
| `CREATED_BY`     | varchar | Creator emp_id                                |

### `employee_masterlist` Table (MDB_DB)

| Column         | Type    | Description                                         |
| -------------- | ------- | --------------------------------------------------- |
| `EMPLOYID`     | varchar | Employee ID                                         |
| `EMPNAME`      | varchar | Full name                                           |
| `JOBTITLE`     | varchar | Job title (used for role detection)                 |
| `DEPT`         | varchar | Department (used for role detection)                |
| `APPROVER2`    | varchar | Department Head approver emp_id                     |
| `APPROVER3`    | varchar | Secondary approver emp_id                           |
| `emp_from`     | varchar | NULL for internal employees; non-null blocks access |
| `emp_position` | int     | Position level (>= 2 required for access)           |

---

## API Routes Reference

```
# Ticketing
GET    /{app}/tickets                - Ticket submission form
POST   /{app}/tickets                - Create ticket
GET    /{app}/tickets/datatable      - Paginated ticket list (role-filtered)
GET    /{app}/tickets/{ticket}       - View ticket details + available actions
POST   /{app}/{ticketId}/assess      - Assess (PROGRAMMER/MIS_SUPERVISOR)
POST   /{app}/{ticketId}/approve/dh  - DH approval (DEPARTMENT_HEAD)
POST   /{app}/{ticketId}/approve/od  - OD approval (OD)
POST   /{app}/{ticketId}/assign      - Assign to programmer(s)
POST   /{app}/{ticketId}/resolve     - Mark resolved (assigned PROGRAMMER)
POST   /{app}/{ticketId}/close       - Close ticket (REQUESTOR)
POST   /{app}/{ticketId}/return      - Return to requestor
POST   /{app}/{ticketId}/resubmit    - Resubmit after return (REQUESTOR)
POST   /{app}/{ticketId}/hold        - Put on hold
POST   /{app}/{ticketId}/resume      - Resume from hold

# Projects
GET    /{app}/projects               - Project list
GET    /{app}/projects/datatable     - Paginated project list
POST   /{app}/projects               - Create project
GET    /{app}/projects/{id}          - View project details
PATCH  /{app}/projects/{id}          - Update project
POST   /{app}/projects/import        - Excel bulk import

# Tasks (Programmer-only routes)
GET    /{app}/tasks                  - Task list (Programmer only)
POST   /{app}/tasks/store            - Create task
POST   /{app}/tasks/{taskId}/status  - Update task status
POST   /{app}/tasks/{taskId}/complete - Mark task complete
POST   /{app}/tasks/{taskId}/note    - Add note
POST   /{app}/tasks/{taskId}/history - View history

# Dashboard
GET    /{app}/dashboard              - Dashboard summary data
```

---

## Important Files

| File                                           | Purpose                                                    |
| ---------------------------------------------- | ---------------------------------------------------------- |
| `app/Services/TicketService.php`               | Core ticket logic, RBAC, workflow validation (1400+ lines) |
| `app/Repositories/TicketRepository.php`        | All ticket DB queries                                      |
| `app/Http/Middleware/AuthMiddleware.php`       | SSO auth, session setup, access control                    |
| `app/Http/Middleware/ProgrammerMiddleware.php` | Task route restriction                                     |
| `app/ValueObjects/WorkflowPath.php`            | Workflow step rules per request type                       |
| `app/Constants/TicketConstants.php`            | Status, request type, action constants                     |
| `app/Constants/ProjectConstants.php`           | Project status constants                                   |
| `app/Constants/TaskConstants.php`              | Task status and source constants                           |
| `config/database.php`                          | All 4 DB connection configurations                         |
| `routes/ticketing.php`                         | Ticket route definitions                                   |
| `routes/projects.php`                          | Project route definitions                                  |
| `routes/tasks.php`                             | Task route definitions (double-gated)                      |
| `resources/js/Pages/Ticketing/*.jsx`           | React UI components                                        |

---

## Common Issues & Troubleshooting

### Authentication Issues

**Problem:** Redirect loop / can't access the app

- Verify authify server is reachable: `ping 192.168.2.221`
- Check `authify_sessions` table has valid, non-expired tokens
- Clear session cache: `php artisan cache:clear`

**Problem:** Access denied after login

- User's `emp_from` must be `NULL` in masterlist
- Job title must contain "programmer" or "MIS Senior Supervisor"
- OR `emp_position >= 2`
- OR user's emp_id must be in `project_list.PROJ_HANDLER`

### Database Connection Issues

**Problem:** "Connection refused" or "SQLSTATE" errors

- Confirm all 4 MySQL servers are running and reachable
- Verify correct host/port/credentials in `.env`
- Test connection via Tinker:
    ```bash
    php artisan tinker
    >>> DB::connection('projects')->getPdo();
    >>> DB::connection('task')->getPdo();
    >>> DB::connection('masterlist')->getPdo();
    >>> DB::connection('authify')->getPdo();
    ```

### WebSocket / Real-time Notification Issues

**Problem:** Notifications not appearing in browser

- SSL certificate must be valid (Reverb requires HTTPS in production)
- Confirm Reverb environment variables are set in `.env`
- Start Reverb server: `php artisan reverb:start`
- Open browser console and check for WebSocket connection errors
- Test endpoint: `https://your-domain/reverb-test`

### Ticket Action Buttons Not Showing

**Problem:** Expected action buttons missing on ticket view

- The system computes available actions per-ticket per-user via `determineAvailableActions()` in `TicketService`
- Check the user's role is being correctly detected (see `getUserRoles()`)
- Verify the ticket's current status allows the action
- Verify the workflow history has the required predecessor actions (e.g., ASSESS before DH_APPROVE)
- Check `WorkflowPath.php` for the request type's required steps

### Excel Import Issues

**Problem:** Import fails or data not saving

- Required columns: `PROJ_NAME`, `PROJ_DEPT`, `PROJ_STATUS`
- `PROJ_STATUS` must be text (e.g., "Planning", "In Progress") or numeric (1–7)
- Check file encoding (UTF-8 recommended)

### Action Authorization Fails Unexpectedly

**Problem:** A user should be able to perform an action but the button is hidden / request is rejected

- Trace through the validation chain in `TicketService`:
    - `canAssess()` — must not be requestor, status must be NEW, workflow must require assessment
    - `canDHApprove()` — user must be DH, ASSESS action must already be in `ticket_workflow`
    - `canODApprove()` — user must be OD, DH_APPROVE must already be in `ticket_workflow`
    - `canAssign()` — for full workflows, user must be MIS_SUPERVISOR and status must be APPROVED
    - `canResolve()` — user's emp_id must be in ticket's `ASSIGNED_TO` field
    - `canClose()` — user's emp_id must match ticket's `EMPLOYID` (requestor)

---

## Maintenance Checklist

### Daily

- Monitor error logs: `storage/logs/laravel.log`
- Check notification delivery in browser

### Weekly

- Review ticket backlog for stalled items
- Run overdue check: `php artisan ticket:check-due`

### Monthly

- Database backup (all 4 MySQL databases)
- Clear old cache files: `php artisan cache:clear`
- Review SSL certificate expiry (required for Reverb)

### Ongoing

- Monitor MySQL connection pool usage
- Check disk space (attachments storage)
- Review application error logs for recurring issues

---

## Commands Reference

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan clear-compiled

# Database operations
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed

# Scheduled tasks
php artisan ticket:check-due

# Testing
php artisan test

# List all routes
php artisan route:list

# Interactive console
php artisan tinker

# WebSocket server
php artisan reverb:start
```

---

## System Notes

1. **No Local Registration** — Users cannot self-register. Access is controlled entirely by the Authify SSO system and employee masterlist data.
2. **4 Databases Required** — The system will fail to function if any of the 4 MySQL connections are unavailable.
3. **HTTPS Required** — Laravel Reverb WebSocket requires SSL/TLS to function in production.
4. **Role Detection is Job-Title Based** — Roles are resolved at runtime from the `emp_jobtitle` and `emp_dept` fields. Changes to an employee's job title in the HR/masterlist system will immediately affect their permissions.
5. **Auto Project Creation** — Submitting a "New System" request type automatically creates a linked project record.
6. **Workflow History is Authoritative** — The system checks `ticket_workflow` history (not just current status) to determine what actions have been taken and what actions are next. Manually altering workflow records will break the action authorization logic.
7. **DEPARTMENT_HEAD role requires a DB lookup** — Unlike other roles, the Department Head role is determined by querying the `employee_masterlist` table (checking APPROVER2/APPROVER3 columns), not purely from session data.

---

## Contacts

| Role                | Responsibility                            |
| ------------------- | ----------------------------------------- |
| System Admin        | Server, database, SSL certificates        |
| Lead Developer      | Application logic, bug fixes, deployments |
| MIS Department      | Ticket assessment, assignment             |
| Operations Director | Final ticket approvals                    |

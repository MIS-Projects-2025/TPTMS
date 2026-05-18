# TPTMS - Technical Project & Task Management System

## Architecture Documentation

---

### 1. System Overview

TPTMS (Technical Project & Task Management System) is a web-based application built with **Laravel 12** and **React/Inertia.js** that manages technical projects, tasks, and support tickets. It integrates with multiple databases and provides real-time notifications via WebSocket.

**Core Capabilities:**

- Multi-level approval workflow for IT support tickets
- Software project lifecycle tracking
- Programmer task management
- Real-time notifications (WebSocket via Reverb/Pusher)
- SSO authentication via Authify

---

### 2. Technology Stack

| Layer            | Technology                              |
| ---------------- | --------------------------------------- |
| Backend          | Laravel 12, PHP 8.2+                    |
| Frontend         | React 18 + Inertia.js                   |
| Styling          | Tailwind CSS                            |
| Database         | MySQL (4 databases) + SQLite (internal) |
| Real-time        | Laravel Reverb + Pusher protocol        |
| Excel Processing | PhpSpreadsheet                          |
| Authentication   | Custom SSO (Authify)                    |

---

### 3. Database Architecture

The system uses **4 MySQL database connections** plus SQLite for Laravel internals:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           APPLICATION LAYER                             │
│                         (Laravel 12 + Inertia.js)                       │
└──────────────────────────────┬──────────────────────────────────────────┘
                               │
        ┌──────────────────────┼──────────────────────┬────────────────────┐
        ▼                      ▼                      ▼                    ▼
┌──────────────┐     ┌─────────────────┐    ┌───────────────┐   ┌─────────────────┐
│  projects    │     │      task       │    │  masterlist   │   │    authify      │
│  (PMS_DB)   │     │   (TMS_DB)      │    │   (MDB_DB)    │   │    (ADB)        │
│              │     │                 │    │               │   │                 │
│ project_list │     │ tickets         │    │ employee_     │   │ authify_        │
│ project_logs │     │ ticket_workflow │    │ masterlist    │   │ sessions        │
│              │     │ ticket_remarks  │    │               │   │                 │
│              │     │ ticket_         │    │ (Approver2,   │   │ (SSO tokens)    │
│              │     │  attachments    │    │  Approver3    │   │                 │
│              │     │ ticket_testers  │    │  lookups)     │   │                 │
│              │     │ tasks           │    │               │   │                 │
└──────────────┘     └─────────────────┘    └───────────────┘   └─────────────────┘

                    ┌──────────────────────────────────┐
                    │         sqlite (internal)         │
                    │  Cache, Jobs, Sessions, Logs      │
                    └──────────────────────────────────┘
```

| Connection Key | DB Name | Purpose                   | Key Tables                                                                                      |
| -------------- | ------- | ------------------------- | ----------------------------------------------------------------------------------------------- |
| `projects`     | PMS_DB  | Project management        | `project_list`, `project_logs`                                                                  |
| `task`         | TMS_DB  | Tickets & tasks           | `tickets`, `ticket_workflow`, `ticket_remarks`, `ticket_attachments`, `ticket_testers`, `tasks` |
| `masterlist`   | MDB_DB  | Employee data & approvers | `employee_masterlist`                                                                           |
| `authify`      | ADB     | SSO session management    | `authify_sessions`                                                                              |
| `sqlite`       | (local) | Laravel internals         | Cache, jobs, compiled views                                                                     |

---

### 4. Application Architecture

#### 4.1 MVC + Service + Repository Pattern

```
┌─────────────────────────────────────────────────────────────────┐
│                      HTTP LAYER                                  │
│   Routes (ticketing.php, projects.php, tasks.php, web.php)      │
└────────────────────────────┬────────────────────────────────────┘
                             │  AuthMiddleware / ProgrammerMiddleware
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                      CONTROLLERS                                 │
│  TicketingController, ProjectController, TaskController         │
│  AuthenticationController, DashboardController                  │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                       SERVICES                                   │
│  TicketService (1400+ lines) — core ticket logic & RBAC         │
│  ProjectService — project lifecycle management                  │
│  TaskService — task creation and tracking                       │
│  NotificationService — real-time event dispatch                 │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                      REPOSITORIES                                │
│  TicketRepository — DB queries for tickets                      │
│  ProjectRepository — DB queries for projects                    │
│  TaskRepository — DB queries for tasks                          │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────────┐
│                VALUE OBJECTS / CONSTANTS                         │
│  WorkflowPath — per-request-type workflow rules                 │
│  TicketConstants, ProjectConstants, TaskConstants               │
└─────────────────────────────────────────────────────────────────┘
```

#### 4.2 Directory Structure

```
app/
├── Constants/
│   ├── TicketConstants.php       # Statuses, request types, workflow action labels
│   ├── ProjectConstants.php      # Project status constants
│   └── TaskConstants.php         # Task status and source constants
├── Http/
│   ├── Controllers/
│   │   ├── TicketingController.php
│   │   ├── ProjectController.php
│   │   ├── TaskController.php
│   │   ├── AuthenticationController.php
│   │   └── DashboardController.php
│   └── Middleware/
│       ├── AuthMiddleware.php     # SSO token validation + session setup
│       ├── ProgrammerMiddleware.php # Restricts task routes to Programmers only
│       ├── CorsMiddleware.php
│       └── HandleInertiaRequests.php
├── Models/
│   ├── User.php                   # employee_masterlist connection
│   └── NotificationUser.php
├── Repositories/
│   ├── TicketRepository.php
│   ├── ProjectRepository.php
│   └── TaskRepository.php
├── Services/
│   ├── TicketService.php          # Core ticket business logic, RBAC, workflow
│   ├── ProjectService.php         # Project lifecycle
│   ├── TaskService.php            # Task management
│   └── NotificationService.php   # Real-time push notifications
├── ValueObjects/
│   └── WorkflowPath.php           # Per-request-type workflow rules & step matrix
└── Notifications/
    ├── TicketCreatedNotification.php
    ├── TicketResolvedNotification.php
    ├── TicketClosedNotification.php
    └── ProjectStatusChangedNotification.php

routes/
├── web.php           # Root + auth routes
├── ticketing.php     # All ticket endpoints (AuthMiddleware)
├── projects.php      # All project endpoints (AuthMiddleware)
└── tasks.php         # All task endpoints (AuthMiddleware + ProgrammerMiddleware)
```

---

### 5. Authentication & Session Flow

```
┌──────────┐    ┌──────────────────────────────────────┐    ┌──────────────┐
│  Browser │    │       AuthMiddleware.php              │    │   Authify    │
│          │    │                                       │    │  SSO Server  │
│          │    │  Token resolution (priority order):   │    │ :8200        │
│  Request │───▶│  1. ?key= query parameter             │    │              │
│          │    │  2. SSO Cookie                        │───▶│ Validates    │
│          │    │  3. Session token                     │    │ token in     │
│          │    │                                       │◀───│ authify_     │
│          │    │  Access control checks:               │    │ sessions     │
│          │    │  ① emp_from must be NULL              │    └──────────────┘
│          │    │  ② emp_position >= 2                  │
│          │    │     OR jobtitle contains "programmer" │
│          │    │     OR jobtitle has "MIS Senior       │
│          │    │        Supervisor"                    │
│          │    │     OR user is a project handler      │
│          │    │        (exists in project_list.       │
│          │    │         PROJ_HANDLER)                 │
│          │    │                                       │
│          │    │  Session populated with emp_data:     │
│          │    │  { token, emp_id, emp_name,           │
│          │    │    emp_firstname, emp_jobtitle,        │
│          │    │    emp_dept, emp_prodline,             │
│          │    │    emp_station, emp_position,          │
│          │    │    emp_system_role, generated_at }     │
└──────────┘    └──────────────────────────────────────┘
```

**emp_system_role assignment:**

- Set to `"Programmer"` if job title contains `"programmer"` OR `"MIS Senior Supervisor"`
- Used by `ProgrammerMiddleware` to gate task-only routes

---

### 6. Role-Based Access Control (RBAC)

#### 6.1 Role Definitions

Roles are **dynamically resolved** at runtime per request from session data. A user can hold multiple roles simultaneously (e.g., a Programmer who is also a Department Head).

> Source: `TicketService.php::getUserRoles()` (Lines 996–1053) and `isDepartmentHead()` masterlist query

| Role                | Detection Criteria                                                                       | Primary Capabilities                                                                                  |
| ------------------- | ---------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| **PROGRAMMER**      | `emp_dept = 'MIS'` AND job title contains `"programmer"` OR (`"mis"` AND `"supervisor"`) | Assess tickets, assign Testing/Parallel Run tickets directly, resolve tickets, full ticket visibility |
| **MIS_SUPERVISOR**  | `emp_dept = 'MIS'` AND job title contains `"supervisor"`                                 | Assign tickets after approvals are complete, assess tickets, full ticket visibility                   |
| **DEPARTMENT_HEAD** | Exists in `employee_masterlist` as `APPROVER2` or `APPROVER3` (DB lookup)                | Approve tickets at TRIAGED status (DH approval step)                                                  |
| **OD**              | `emp_dept = 'OPERATIONS'` OR job title = `'OPERATIONS DIRECTOR'` (case-insensitive)      | Final approval after DH approval, full ticket visibility                                              |
| **REQUESTOR**       | Anyone not matching above roles                                                          | Create tickets, resubmit returned tickets, close resolved tickets (own tickets only)                  |

#### 6.2 Route-Level Middleware Gates

```
All routes ──▶ AuthMiddleware (SSO token validation)
                    │
                    ├──▶ /tickets/*   → TicketingController
                    ├──▶ /projects/*  → ProjectController
                    └──▶ /tasks/*     → ProgrammerMiddleware ──▶ TaskController
                                        (emp_system_role === 'Programmer' required)
```

#### 6.3 Action Authorization Matrix

The `determineAvailableActions()` method in `TicketService` (Lines 778–948) evaluates which actions the current user can take on a given ticket. This is computed per-ticket and sent to the frontend.

**General Workflow Tickets (New System / Modification / Enhancement / Adjustment):**

| Action        | Who Can Perform                                    | Conditions                                                                                         |
| ------------- | -------------------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `ASSESS`      | PROGRAMMER, MIS_SUPERVISOR                         | Status = NEW; user is not the requestor; no prior RETURN action; workflow requires assessment      |
| `DH_APPROVE`  | DEPARTMENT_HEAD                                    | Status = TRIAGED; ASSESS action exists in history; no prior DH_APPROVE                             |
| `DH_REJECT`   | DEPARTMENT_HEAD                                    | Status = TRIAGED; same conditions as DH_APPROVE                                                    |
| `OD_APPROVE`  | OD                                                 | Status = TRIAGED; DH_APPROVE exists in history; no prior OD_APPROVE; workflow requires OD approval |
| `OD_REJECT`   | OD                                                 | Status = TRIAGED; same conditions as OD_APPROVE                                                    |
| `ASSIGN`      | MIS_SUPERVISOR (full/DH-only), PROGRAMMER (direct) | Status = APPROVED; all required approvals complete                                                 |
| `RESOLVE`     | Assigned PROGRAMMER                                | Status = IN_PROGRESS; user must be in ASSIGNED_TO list                                             |
| `CLOSE`       | REQUESTOR (original)                               | Status = RESOLVED; user is the ticket creator                                                      |
| `RETURN`      | PROGRAMMER, MIS_SUPERVISOR                         | Status = NEW or TRIAGED                                                                            |
| `RESUBMIT`    | REQUESTOR (original)                               | Status = RETURNED; user is the ticket creator                                                      |
| `PUT_ON_HOLD` | PROGRAMMER, MIS_SUPERVISOR                         | Various statuses                                                                                   |
| `RESUME`      | PROGRAMMER, MIS_SUPERVISOR                         | Status = ON_HOLD                                                                                   |

**Testing / Parallel Run Tickets:**

| Action                 | Who Can Perform | Conditions                                                           |
| ---------------------- | --------------- | -------------------------------------------------------------------- |
| `ASSIGN`               | PROGRAMMER      | Status = NEW; no approvals needed                                    |
| `TEST` (PASSED/FAILED) | Assigned Tester | Status = NEW, TRIAGED, IN_PROGRESS, or RESOLVED; user in tester list |
| `RESUBMIT`             | REQUESTOR       | Status = RETURNED; returned by a tester                              |
| `CLOSE`                | REQUESTOR       | All testers submitted PASSED results                                 |

#### 6.4 Ticket Visibility Rules

`applyRoleVisibility()` in `TicketService` (Lines 1316–1337) filters the datatable query:

| Role            | Visible Tickets                                                                                     |
| --------------- | --------------------------------------------------------------------------------------------------- |
| PROGRAMMER      | All tickets                                                                                         |
| MIS_SUPERVISOR  | All tickets                                                                                         |
| OD              | All tickets                                                                                         |
| DEPARTMENT_HEAD | Only tickets where the user is listed as APPROVER2 or APPROVER3 in the requestor's masterlist entry |
| REQUESTOR       | Own tickets (created) + tickets assigned to them + tickets they are listed as a tester for          |

---

### 7. Ticket Workflow System

#### 7.1 Ticket Status States

| ID  | Constant             | Label       | Description                                               |
| --- | -------------------- | ----------- | --------------------------------------------------------- |
| 1   | `STATUS_NEW`         | New         | Newly submitted; awaiting assessment or direct assignment |
| 2   | `STATUS_TRIAGED`     | Triaged     | Assessed by programmer; awaiting approval(s)              |
| 3   | `STATUS_APPROVED`    | Approved    | All required approvals received; ready for assignment     |
| 4   | `STATUS_IN_PROGRESS` | In Progress | Assigned to programmer(s); work underway                  |
| 5   | `STATUS_RESOLVED`    | Resolved    | Work complete; awaiting requestor verification            |
| 6   | `STATUS_CLOSED`      | Closed      | Verified and closed by requestor                          |
| 7   | `STATUS_REJECTED`    | Rejected    | Rejected during DH or OD approval                         |
| 8   | `STATUS_ON_HOLD`     | On Hold     | Temporarily paused                                        |
| 9   | `STATUS_RETURNED`    | Returned    | Sent back to requestor for clarification or resubmission  |

#### 7.2 Request Types & Workflow Paths

| ID  | Request Type | Workflow Type      | Steps                                                       |
| --- | ------------ | ------------------ | ----------------------------------------------------------- |
| 1   | New System   | `FULL_APPROVAL`    | Assess → DH Approve → OD Approve → Assign → Resolve → Close |
| 2   | Modification | `FULL_APPROVAL`    | Assess → DH Approve → OD Approve → Assign → Resolve → Close |
| 3   | Enhancement  | `FULL_APPROVAL`    | Assess → DH Approve → OD Approve → Assign → Resolve → Close |
| 4   | Adjustment   | `DH_APPROVAL_ONLY` | Assess → DH Approve → Assign → Resolve → Close              |
| 5   | Testing      | `DIRECT_ASSIGN`    | Assign → Test (PASSED/FAILED) → Close                       |
| 6   | Parallel Run | `DIRECT_ASSIGN`    | Assign → Test (PASSED/FAILED) → Close                       |

> Defined in `WorkflowPath.php` (Lines 15–64) as a value object per request type.

#### 7.3 Full Ticket Lifecycle Diagrams

**FULL_APPROVAL (New System, Modification, Enhancement):**

```
REQUESTOR submits ticket
         │
         ▼
    ┌─────────┐
    │   NEW   │ ◀─────────────────────────────────────────────┐
    └────┬────┘                                               │
         │                                                    │
         │ PROGRAMMER / MIS_SUPERVISOR → ASSESS               │
         ▼                                                    │
    ┌──────────┐                                              │
    │ TRIAGED  │──── RETURN ────────────────────────────────▶ │
    └────┬─────┘ (by PROGRAMMER/MIS_SUPERVISOR)         ┌────┴────────┐
         │                                              │  RETURNED   │
         │ DEPARTMENT_HEAD → DH_APPROVE                 │  (status 9) │
         │                                              └─────────────┘
         │         ┌──────────┐                               │
         │         │ REJECTED │ ◀── DH_REJECT                 │ REQUESTOR → RESUBMIT
         │         └──────────┘                               │
         ▼                                                    │
    (still TRIAGED, DH_APPROVE logged)                        │
         │                                                    │
         │ OD → OD_APPROVE                                    │
         │                                                    │
         │         ┌──────────┐                               │
         │         │ REJECTED │ ◀── OD_REJECT                 │
         │         └──────────┘                               │
         ▼                                                    │
    ┌──────────┐                                              │
    │ APPROVED │                                              │
    └────┬─────┘                                              │
         │                                                    │
         │ MIS_SUPERVISOR → ASSIGN (to programmer(s))         │
         ▼                                                    │
    ┌─────────────┐                                           │
    │ IN_PROGRESS │                                           │
    └──────┬──────┘                                           │
           │                                                  │
           │ Assigned PROGRAMMER → RESOLVE                    │
           ▼                                                  │
      ┌──────────┐                                            │
      │ RESOLVED │ ──── RETURN ───────────────────────────────┘
      └──────┬───┘ (by PROGRAMMER/MIS_SUPERVISOR if issue found)
             │
             │ REQUESTOR (original) → CLOSE
             ▼
        ┌────────┐
        │ CLOSED │
        └────────┘
```

**DH_APPROVAL_ONLY (Adjustment):**

```
REQUESTOR submits → NEW
         │
         │ PROGRAMMER/MIS_SUPERVISOR → ASSESS
         ▼
    TRIAGED
         │
         │ DEPARTMENT_HEAD → DH_APPROVE
         ▼
    APPROVED
         │
         │ MIS_SUPERVISOR → ASSIGN
         ▼
    IN_PROGRESS → PROGRAMMER resolves → RESOLVED → REQUESTOR closes → CLOSED
```

**DIRECT_ASSIGN (Testing / Parallel Run):**

```
REQUESTOR submits → NEW
         │
         │ PROGRAMMER → ASSIGN (to testers)
         ▼
    IN_PROGRESS
         │
         │ Each assigned TESTER → TEST (PASSED or FAILED)
         ▼
    ┌────────────────────────────────────────────┐
    │  All testers PASSED?                        │
    │                                            │
    │  YES → RESOLVED → REQUESTOR closes → CLOSED│
    │                                            │
    │  NO  → RETURNED → REQUESTOR resubmits      │
    │         → NEW → cycle repeats              │
    └────────────────────────────────────────────┘
```

**ON_HOLD flow (applicable to any status):**

```
Any active ticket
      │
      │ PROGRAMMER/MIS_SUPERVISOR → PUT_ON_HOLD
      ▼
  ON_HOLD
      │
      │ PROGRAMMER/MIS_SUPERVISOR → RESUME
      ▼
  Restored to previous active status
```

#### 7.4 Workflow Action Log

Every action is recorded in `ticket_workflow` table:

| Column        | Description                                                                                                                                    |
| ------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `TICKET_ID`   | FK to tickets                                                                                                                                  |
| `ACTION_TYPE` | One of: ASSESS, DH_APPROVE, DH_REJECT, OD_APPROVE, OD_REJECT, ASSIGN, ACKNOWLEDGE, RESOLVE, CLOSE, RETURN, PUT_ON_HOLD, RESUME, RESUBMIT, TEST |
| `ACTION_BY`   | emp_id of who performed the action                                                                                                             |
| `OLD_STATUS`  | Numeric status before action                                                                                                                   |
| `NEW_STATUS`  | Numeric status after action                                                                                                                    |
| `ACTION_AT`   | Timestamp                                                                                                                                      |

---

### 8. Project Module

#### 8.1 Project Statuses

| ID  | Constant                  | Label           |
| --- | ------------------------- | --------------- |
| 1   | `PROJ_STATUS_PLANNING`    | Planning        |
| 2   | `PROJ_STATUS_TRIAGED`     | Triaged / Ready |
| 3   | `PROJ_STATUS_IN_PROGRESS` | In Progress     |
| 4   | `PROJ_STATUS_ON_HOLD`     | On Hold         |
| 5   | `PROJ_STATUS_DEPLOYED`    | Deployed        |
| 6   | `PROJ_STATUS_CANCELLED`   | Cancelled       |
| 7   | `PROJ_STATUS_INACTIVE`    | Inactive        |

#### 8.2 Project-Ticket Relationship

```
New System ticket submitted
         │
         │ Auto-create project (ProjectService)
         ▼
    Project (PLANNING status)
         │
         │ Ticket status transitions sync project status:
         │   Ticket TRIAGED      → Project TRIAGED
         │   Ticket IN_PROGRESS  → Project IN_PROGRESS
         │   Ticket ON_HOLD      → Project ON_HOLD
         │   All tickets CLOSED  → Project can be set DEPLOYED
         ▼
    Project lifecycle managed manually
    (cannot deploy until all linked tickets are CLOSED)
```

#### 8.3 Project RBAC

| Action             | Who Can Perform                                |
| ------------------ | ---------------------------------------------- |
| View projects list | All authenticated users                        |
| Create project     | AuthMiddleware passes (any authenticated user) |
| Update project     | AuthMiddleware passes (any authenticated user) |
| Import via Excel   | AuthMiddleware passes (any authenticated user) |
| Deploy project     | Only when all linked tickets are CLOSED        |

---

### 9. Task Module

#### 9.1 Task Statuses

| ID  | Constant             | Label       |
| --- | -------------------- | ----------- |
| 1   | `STATUS_PENDING`     | Pending     |
| 2   | `STATUS_IN_PROGRESS` | In Progress |
| 3   | `STATUS_COMPLETED`   | Completed   |
| 4   | `STATUS_ON_HOLD`     | On Hold     |
| 5   | `STATUS_CANCELLED`   | Cancelled   |

#### 9.2 Task Sources

| Constant         | Value       | Description                            |
| ---------------- | ----------- | -------------------------------------- |
| `SOURCE_TICKET`  | `'TICKET'`  | Auto-created when a ticket is assigned |
| `SOURCE_PROJECT` | `'PROJECT'` | Created from a project                 |
| `SOURCE_MANUAL`  | `'MANUAL'`  | Manually created by a programmer       |

#### 9.3 Task RBAC

All task routes are protected by **both** `AuthMiddleware` AND `ProgrammerMiddleware`.

- `ProgrammerMiddleware` checks `session('emp_data.emp_system_role') === 'Programmer'`
- Only MIS Programmers and MIS Senior Supervisors can access task routes

| Route                       | Access          |
| --------------------------- | --------------- |
| `GET /tasks`                | Programmer only |
| `POST /tasks/store`         | Programmer only |
| `POST /tasks/{id}/status`   | Programmer only |
| `POST /tasks/{id}/complete` | Programmer only |
| `POST /tasks/{id}/note`     | Programmer only |
| `POST /tasks/{id}/history`  | Programmer only |

---

### 10. Notification System

#### 10.1 Architecture

```
Action performed (e.g., ticket assigned)
         │
         ▼
NotificationService::dispatch()
         │
         │ Queued Laravel Notification
         ▼
Laravel Reverb (WebSocket Server)
         │
         │ Broadcasts on private channel: users.{emp_id}
         ▼
Browser WebSocket (Echo/Pusher client)
         │
         ▼
React UI — notification bell updates in real-time
```

#### 10.2 Notification Events

| Notification Class                  | Trigger                 |
| ----------------------------------- | ----------------------- |
| `TicketCreatedNotification`         | New ticket submitted    |
| `TicketResolvedNotification`        | Ticket marked resolved  |
| `TicketClosedNotification`          | Ticket closed           |
| `ProjectStatusChangedNotification`  | Project status updated  |
| Ticket assigned, returned, approved | Via NotificationService |

#### 10.3 WebSocket Configuration

- **Channel format**: `users.{emp_id}` (private channel per user)
- **Protocol**: Pusher-compatible (via Laravel Reverb)
- **Requirement**: HTTPS / SSL required for production WebSocket connections

---

### 11. Key File Reference

| File                                           | Lines | Purpose                                                 |
| ---------------------------------------------- | ----- | ------------------------------------------------------- |
| `app/Services/TicketService.php`               | 1400+ | Core ticket logic, RBAC, workflow, action authorization |
| `app/Http/Middleware/AuthMiddleware.php`       | 284   | SSO authentication, session population                  |
| `app/Http/Middleware/ProgrammerMiddleware.php` | 26    | Task route restriction to Programmers                   |
| `app/ValueObjects/WorkflowPath.php`            | ~130  | Per-request-type workflow rules                         |
| `app/Constants/TicketConstants.php`            | ~50   | All ticket status and action constants                  |
| `app/Repositories/TicketRepository.php`        | —     | Ticket DB queries                                       |
| `app/Http/Controllers/TicketingController.php` | —     | HTTP endpoints                                          |
| `config/database.php`                          | —     | All 4 DB connection configs                             |
| `routes/ticketing.php`                         | —     | Ticket route definitions                                |
| `routes/projects.php`                          | —     | Project route definitions                               |
| `routes/tasks.php`                             | —     | Task route definitions (double-gated)                   |

---

### 12. Configuration Files

| File                      | Purpose                           |
| ------------------------- | --------------------------------- |
| `.env`                    | All environment variables         |
| `config/database.php`     | 5 database connection definitions |
| `config/reverb.php`       | WebSocket server configuration    |
| `config/broadcasting.php` | Pusher/Reverb broadcast settings  |
| `config/cors.php`         | Cross-origin request settings     |

#### 12.1 Key Environment Variables

```env
# App
APP_NAME=tptms
APP_URL=https://tptms.local

# Database Connections
DB_CONNECTION=sqlite

PMS_HOST=192.168.x.x
PMS_PORT=3306
PMS_DATABASE=pms
PMS_USERNAME=root
PMS_PASSWORD=

TMS_HOST=192.168.x.x
TMS_PORT=3306
TMS_DATABASE=tms
TMS_USERNAME=root
TMS_PASSWORD=

MDB_HOST=192.168.x.x
MDB_PORT=3306
MDB_DATABASE=masterlist
MDB_USERNAME=root
MDB_PASSWORD=

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

# Pusher (fallback)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_FORCE_TLS=true
```

---

### 13. Deployment Notes

1. **HTTPS Required** — SSL certificates mandatory for Reverb/WebSocket to function
2. **4 MySQL Databases** — All must be accessible and have correct credentials
3. **SSO Dependency** — Authify server at `192.168.2.221:8200` must be reachable
4. **Queue Workers** — Required for notification delivery in production
5. **Scheduled Tasks** — `php artisan ticket:check-due` for overdue ticket processing

---

### 14. Development Commands

```bash
# Install dependencies
composer install
npm install

# Development server (all-in-one)
composer run dev

# Individual servers
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start
npm run dev

# Production build
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Cache clearing
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Database
php artisan migrate
php artisan db:seed

# Check overdue tickets
php artisan ticket:check-due

# Testing
php artisan test
```

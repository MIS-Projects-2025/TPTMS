# TPTMS - Technical Project & Task Management System

## Architecture Documentation

### 1. System Overview

TPTMS is a web-based application built with Laravel 12 and React/Inertia.js that manages technical projects, tasks, and support tickets. It integrates with multiple databases and provides real-time notifications.

### 2. Technology Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | React 18 + Inertia.js |
| Styling | Tailwind CSS |
| Database | MySQL (4 databases) + SQLite (local) |
| Real-time | Laravel Reverb + Pusher |
| Excel Processing | PhpSpreadsheet |
| Authentication | Custom SSO (authify) |

### 3. Database Architecture

The system uses **4 MySQL database connections**:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           APPLICATION LAYER                             │
│                         (Laravel + Inertia)                             │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
        ┌───────────────────────────┼───────────────────────────┐
        ▼                           ▼                           ▼
┌───────────────┐          ┌───────────────┐          ┌───────────────┐
│   projects    │          │     task      │          │  masterlist   │
│   (PMS_DB)    │          │   (TMS_DB)    │          │   (MDB_DB)    │
│               │          │               │          │               │
│ - project_list│          │ - tickets     │          │ - employee_   │
│ - project_logs│          │ - ticket_work │          │   masterlist  │
│               │          │ - tasks       │          │               │
└───────────────┘          └───────────────┘          └───────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         authify (ADB)                                   │
│              authify_sessions (SSO token management)                   │
└─────────────────────────────────────────────────────────────────────────┘
```

### 4. Application Architecture

#### 4.1 MVC Pattern with Service Layer

```
┌─────────────────────────────────────────────────────────────────┐
│                      CONTROLLERS                                 │
│  TicketingController, ProjectController, TaskController         │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                       SERVICES                                   │
│  TicketService, ProjectService, TaskService, NotificationService│
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                      REPOSITORIES                                │
│  TicketRepository, ProjectRepository, TaskRepository            │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                       MODELS                                     │
│  User (masterlist), NotificationUser                            │
└─────────────────────────────────────────────────────────────────┘
```

#### 4.2 Directory Structure

```
app/
├── Constants/
│   ├── TicketConstants.php      # Status, request types, workflow actions
│   ├── ProjectConstants.php     # Project statuses, request types
│   └── TaskConstants.php
├── Http/
│   ├── Controllers/
│   │   ├── TicketingController.php
│   │   ├── ProjectController.php
│   │   ├── TaskController.php
│   │   ├── AuthenticationController.php
│   │   └── DashboardController.php
│   └── Middleware/
│       ├── AuthMiddleware.php   # SSO authentication
│       ├── CorsMiddleware.php
│       ├── ProgrammerMiddleware.php
│       └── HandleInertiaRequests.php
├── Models/
│   ├── User.php                 # Employee masterlist connection
│   └── NotificationUser.php
├── Repositories/
│   ├── TicketRepository.php
│   ├── ProjectRepository.php
│   └── TaskRepository.php
├── Services/
│   ├── TicketService.php        # Core ticket business logic
│   ├── ProjectService.php       # Project management
│   ├── TaskService.php
│   └── NotificationService.php  # Real-time notifications
├── ValueObjects/
│   └── WorkflowPath.php         # Request type workflow definitions
└── Notifications/
    ├── TicketCreatedNotification.php
    ├── TicketResolvedNotification.php
    ├── TicketClosedNotification.php
    └── ProjectStatusChangedNotification.php
```

### 5. Core Modules

#### 5.1 Ticketing Module

**Ticket Workflow States:**
| ID | Status | Description |
|----|--------|-------------|
| 1 | NEW | Newly submitted ticket |
| 2 | TRIAGED | Assessed and ready for approval |
| 3 | APPROVED | Approved and ready for assignment |
| 4 | IN_PROGRESS | Work is being done |
| 5 | RESOLVED | Work completed, awaiting verification |
| 6 | CLOSED | Verified and closed |
| 7 | REJECTED | Rejected |
| 8 | ON_HOLD | Temporarily paused |
| 9 | RETURNED | Returned to requestor for clarification |

**Request Types:**
| ID | Type | Workflow Path |
|----|------|---------------|
| 1 | New System | Assessment → DH Approval → OD Approval → Assign → Resolve → Close |
| 2 | Modification | Assessment → DH Approval → OD Approval → Assign → Resolve → Close |
| 3 | Enhancement | Assessment → DH Approval → OD Approval → Assign → Resolve → Close |
| 4 | Adjustment | Direct DH Approval → Assign → Resolve → Close |
| 5 | Testing | Direct Assignment → Resolve → Close |
| 6 | Parallel Run | Direct Assignment → Resolve → Close |

**User Roles:**
- `PROGRAMMER` - MIS department programmers
- `MIS_SUPERVISOR` - MIS supervisors
- `DEPARTMENT_HEAD` - Department heads (can approve tickets)
- `OD` - Operations Director (can give final approval)
- `REQUESTOR` - Regular users who submit tickets

**Key Endpoints:**
```
POST   /tickets                    # Create new ticket
GET    /tickets                    # Show ticket form
GET    /tickets/{ticket}           # View ticket details
GET    /tickets/datatable          # Get paginated tickets
POST   /tickets/{id}/assess        # Assess ticket (programmer)
POST   /tickets/{id}/approve/dh    # DH approval
POST   /tickets/{id}/approve/od    # OD approval
POST   /tickets/{id}/assign        # Assign to programmer(s)
POST   /tickets/{id}/resolve       # Mark as resolved
POST   /tickets/{id}/close         # Close ticket
POST   /tickets/{id}/return        # Return to requestor
POST   /tickets/{id}/resubmit      # Resubmit after return
POST   /tickets/{id}/hold          # Put on hold
POST   /tickets/{id}/resume        # Resume from hold
```

#### 5.2 Project Module

**Project Statuses:**
| ID | Status |
|----|--------|
| 1 | PLANNING |
| 2 | TRIAGED/APPROVED |
| 3 | IN_PROGRESS |
| 4 | ON_HOLD |
| 5 | DEPLOYED |
| 6 | CANCELLED |
| 7 | INACTIVE |

**Project-Ticket Relationship:**
- New System tickets automatically create projects
- Project status syncs from ticket statuses
- Tickets must be all closed before project can be deployed

#### 5.3 Task Module

- Tasks are auto-created when ticket is assigned
- Each assigned programmer gets individual task
- Tasks track individual work items within a ticket

#### 5.4 Notification System

- Real-time notifications via Laravel Reverb/Pusher
- WebSocket channels: `users.{emp_id}`
- Notification types: Ticket created, resolved, closed, returned, approved, assigned

### 6. Authentication Flow

```
┌──────────┐     ┌─────────────┐     ┌───────────┐     ┌──────────────┐
│  User    │────▶│  Authify    │────▶│  TPTMS    │────▶│   Session    │
│  Access  │     │  (SSO)      │     │  Backend  │     │   Created    │
└──────────┘     └─────────────┘     └───────────┘     └──────────────┘
     │                │                   │                   │
     │  Login with    │  Returns token    │  Stores session   │
     │  credentials   │  via redirect     │  with emp_data    │
     └────────────────┴───────────────────┴───────────────────┘

Session Data (emp_data):
- emp_id, emp_name, emp_firstname
- emp_jobtitle, emp_dept
- emp_prodline, emp_station, emp_position
- emp_system_role
```

### 7. Configuration Files

| File | Purpose |
|------|---------|
| `.env` | Main environment variables |
| `config/database.php` | Database connections |
| `config/reverb.php` | WebSocket configuration |
| `config/broadcasting.php` | Pusher/Reverb settings |
| `config/cors.php` | Cross-origin settings |
| `routes/*.php` | API and web routes |

### 8. Environment Variables

Key variables in `.env`:
```
# App
APP_NAME=tptms

# Database Connections
DB_CONNECTION=sqlite
PMS_HOST, PMS_DATABASE, PMS_USERNAME, PMS_PASSWORD  # Projects DB
TMS_HOST, TMS_DATABASE, TMS_USERNAME, TMS_PASSWORD  # Tasks DB
MDB_HOST, MDB_DATABASE, MDB_USERNAME, MDB_PASSWORD  # Masterlist DB
ADB_HOST, ADB_DATABASE, ADB_USERNAME, ADB_PASSWORD  # Authify DB

# Reverb/Pusher
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=
REVERB_PORT=
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
```

### 9. Deployment Notes

1. **HTTPS Required**: SSL certificates needed for Reverb/WebSocket
2. **Multiple Databases**: All 4 MySQL connections must be accessible
3. **SSO Dependency**: Requires authify server at `192.168.2.221:8200`
4. **Queue Workers**: For notification processing (if implemented)
5. **Scheduled Tasks**: CheckDueTickets command for overdue notifications

### 10. Key Files Reference

| File | Lines | Purpose |
|------|-------|---------|
| `app/Services/TicketService.php` | 1400+ | Core ticket logic, workflow, validation |
| `app/Repositories/TicketRepository.php` | - | Database queries for tickets |
| `app/Http/Controllers/TicketingController.php` | - | HTTP endpoints |
| `app/Http/Middleware/AuthMiddleware.php` | 284 | SSO authentication |
| `app/ValueObjects/WorkflowPath.php` | - | Request type workflow definitions |
| `resources/js/Pages/Ticketing/*.jsx` | - | React components |

### 11. Testing

```bash
# Run tests
composer test
# or
php artisan test
```

### 12. Development Commands

```bash
# Install dependencies
composer install
npm install

# Development server
npm run dev

# Full dev with queue/logs
composer run dev

# Clear cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Database
php artisan migrate
php artisan db:seed
```
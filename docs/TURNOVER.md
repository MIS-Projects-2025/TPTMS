# TPTMS - Turnover Documentation

## System Overview

**TPTMS (Technical Project & Task Management System)** is a Laravel 12 + React/Inertia.js application for managing technical projects, tasks, and support tickets with real-time notifications.

### Purpose
- Submit and track IT support tickets
- Manage software development projects
- Track tasks assigned to programmers
- Multi-level approval workflow (Assessment → DH → OD → Assignment → Resolution → Close)
- Real-time notifications via WebSocket

---

## System Configuration

### 1. Environment Variables (`.env`)

Key configurations needed:

```env
# Application
APP_NAME=tptms
APP_ENV=local
APP_DEBUG=true
APP_URL=https://tptms.local

# Main Database (SQLite)
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

# Reverb/WebSocket
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

### 2. Database Connections

Located in `config/database.php`:

| Connection | Purpose | Table Example |
|------------|---------|---------------|
| `projects` | Project management | `project_list`, `project_logs` |
| `task` | Tickets & Tasks | `tickets`, `ticket_workflow`, `tasks` |
| `masterlist` | Employee data | `employee_masterlist` |
| `authify` | SSO sessions | `authify_sessions` |
| `sqlite` | Laravel internal | Cache, jobs |

### 3. Apache/Nginx Configuration

For HTTPS with WebSocket support (Reverb):

```apache
# SSL Certificate required
SSLEngine on
SSLCertificateFile /path/to/cert.crt
SSLCertificateKeyFile /path/to/cert.key

# Proxy to Laravel
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
# Development
composer install
npm install
npm run dev

# Start Laravel server
php artisan serve --host=0.0.0.0 --port=8000

# Full dev environment (server + queue + logs + vite)
composer run dev

# Production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Key Modules

### 1. Authentication (SSO)

**Flow:**
1. User accesses app → redirected to authify login
2. Authify validates credentials → redirects back with token in URL
3. `AuthMiddleware` validates token from `authify_sessions` table
4. Session created with `emp_data`

**Session Data Structure:**
```php
session('emp_data') = [
    'token' => 'xxx',
    'emp_id' => '1001',
    'emp_name' => 'John Doe',
    'emp_firstname' => 'John',
    'emp_jobtitle' => 'Programmer',
    'emp_dept' => 'MIS',
    'emp_prodline' => '...',
    'emp_station' => '...',
    'emp_position' => 3,
    'emp_system_role' => 'Programmer',
];
```

**Access Control Logic** (AuthMiddleware.php:66-78):
- `emp_from` must be NULL
- AND (`emp_position >= 2` OR `jobtitle contains programmer` OR `jobtitle contains MIS Senior Supervisor` OR `is project handler`)

### 2. Ticketing System

**Workflow Paths:**
- **New System/Modification/Enhancement**: Assess → DH Approve → OD Approve → Assign → Resolve → Close
- **Adjustment**: DH Approve → Assign → Resolve → Close
- **Testing/Parallel Run**: Assign → Resolve → Close

**Ticket Statuses:**
```php
const STATUS_NEW = 1;
const STATUS_TRIAGED = 2;
const STATUS_APPROVED = 3;
const STATUS_IN_PROGRESS = 4;
const STATUS_RESOLVED = 5;
const STATUS_CLOSED = 6;
const STATUS_REJECTED = 7;
const STATUS_ON_HOLD = 8;
const STATUS_RETURNED = 9;
```

**Key Ticket Tables:**
- `tickets` - Main ticket data
- `ticket_workflow` - Workflow history (actions taken)
- `ticket_remarks` - Comments/notes
- `ticket_attachments` - File attachments
- `ticket_testers` - Assigned testers for testing requests

### 3. Project Management

- New System tickets auto-create projects
- Project status auto-syncs from ticket statuses
- Project can only be "Deployed" when all tickets are closed

**Project Statuses:**
```php
const PROJ_STATUS_PLANNING = 1;
const PROJ_STATUS_TRIAGED = 2;
const PROJ_STATUS_IN_PROGRESS = 3;
const PROJ_STATUS_ON_HOLD = 4;
const PROJ_STATUS_DEPLOYED = 5;
const PROJ_STATUS_CANCELLED = 6;
const PROJ_STATUS_INACTIVE = 7;
```

### 4. Task System

- Tasks created automatically when ticket is assigned
- Each assigned programmer gets individual task
- Task statuses track individual work progress

### 5. Notification System

**Real-time via Laravel Reverb:**
- WebSocket channel: `users.{emp_id}`
- Uses Pusher protocol
- Notifications: Ticket created, assigned, resolved, closed, returned, approved

**Notification Tables:**
- `notifications` - Notification records
- `notification_users` - User notification preferences

---

## Common Issues & Troubleshooting

### 1. Authentication Issues

**Problem:** Redirect loop or can't access app
- Check authify server is accessible (`ping 192.168.2.221`)
- Verify `authify_sessions` table has valid tokens
- Clear session: `php artisan cache:clear`

**Problem:** Access denied
- User must have `emp_from = NULL`
- Job title must contain "programmer" or "MIS Senior Supervisor"
- OR user must be in `project_list.PROJ_HANDLER`

### 2. Database Connection Issues

**Problem:** "Connection refused" errors
- Check MySQL servers are running
- Verify host/port in `.env`
- Test connection: `php artisan tinker` then `DB::connection('projects')->ping()`

### 3. WebSocket/Reverb Issues

**Problem:** Notifications not working
- Check SSL certificate is valid
- Verify Reverb configured in `.env`
- Check Reverb server is running
- Check browser console for WebSocket errors

**Test Reverb:**
```
https://your-domain/reverb-test
```

### 4. Ticket Workflow Issues

**Problem:** Action buttons not showing
- Check user role permissions (TicketService.php)
- Verify ticket status allows the action
- Check WorkflowPath for request type

### 5. Excel Import Issues

**Problem:** Import fails
- Required columns: `PROJ_NAME`, `PROJ_DEPT`, `PROJ_STATUS`
- Status must be text (e.g., "Planning", "In Progress") or numeric (1-7)

---

## Database Schema Reference

### Main Tables

**tickets**
| Column | Type | Description |
|--------|------|-------------|
| ID | int | Primary key |
| TICKET_ID | varchar | Unique ticket number |
| EMPLOYID | varchar | Requestor ID |
| TYPE_OF_REQUEST | int | Request type (1-6) |
| PROJECT_NAME | varchar | Associated project |
| STATUS | int | Current status (1-9) |
| DETAILS | text | Description |
| ASSIGNED_TO | varchar | Assigned programmers |
| CREATED_AT | datetime | Creation time |
| CLOSED_AT | datetime | Close time |

**ticket_workflow**
| Column | Type | Description |
|--------|------|-------------|
| ID | int | Primary key |
| TICKET_ID | int | FK to tickets |
| ACTION_TYPE | varchar | Action taken |
| ACTION_BY | varchar | Employee who did action |
| OLD_STATUS | int | Previous status |
| NEW_STATUS | int | New status |
| ACTION_AT | datetime | When action occurred |

**project_list**
| Column | Type | Description |
|--------|------|-------------|
| PROJ_ID | int | Primary key |
| PROJ_NAME | varchar | Project name |
| PROJ_DESC | text | Description |
| PROJ_DEPT | varchar | Department |
| PROJ_STATUS | int | Status (1-7) |
| ASSIGNED_PROGS | varchar | Assigned programmers |
| DATE_START | date | Start date |
| DATE_END | date | End date |
| CREATED_BY | varchar | Creator ID |

---

## API Routes

```
# Ticketing
GET    /{app}/tickets              - Show ticket form
POST   /{app}/tickets              - Create ticket
GET    /{app}/tickets/datatable    - List tickets
GET    /{app}/tickets/{ticket}     - View ticket
POST   /{app}/{ticketId}/assess    - Assess
POST   /{app}/{ticketId}/approve/dh  - DH approve
POST   /{app}/{ticketId}/approve/od  - OD approve
POST   /{app}/{ticketId}/assign    - Assign
POST   /{app}/{ticketId}/resolve   - Resolve
POST   /{app}/{ticketId}/close     - Close
POST   /{app}/{ticketId}/return    - Return
POST   /{app}/{ticketId}/resubmit  - Resubmit

# Projects
GET    /{app}/projects             - List projects
POST   /{app}/projects             - Create project
GET    /{app}/projects/{id}        - View project
PUT    /{app}/projects/{id}        - Update project
POST   /{app}/projects/import      - Excel import

# Tasks
GET    /{app}/tasks                - List tasks
POST   /{app}/tasks                - Create task
PUT    /{app}/tasks/{id}           - Update task

# Dashboard
GET    /{app}/dashboard            - Dashboard data
```

---

## Important Files

| File | Purpose |
|------|---------|
| `app/Services/TicketService.php` | Core ticket logic (1400+ lines) |
| `app/Repositories/TicketRepository.php` | Ticket DB queries |
| `app/Http/Middleware/AuthMiddleware.php` | SSO authentication |
| `app/ValueObjects/WorkflowPath.php` | Workflow definitions |
| `config/database.php` | Database connections |
| `.env` | Environment configuration |
| `routes/ticketing.php` | Ticket routes |
| `routes/projects.php` | Project routes |
| `resources/js/Pages/Ticketing/*.jsx` | React components |

---

## Maintenance Checklist

### Daily
- Monitor error logs (`storage/logs/`)
- Check notification delivery

### Weekly
- Review ticket backlog
- Check overdue tickets (`php artisan ticket:check-due`)

### Monthly
- Database backup
- Clear old cache files
- Review SSL certificate expiry

### On-Going
- Monitor MySQL connections
- Check disk space
- Review application logs for errors

---

## Commands Reference

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan clear-compiled

# Database
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed

# Test
php artisan test

# Check due dates
php artisan ticket:check-due

# List routes
php artisan route:list

# Tinker (interactive)
php artisan tinker
```

---

## Contacts

| Role | Responsibility |
|------|----------------|
| System Admin | Server, database, SSL |
| Lead Developer | Application logic, bugs |
| MIS Department | Ticket approvals, assignments |
| Operations Director | Final approvals |

---

## Notes

1. **No Local Registration** - System uses SSO (authify). Users cannot register themselves.
2. **4 Databases Required** - All must be accessible for full functionality.
3. **HTTPS Required** - WebSocket (Reverb) needs SSL.
4. **Job Title Based Roles** - Roles determined by job title parsing.
5. **Auto Project Creation** - New System requests automatically create projects.
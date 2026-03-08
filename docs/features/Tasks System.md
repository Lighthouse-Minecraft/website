# Tasks System -- Technical Documentation

> **Audience:** Project owner, developers, AI agents
> **Generated:** 2026-03-07
> **Generator:** `/document-feature` skill

---

## Table of Contents

1. [Overview](#1-overview)
2. [Database Schema](#2-database-schema)
3. [Models & Relationships](#3-models--relationships)
4. [Enums Reference](#4-enums-reference)
5. [Authorization & Permissions](#5-authorization--permissions)
6. [Routes](#6-routes)
7. [User Interface Components](#7-user-interface-components)
8. [Actions (Business Logic)](#8-actions-business-logic)
9. [Notifications](#9-notifications)
10. [Background Jobs](#10-background-jobs)
11. [Console Commands & Scheduled Tasks](#11-console-commands--scheduled-tasks)
12. [Services](#12-services)
13. [Activity Log Entries](#13-activity-log-entries)
14. [Data Flow Diagrams](#14-data-flow-diagrams)
15. [Configuration](#15-configuration)
16. [Test Coverage](#16-test-coverage)
17. [File Map](#17-file-map)
18. [Known Issues & Improvement Opportunities](#18-known-issues--improvement-opportunities)

---

## 1. Overview

The Tasks System provides a lightweight to-do/action-item tracker for staff members, integrated into both the meeting workflow and the Staff Ready Room. Tasks are organized by department (`section_key`) and follow a lifecycle of Pending → Completed → Archived. They are created during meetings or from the Ready Room, assigned to staff members, and tracked across meeting iterations.

Staff members at the CrewMember rank and above can create and update tasks. Tasks are displayed in two primary contexts: within the meeting edit page (alongside department notes), and in the Staff Ready Room (a standalone page with department tabs and a personal "My Board" view). Each task can optionally be assigned to a specific staff member.

The Tasks System ties closely to the Meeting system — tasks record which meeting they were created in (`assigned_meeting_id`), which meeting they were completed in (`completed_meeting_id`), and which meeting they were archived in (`archived_meeting_id`). This enables the Command Dashboard to track staff productivity by counting tasks completed per iteration (time period between staff meetings).

Key concepts: **section_key** maps tasks to departments (command, chaplain, engineer, quartermaster, steward); **archiving** is done during meetings to clear completed tasks from the active list while preserving a record; the **Ready Room** is the staff hub page gated by `view-ready-room`.

---

## 2. Database Schema

### `tasks` table

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| `id` | bigint (auto) | No | — | Primary key |
| `name` | string | No | — | Task description |
| `section_key` | string | Yes | — | Department key (e.g. "command", "chaplain") |
| `status` | string | No | — | TaskStatus enum value |
| `assigned_meeting_id` | foreignId | Yes | — | Meeting where task was created (nullable after migration) |
| `created_by` | foreignId | No | — | User who created the task |
| `assigned_to_user_id` | foreignId | Yes | — | User the task is assigned to; nullOnDelete |
| `completed_by` | foreignId | Yes | — | User who completed the task |
| `completed_at` | timestamp | Yes | — | When the task was completed |
| `completed_meeting_id` | foreignId | Yes | — | Meeting where task was marked completed |
| `archived_meeting_id` | foreignId | Yes | — | Meeting where task was archived |
| `archived_at` | timestamp | Yes | — | When the task was archived |
| `created_at` | timestamp | Yes | — | Laravel timestamp |
| `updated_at` | timestamp | Yes | — | Laravel timestamp |

**Indexes:** Standard primary key index on `id`

**Foreign Keys:**
- `assigned_meeting_id` → `meetings.id`
- `created_by` → `users.id`
- `assigned_to_user_id` → `users.id` (nullOnDelete)
- `completed_by` → `users.id`
- `completed_meeting_id` → `meetings.id`
- `archived_meeting_id` → `meetings.id`

**Migration(s):**
- `database/migrations/2025_08_16_163658_create_tasks_table.php` — creates the table
- `database/migrations/2025_08_19_191557_update_tasks_nulify_meeting_field.php` — makes `assigned_meeting_id` nullable
- `database/migrations/2026_02_26_215111_add_assigned_to_user_id_to_tasks_table.php` — adds `assigned_to_user_id` column

---

## 3. Models & Relationships

### Task (`app/Models/Task.php`)

**Relationships:**

| Method | Type | Related Model | Notes |
|--------|------|---------------|-------|
| `createdBy()` | belongsTo | User | FK: `created_by` |
| `completedBy()` | belongsTo | User | FK: `completed_by` |
| `completedMeeting()` | belongsTo | Meeting | FK: `completed_meeting_id` |
| `assignedMeeting()` | belongsTo | Meeting | FK: `assigned_meeting_id` |
| `assignedTo()` | belongsTo | User | FK: `assigned_to_user_id` |

**Scopes:** None

**Key Methods:** None (simple model)

**Casts:**
- `status` => `TaskStatus::class`
- `completed_at` => `datetime`
- `archived_at` => `datetime`

**Fillable:** `name`, `assigned_meeting_id`, `section_key`, `status`, `created_by`, `completed_by`, `completed_at`, `completed_meeting_id`, `archived_at`, `archived_meeting_id`, `assigned_to_user_id`

**Note:** Neither the User model nor the Meeting model define inverse `hasMany` relationships for tasks. The Task model has all five `belongsTo` relationships, but none of them have corresponding `hasMany` on the related models. Queries for tasks by user/meeting are done via direct `Task::where(...)` queries.

---

## 4. Enums Reference

### TaskStatus (`app/Enums/TaskStatus.php`)

| Case | Value | Label | Notes |
|------|-------|-------|-------|
| `Pending` | `pending` | Pending | Initial state when created |
| `InProgress` | `in-progress` | In Progress | Not used in current UI flow |
| `Completed` | `completed` | Completed | Marked done by a staff member |
| `Archived` | `archived` | Archived | Cleared from active list during a meeting |

**Helper methods:**
- `label(): string` — returns human-readable label for each case

---

## 5. Authorization & Permissions

### Gates (from `AuthServiceProvider`)

| Gate Name | Who Can Pass | Logic Summary |
|-----------|-------------|---------------|
| `view-ready-room` | Admin OR JrCrew+ rank | Access to the Staff Ready Room page |
| `view-ready-room-command` | Admin OR Officer+ OR (JrCrew+ AND Command dept) | View Command department tab |
| `view-ready-room-chaplain` | Admin OR Officer+ OR (JrCrew+ AND Chaplain dept) | View Chaplain department tab |
| `view-ready-room-engineer` | Admin OR Officer+ OR (JrCrew+ AND Engineer dept) | View Engineer department tab |
| `view-ready-room-quartermaster` | Admin OR Officer+ OR (JrCrew+ AND Quartermaster dept) | View Quartermaster department tab |
| `view-ready-room-steward` | Admin OR Officer+ OR (JrCrew+ AND Steward dept) | View Steward department tab |

### Policies

#### TaskPolicy (`app/Policies/TaskPolicy.php`)

**`before()` hook:** Admin → returns `true` (full bypass)

| Ability | Who Can | Conditions |
|---------|---------|------------|
| `viewAny` | Nobody (except Admin via before) | Returns false |
| `view` | Nobody (except Admin via before) | Returns false |
| `create` | CrewMember+ rank | `$user->isAtLeastRank(StaffRank::CrewMember)` |
| `update` | CrewMember+ rank | `$user->isAtLeastRank(StaffRank::CrewMember)` |
| `delete` | Nobody (except Admin via before) | Returns false |
| `restore` | Nobody (except Admin via before) | Returns false |
| `forceDelete` | Nobody (except Admin via before) | Returns false |

### Permissions Matrix

| User Type | View Ready Room | View Own Dept Tab | View All Dept Tabs | Create Task | Update Task | Complete Task | Archive Task |
|-----------|----------------|-------------------|-------------------|-------------|-------------|---------------|--------------|
| Unauthenticated | No | No | No | No | No | No | No |
| Regular User | No | No | No | No | No | No | No |
| JrCrew | Yes | Yes (own dept only) | No | No | No | No | No |
| CrewMember | Yes | Yes (own dept only) | No | Yes | Yes | Yes | Yes* |
| Officer | Yes | Yes | Yes (all depts) | Yes | Yes | Yes | Yes* |
| Admin | Yes | Yes | Yes (all depts) | Yes | Yes | Yes | Yes* |

*Archive button only shows when task is completed AND viewing within a meeting context.

---

## 6. Routes

| Method | URL | Middleware | Handler | Route Name |
|--------|-----|-----------|---------|------------|
| GET | `/ready-room` | auth | `DashboardController@readyRoom` | `ready-room.index` |

**Note:** Tasks do not have their own routes. They are managed entirely through embedded Livewire components within the Ready Room page and meeting edit pages. The meeting edit page routes (which embed the task department-list component) are part of the Meeting system, not the Tasks system.

---

## 7. User Interface Components

### Ready Room Page
**File:** `resources/views/dashboard/ready-room.blade.php`
**Route:** `/ready-room` (route name: `ready-room.index`)

**Purpose:** Layout wrapper that renders the Ready Room Livewire component. Includes a heading and a "View Tickets" button linking to the tickets index.

**Authorization:** `Gate::authorize('view-ready-room')` in `DashboardController@readyRoom`

### Ready Room Tabs
**File:** `resources/views/livewire/dashboard/ready-room.blade.php`

**Purpose:** Main Ready Room component with tab navigation. Default tab is "My Board". Additional tabs for each department (Command, Chaplain, Engineer, Quartermaster, Steward) are gated by department-specific gates.

**PHP Properties:**
- `$tab` — current tab name, default `'my-board'`

**UI Elements:**
- Segmented tab navigation with 6 tabs (My Board + 5 departments, each gated)
- "My Board" tab: side-by-side layout with upcoming meetings (1/4 width) and personal tasks (3/4 width)
- Department tabs: embed `ready-room-department` component

### My Tasks Panel
**File:** `resources/views/livewire/dashboard/ready-room-my-tasks.blade.php`

**Purpose:** Shows all tasks assigned to the current user, grouped by section_key (department).

**Authorization:** None (relies on parent page gate)

**PHP Properties/Computed:**
- `tasksBySection` (computed) — Tasks assigned to `auth()->id()` with status Pending or Completed, grouped by `section_key`

**UI Elements:**
- Empty state with clipboard icon when no tasks assigned
- Grid of cards (2-column on md+), one per department section
- Each task rendered via `<livewire:task.show-task>`

### Department Tab Content
**File:** `resources/views/livewire/dashboard/ready-room-department.blade.php`

**Purpose:** Renders a department's task list and meeting notes within the Ready Room.

**PHP Properties:**
- `$department` — department string value
- `$meeting` — empty Meeting instance (tasks shown without meeting context)

**UI Elements:**
- Side-by-side layout: upcoming meetings (1/4) + department task list (3/4)
- Below: meeting notes display accordion

### Department Task List
**File:** `resources/views/livewire/task/department-list.blade.php`

**Purpose:** Shows all tasks for a department section, with sections for pending, completed, and archived tasks. Includes an "Add Task" form.

**Authorization:** `$this->authorize('create', Task::class)` on `addTask()` method

**PHP Properties:**
- `$meeting` — Meeting model (may be empty if from Ready Room)
- `$section_key` — department identifier string
- `$taskName` — input for new task name
- `$tasks` — pending tasks collection
- `$completedTasks` — completed tasks collection
- `$archivedTasks` — tasks archived in the current meeting (only loaded when meeting context exists)

**Key Methods:**
- `loadTasks()` — queries tasks by section_key, split into pending/completed/archived
- `addTask()` — authorizes, validates non-empty name, creates Task with Pending status

**Listeners:** `taskUpdated` → `loadTasks` (refreshes when child show-task components dispatch)

**UI Elements:**
- "Recently Completed Tasks" section with completed task items
- "In Progress Tasks" section with pending task items
- "Archived Tasks This Meeting" section (only visible during meetings)
- Add task form: text input + "Add Task" button

### Single Task Display
**File:** `resources/views/livewire/task/show-task.blade.php`

**Purpose:** Renders a single task as a checkbox (or archived display), with edit modal and archive button.

**Authorization:** `@can('update', $task)` for edit button; `$this->authorize('update', $this->task)` on `updateTask()`

**PHP Properties:**
- `$task` — Task model (loaded with `assignedTo` relation)
- `$meeting` — optional Meeting model for meeting context
- `$editName` — name field for edit modal
- `$editAssignedTo` — user ID for assignment in edit modal

**Key Methods:**
- `toggleCompletion()` — toggles between Pending ↔ Completed, sets/clears completed_by, completed_at, completed_meeting_id
- `openEditModal()` — opens edit modal for the task
- `updateTask()` — authorizes, validates, updates name and assigned_to_user_id
- `markAsArchived()` — sets status to Archived with archived_at and archived_meeting_id

**Computed Properties:**
- `isCompleted` — true if status is Completed
- `isArchived` — true if status is Archived
- `staffUsers` — all users with a staff_department (for assignment dropdown)

**UI Elements:**
- Checkbox with task name (or check-circle icon if archived)
- Assigned user badge
- Edit button (pencil icon, gated by policy)
- Archive button (only visible when completed AND in meeting context)
- Edit modal: task name input, assign-to select dropdown, Save/Cancel buttons

### Upcoming Meetings Panel
**File:** `resources/views/livewire/dashboard/ready-room-upcoming-meetings.blade.php`

**Purpose:** Shows up to 3 upcoming meetings with check-in report status.

**PHP Properties:**
- `$meetings` — up to 3 pending meetings with question counts
- `$userReports` — array of meeting IDs where current user has submitted reports

**UI Elements:**
- List of upcoming meetings with links
- Check-in status badges (Submit/Update/Disabled)

### Meeting Department Section (Task Integration)
**File:** `resources/views/livewire/meeting/department-section.blade.php`

**Purpose:** Renders a department section within a meeting edit page, including notes editor and task list side-by-side.

**UI Elements:**
- Department heading and description
- Report cards (for staff meetings)
- Notes editor (2/3 width) alongside task department-list (1/3 width)
- Community section omits tasks (notes only)

---

## 8. Actions (Business Logic)

Not applicable for this feature. Tasks use direct model operations (`Task::create()`, `$task->update()`) in Livewire components rather than Action classes.

**Note:** The `GetIterationBoundaries` action (documented in Command Dashboard) queries tasks for staff engagement metrics, but does not create/update tasks.

---

## 9. Notifications

Not applicable for this feature.

---

## 10. Background Jobs

Not applicable for this feature.

---

## 11. Console Commands & Scheduled Tasks

Not applicable for this feature.

---

## 12. Services

Not applicable for this feature.

---

## 13. Activity Log Entries

Not applicable for this feature. Task creation, completion, and archiving do not call `RecordActivity::run()`.

---

## 14. Data Flow Diagrams

### Creating a Task

```
Staff member types task name and clicks "Add Task" on department-list component
  -> addTask() fires on task.department-list
    -> $this->authorize('create', Task::class) → TaskPolicy@create()
    -> Validates: taskName is not empty
    -> Task::create([
         name, section_key, status=Pending,
         assigned_meeting_id (from meeting context),
         created_by=auth()->id()
       ])
    -> Flux::toast('Task created', 'Success', variant: 'success')
    -> loadTasks() refreshes the list
```

### Completing a Task (Toggle)

```
Staff member clicks checkbox on a task in show-task component
  -> toggleCompletion() fires
    -> If currently Completed:
         -> $task->update([status=Pending, completed_by=null, completed_at=null, completed_meeting_id=null])
    -> If currently Pending:
         -> $task->update([status=Completed, completed_by=auth()->id(), completed_at=now(), completed_meeting_id=meeting?.id])
    -> $this->dispatch('taskUpdated') → parent reloads task list
```

### Editing a Task

```
Staff member clicks pencil icon on a task
  -> openEditModal() fires → modal opens
  -> User modifies name and/or assignment, clicks "Save"
  -> updateTask() fires
    -> $this->authorize('update', $this->task) → TaskPolicy@update()
    -> validate: editName required|string|max:255, editAssignedTo nullable|exists:users
    -> $task->update([name, assigned_to_user_id])
    -> Modal closes
    -> $this->dispatch('taskUpdated')
    -> Flux::toast('Task updated.', variant: 'success')
```

### Archiving a Task

```
During a meeting, staff member clicks "Archive" on a completed task
  -> markAsArchived() fires
    -> $task->update([
         status=Archived,
         archived_at=now(),
         archived_meeting_id=meeting?.id
       ])
    -> $this->dispatch('taskUpdated')
```

### Viewing Tasks (Ready Room)

```
Staff member navigates to /ready-room
  -> GET /ready-room (middleware: auth)
    -> DashboardController@readyRoom()
      -> Gate::authorize('view-ready-room')
      -> return view('dashboard.ready-room')
        -> <livewire:dashboard.ready-room />
          -> "My Board" tab: <livewire:dashboard.ready-room-my-tasks />
            -> Queries: Task where assigned_to_user_id = auth, status in [Pending, Completed]
          -> Department tabs (gated): <livewire:dashboard.ready-room-department />
            -> <livewire:task.department-list /> with empty Meeting
              -> Queries: Task where section_key = department, status = Pending/Completed
```

---

## 15. Configuration

Not applicable for this feature.

---

## 16. Test Coverage

### Test Files

| File | Tests | What It Covers |
|------|-------|----------------|
| `tests/Feature/Meeting/TaskTest.php` | 4 tests (+ many todo/wip blocks) | Task CRUD within meetings, permissions |
| `tests/Feature/DepartmentReadyRoom/ReadyRoomPageTest.php` | 10 tests | Ready Room page access, department visibility, task display |

### Test Case Inventory

#### `tests/Feature/Meeting/TaskTest.php`

**Task Management - Create Task:**
- `it('should have an add task input field in the department component')` — done
- `it('should create a new task when the form is submitted')` — done

**Task Management - Task List:**
- `it('should display a list of tasks in the department component')` — done

**Task Management - Task Completion:** (todo — no test implementations)
- Tasks can be marked as completed
- The task records who completed it
- The task records the time it was completed at
- Confirming a task as completed sets the completed_meeting_id

**Task Management - Task Edit:** (todo — no test implementations)
- The task edit button opens a modal
- The task edit modal allows updating the name
- The task edit modal allows assigning a user
- A task can be marked as cancelled
- Validate user data

**Task Management - Permissions:**
- `it('should allow officers and crew members to create tasks')` — done (uses `rankAtLeastCrewMembers` dataset)

#### `tests/Feature/DepartmentReadyRoom/ReadyRoomPageTest.php`

**Ready Room Page:**
- `it('shows the Ready Room link in the sidebar for all ranks')` — done
- `it('does not show the Ready Room link in the sidebar for members')` — done
- `it('loads the Ready Room page')` — done
- `it('is accessible by all ranks')` — done
- `it('is not accessible by members')` — done
- `it('is not accessible to guests')` — done

**Department Page - Departments:**
- `it('displays the list of departments as a tab list')` — done
- `it('allows Officers to view all departments')` — done
- `it('allows JrCrew and Crew Members to view their department')` — done

**Department Page - Recent Meeting Notes:**
- `it('displays the recent meeting notes')` — done
- `it('displays the current task list')` — done

### Coverage Gaps

- **Task completion toggle is untested** — the `toggleCompletion()` method has no tests (entire describe block is `todo`)
- **Task editing is untested** — the `updateTask()` method, validation, and assignment are untested (entire describe block is `todo`)
- **Task archiving is untested** — the `markAsArchived()` method has no tests
- **"My Tasks" board is untested** — the `ready-room-my-tasks` component has no tests
- **JrCrew cannot create tasks** — the policy restricts to CrewMember+, but JrCrew can view the Ready Room; no test verifies JrCrew is denied task creation
- **No test for un-completing a task** — the toggle from Completed back to Pending is untested
- **No test for task assignment** — assigning a user to a task via the edit modal is untested
- **Task display within meeting pages** — no test verifies tasks render correctly in the meeting edit context

---

## 17. File Map

**Models:**
- `app/Models/Task.php`

**Enums:**
- `app/Enums/TaskStatus.php`

**Actions:** None

**Policies:**
- `app/Policies/TaskPolicy.php`

**Gates:** `app/Providers/AuthServiceProvider.php` — gates: `view-ready-room`, `view-ready-room-command`, `view-ready-room-chaplain`, `view-ready-room-engineer`, `view-ready-room-quartermaster`, `view-ready-room-steward`

**Notifications:** None

**Jobs:** None

**Services:** None

**Controllers:**
- `app/Http/Controllers/DashboardController.php` (method: `readyRoom`)

**Volt Components:**
- `resources/views/livewire/dashboard/ready-room.blade.php` (tab container)
- `resources/views/livewire/dashboard/ready-room-my-tasks.blade.php` (personal task board)
- `resources/views/livewire/dashboard/ready-room-department.blade.php` (department tab content)
- `resources/views/livewire/dashboard/ready-room-upcoming-meetings.blade.php` (upcoming meetings sidebar)
- `resources/views/livewire/task/show-task.blade.php` (single task display/edit/archive)
- `resources/views/livewire/task/department-list.blade.php` (department task list with add form)
- `resources/views/livewire/meeting/department-section.blade.php` (meeting page integration, embeds department-list)

**Views:**
- `resources/views/dashboard/ready-room.blade.php` (layout wrapper)

**Routes:**
- `ready-room.index` — `GET /ready-room`

**Migrations:**
- `database/migrations/2025_08_16_163658_create_tasks_table.php`
- `database/migrations/2025_08_19_191557_update_tasks_nulify_meeting_field.php`
- `database/migrations/2026_02_26_215111_add_assigned_to_user_id_to_tasks_table.php`

**Console Commands:** None

**Tests:**
- `tests/Feature/Meeting/TaskTest.php`
- `tests/Feature/DepartmentReadyRoom/ReadyRoomPageTest.php`

**Config:** None

**Other:**
- `database/factories/TaskFactory.php` (test factory with `withDepartment`, `withCreator`, `withMeeting` states)
- `resources/views/components/layouts/app/sidebar.blade.php` (sidebar link gated by `view-ready-room`)

---

## 18. Known Issues & Improvement Opportunities

1. **Significant test coverage gaps** — Task completion, editing, archiving, and the "My Tasks" board have no test coverage. The `TaskTest.php` file has multiple `todo` and `wip` describe blocks with no implemented tests for these critical workflows.

2. **No activity logging** — Task creation, completion, editing, and archiving produce no activity log entries via `RecordActivity::run()`. There is no audit trail for task lifecycle changes.

3. **No authorization on toggleCompletion()** — The `toggleCompletion()` method in `show-task.blade.php` does not call `$this->authorize()`. Any authenticated user who can access the component could potentially toggle task completion, bypassing the policy check.

4. **No authorization on markAsArchived()** — The `markAsArchived()` method does not verify the user has permission to archive. Similar to `toggleCompletion()`, it relies on UI visibility rather than explicit authorization.

5. **InProgress status is unused** — `TaskStatus::InProgress` is defined but never set anywhere in the codebase. Tasks go directly from Pending to Completed. This enum case is dead code.

6. **Business logic in components** — Task creation and updates use direct `Task::create()` and `$task->update()` calls in Livewire components, not Action classes. This is inconsistent with the project convention of using Action classes for business logic.

7. **No inverse relationships on User/Meeting models** — Neither User nor Meeting define `hasMany` relationships to tasks. All task queries are done via `Task::where(...)`, which works but means you cannot use `$user->tasks` or `$meeting->tasks` for eager loading.

8. **Archive button visibility logic** — The archive button only appears when `$this->isCompleted && $this->meeting?->id`, meaning tasks can only be archived during meeting context. Tasks completed outside meetings (via the Ready Room) have no way to be archived from the UI.

9. **No delete functionality** — The TaskPolicy defines `delete`, `restore`, and `forceDelete` methods (all returning false), but there is no UI or route to delete tasks. Once created, tasks can only be archived, never removed.

10. **Empty task name validation inconsistency** — The `addTask()` method checks `empty($this->taskName)` manually and shows a Flux toast, but `updateTask()` uses formal validation rules (`required|string|max:255`). Task creation should also use proper validation.

11. **N+1 potential in department-list** — `loadTasks()` queries tasks without eager loading relationships, but each task is rendered via `<livewire:task.show-task>` which calls `$task->load('assignedTo')` in `mount()`. This is not a traditional N+1 since each child component loads its own relation, but it results in one query per task for the assignedTo relationship.

12. **staffUsers computed property is uncached** — The `staffUsers` computed property in `show-task` queries all staff users every time the edit modal dropdown needs rendering. With many tasks on a page, this could result in redundant queries (though Livewire computed caching per request mitigates this somewhat).

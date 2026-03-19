---
title: 'ACP Overview'
visibility: officer
order: 1
summary: 'What the Admin Control Panel contains and who can access it.'
---

## Overview

The **Admin Control Panel** (ACP) is the central administrative hub for the Lighthouse website. It provides a tabbed interface for managing every major area of the platform. The specific tabs you see depend on your permissions.

## Who Can Access the ACP

- **Admins** -- full access to everything
- **Crew Members** and above -- access to most tabs
- **Page Editors** -- access to CMS page management
- **Engineering department** -- access even at Jr Crew rank (for technical operations)

## ACP Categories

The ACP is organized into four top-level categories:

### Users

| Tab | What It Does |
|---|---|
| **Manage Users** | Search, filter, and view all site users. Sortable by membership level, brig status, and more. |
| **Minecraft Accounts** | View all linked Minecraft accounts. Search by username, filter by status. |
| **Discord Accounts** | View all linked Discord accounts with status and linking details. |

### Content

| Tab | What It Does |
|---|---|
| **CMS Pages** | Create and edit static pages like the rules, donation page, and other content. |
| **Announcements** | Create, schedule, publish, and expire community announcements. |
| **Meetings** | View, create, and manage meetings. Start, end, and finalize meetings. |
| **Staff Positions** | Create, edit, and organize staff positions by department. Assign and unassign users. |
| **Board Members** | Manage the Board of Directors listing. |

### Logs

| Tab | What It Does |
|---|---|
| **MC Command Log** | View all RCON commands sent to the Minecraft server with timestamps and results. |
| **Discord API Log** | View all Discord API calls with status, response times, and error details. |
| **Activity Log** | Searchable log of all system activities -- promotions, brig actions, position changes, etc. |
| **Discipline Reports** | Filterable, sortable log of all discipline reports across the community. |

### Config

| Tab | What It Does |
|---|---|
| **Roles** | Manage user roles (Admin, Meeting Secretary, Page Editor, etc.). |
| **Report Categories** | Manage discipline report categories (Language, Harassment, etc.). |
| **Prayer Nations** | Manage the prayer tracking country list. |
| **Application Questions** | Create, edit, and manage the questions shown to staff applicants. See [[books/staff-handbook/administration/staff-applications/managing-application-questions|Managing Application Questions]]. |

## Important Notes

- The ACP is a container -- each tab loads its own Livewire component independently
- What you see is filtered by your permissions. Don't be alarmed if you see fewer tabs than listed here
- The Activity Log is especially useful for auditing -- you can search by user, action type, or date range to understand what happened and when

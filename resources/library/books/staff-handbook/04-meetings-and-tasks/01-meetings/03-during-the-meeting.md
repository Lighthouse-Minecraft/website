---
title: 'During the Meeting'
visibility: staff
order: 3
summary: 'How note-taking and attendance work during live meetings.'
---

## Overview

During a meeting, staff collaborate on notes in real time. The meeting page provides department-specific note sections, attendance tracking, and an agenda view.

## Note-Taking

Meeting notes are organized by section:

- **Agenda** -- the meeting agenda, typically set up before the meeting starts
- **General** -- notes that apply to the whole team
- **Department sections** -- each department (Command, Chaplain, Engineer, Quartermaster, Steward) has its own note area
- **Community** -- notes intended for the community update that gets published after the meeting

### How Note Locking Works

To prevent conflicts, notes use an **optimistic locking** system. When you start editing a note section:

1. You "lock" that section -- other staff see that you're editing it
2. While you hold the lock, only you can save changes to that section
3. The lock has a heartbeat -- if you stop interacting, the lock expires and someone else can take over
4. When you're done, your changes are saved and the lock releases

This prevents two people from overwriting each other's notes.

## Attendance

Meeting organizers can track who attended. The attendance list is recorded with the meeting for future reference.

## Meeting Flow for Organizers

Officers (or Meeting Secretaries) manage the meeting lifecycle:

1. **Start the meeting** -- moves status to "In Progress" and copies the agenda note into the meeting record
2. Staff collaborate on notes throughout the meeting
3. **End the meeting** -- moves status to "Finalizing" and compiles all department notes into the meeting minutes
4. **Review and finalize** -- the organizer can review the compiled minutes, format community notes (with optional AI assistance), and complete the meeting
5. **Complete** -- the meeting is archived. If community updates are enabled, the community notes become visible on the Community Updates page

## Important Notes

- Only Crew Members and above can edit notes. Jr Crew can attend and view but not edit.
- The community notes section is what gets published publicly -- be mindful of what goes there
- Department notes are internal and only visible to staff

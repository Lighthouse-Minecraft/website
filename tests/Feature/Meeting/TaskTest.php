<?php

use App\Enums\TaskStatus;
use App\Models\Meeting;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->meeting = Meeting::factory()->create([
        'title' => 'Test Meeting',
        'day' => '2025-05-04',
        'scheduled_time' => '2025-05-04 19:00:00',
    ]);
});

describe('Task Management - Create Task', function () {
    // There is an "add task" input field in the department component
    it('should have an add task input field in the department component', function () {
        loginAsAdmin();

        livewire('meeting.department-section', ['meeting' => $this->meeting, 'departmentValue' => 'command', 'description' => ''])
            ->assertSee('Add Task');
    })->done();

    // The "add task" form creates a new task
    it('should create a new task when the form is submitted', function () {
        loginAsAdmin();

        livewire('task.department-list', ['meeting' => $this->meeting, 'section_key' => 'command'])
            ->set('taskName', 'New Task')
            ->call('addTask');

        $this->assertDatabaseHas('tasks', [
            'name' => 'New Task',
            'section_key' => 'command',
            'assigned_meeting_id' => $this->meeting->id,
            'status' => TaskStatus::Pending,
        ]);
    })->done();

    // The task status is set to not-started
})->wip(issue: 28, assignee: 'jonzenor');

describe('Task Management - Task List', function () {
    // There is a list of tasks displayed in the department component

    // There is an Edit button on the task items

    // Completed tasks can be confirmed as completed during a meeting
})->todo(issue: 28, assignee: 'jonzenor');

describe('Task Management - Task Completion', function () {
    // Tasks can be marked as completed - Updates status to Completed

    // The task records who completed it

    // The task records the time it was completed at

    // Confirming a task as completed sets the completed_meeting_id to the current meeting
})->todo(issue: 28, assignee: 'jonzenor');

describe('Task Management - Task Edit', function () {
    // The task edit button opens a modal

    // The task edit modal allows updating the name of the task

    // The task edit modal allows assigning a user to the task

    // A task can be marked as cancelled in the task edit modal
})->todo(issue: 28, assignee: 'jonzenor');

describe('Task Management - Permissions', function () {
    // Officers and Crew Members can create tasks
})->todo(issue: 28, assignee: 'jonzenor');

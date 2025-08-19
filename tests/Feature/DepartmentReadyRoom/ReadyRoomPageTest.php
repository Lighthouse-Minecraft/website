<?php

use function Pest\Laravel\get;

describe('Ready Room Page', function () {
    // The Ready Room link shows up in the sidebar for all ranks
    it('shows the Ready Room link in the sidebar for all ranks', function () {
        loginAsAdmin();

        get('dashboard')
            ->assertSee('Staff Ready Room')
            ->assertSee(route('ready-room.index'));
    })->wip();

    // The Ready Room link does not show up for members
    it('does not show the Ready Room link in the sidebar for members', function () {
        // Test implementation
    })->todo();

    // The Ready Room page loads
    it('loads the Ready Room page', function () {
        // Test implementation
    })->todo();
})->wip(issue: 54, assignee: 'jonzenor');

// The Ready Room page is accessible by all ranks

// The Ready Room page is not accessible by members

// The list of departments is displayed as a tab list

// Only Officers can view all of the departments

// JrCrew and Crew Members can only view their department

// The recent meeting notes are displayed as a list

// The current task list is displayed

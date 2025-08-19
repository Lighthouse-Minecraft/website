<?php

use function Pest\Laravel\get;

describe('Ready Room Page', function () {
    // The Ready Room link shows up in the sidebar for all ranks
    it('shows the Ready Room link in the sidebar for all ranks', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertSee('Staff Ready Room')
            ->assertSee(route('ready-room.index'));
    })->with('rankAtLeastJrCrew')->done();

    // The Ready Room link does not show up for members
    it('does not show the Ready Room link in the sidebar for members', function ($user) {
        loginAs($user);

        get('dashboard')
            ->assertDontSee('Staff Ready Room')
            ->assertDontSee(route('ready-room.index'));
    })->with('memberAll')->done();

    // The Ready Room page loads
    it('loads the Ready Room page', function () {
        loginAsAdmin();

        get(route('ready-room.index'))
            ->assertStatus(200)
            ->assertViewIs('dashboard.ready-room');
    })->done();

    // The Ready Room page is accessible by all ranks
    it('is accessible by all ranks', function ($user) {
        loginAs($user);

        get(route('ready-room.index'))
            ->assertStatus(200);
    })->with('rankAtLeastJrCrew')->done();

    // The Ready Room page is not accessible by members
    it('is not accessible by members', function ($user) {
        loginAs($user);

        get(route('ready-room.index'))
            ->assertStatus(403);
    })->with('memberAll')->done();

    // The Ready Room is not accessible to guests
    it('is not accessible to guests', function () {
        get(route('ready-room.index'))
            ->assertRedirect(route('login'));
    })->done();
})->done(issue: 54, assignee: 'jonzenor');

describe('Department Page - Departments', function () {
    // The list of departments is displayed as a tab list
    it('displays the list of departments as a tab list', function () {
        loginAsAdmin();

        get(route('ready-room.index'))
            ->assertStatus(200)
            ->assertSee('Command')
            ->assertSee('Chaplain')
            ->assertSee('Engineer')
            ->assertSee('Quartermaster')
            ->assertSee('Steward');
    })->done();

    // Only Officers can view all of the departments
    it('allows Officers to view all departments', function ($user) {
        loginAs($user);

        get(route('ready-room.index'))
            ->assertSee('Command')
            ->assertSee('Chaplain')
            ->assertSee('Engineer')
            ->assertSee('Quartermaster')
            ->assertSee('Steward');
    })->with('officers')->done();

    // JrCrew and Crew Members can only view their department
    it('allows JrCrew and Crew Members to view their department', function ($user) {
        loginAs($user);

        $departments['command'] = 'Command';
        $departments['chaplain'] = 'Chaplain';
        $departments['engineer'] = 'Engineer';
        $departments['quartermaster'] = 'Quartermaster';
        $departments['steward'] = 'Steward';
        $seeDepartment = '';

        $seeDepartment = $departments[$user->staff_department->value];
        $departments[$user->staff_department->value] = 'BLAHBLAHBLAH THIS WILL NEVER BE ON THE PAGE BLAHBLECH';

        get(route('ready-room.index'))
            ->assertSee($seeDepartment)
            ->assertDontSee($departments['command'])
            ->assertDontSee($departments['chaplain'])
            ->assertDontSee($departments['engineer'])
            ->assertDontSee($departments['quartermaster'])
            ->assertDontSee($departments['steward'])
            ->assertStatus(status: 200);
    })->with('rankAtMostCrewMembers')->wip();
})->wip(issue: 54, assignee: 'jonzenor');

// The recent meeting notes are displayed as a list

// The current task list is displayed

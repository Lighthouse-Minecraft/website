<?php

use App\Enums\MeetingStatus;
use App\Livewire\Meeting\NotesDisplay;
use App\Models\Meeting;
use App\Models\MeetingNote;
use Livewire\Livewire;

it('can render the notes display component', function () {
    loginAsAdmin();

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'development']);

    $component->assertStatus(200);
});

it('displays completed meetings for the specified section key', function () {
    $admin = loginAsAdmin();
    $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create(['title' => 'Dev Team Meeting', 'minutes' => 'Global meeting minutes']);
    MeetingNote::factory()->create([
        'meeting_id' => $meeting->id,
        'section_key' => 'development',
        'created_by' => $admin->id,
    ]);

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'development']);

    $component->assertSee('Dev Team Meeting');
});

it('shows meeting note content inline for matching department', function () {
    $admin = loginAsAdmin();
    $meeting = Meeting::factory()->withStatus(MeetingStatus::Completed)->create(['title' => 'Dev Team Meeting']);
    MeetingNote::factory()->create([
        'meeting_id' => $meeting->id,
        'section_key' => 'command',
        'content' => 'This is the meeting content.',
        'created_by' => $admin->id,
    ]);

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'command']);

    $component->assertSee('This is the meeting content.');
});

it('does not show pending meetings in notes display', function () {
    loginAsAdmin();
    Meeting::factory()->withStatus(MeetingStatus::Pending)->create(['title' => 'Upcoming Meeting']);
    Meeting::factory()->withStatus(MeetingStatus::Completed)->create(['title' => 'Past Meeting']);

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'development']);

    $component
        ->assertDontSee('Upcoming Meeting')
        ->assertSee('Past Meeting');
});

it('shows message when no meetings exist', function () {
    loginAsAdmin();

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'development']);

    $component->assertSee('No meetings found.');
});

it('paginates the meetings list', function () {
    loginAsAdmin();
    Meeting::factory()->withStatus(MeetingStatus::Completed)->count(11)->create();

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'development']);

    $component->assertSee('Next');
});

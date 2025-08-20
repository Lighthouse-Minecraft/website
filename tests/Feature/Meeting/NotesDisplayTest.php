<?php

use App\Livewire\Meeting\NotesDisplay;
use App\Models\Meeting;
use App\Models\MeetingNote;
use Livewire\Livewire;

it('can render the notes display component', function () {
    loginAsAdmin();

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'development']);

    $component->assertStatus(200);
});

it('displays meetings for the specified section key', function () {
    $admin = loginAsAdmin();
    $meeting = Meeting::factory()->create(['title' => 'Dev Team Meeting']);
    MeetingNote::factory()->create([
        'meeting_id' => $meeting->id,
        'section_key' => 'development',
        'created_by' => $admin->id,
    ]);

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'development']);

    $component->assertSee('Dev Team Meeting');
});

it('shows meeting note content when a meeting is selected', function () {
    $admin = loginAsAdmin();
    $meeting = Meeting::factory()->create(['title' => 'Dev Team Meeting']);
    $meetingNote = MeetingNote::factory()->create([
        'meeting_id' => $meeting->id,
        'section_key' => 'development',
        'content' => 'This is the meeting content.',
        'created_by' => $admin->id,
    ]);

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'development']);

    $component->call('selectMeeting', $meeting->id)
        ->assertSee('This is the meeting content.')
        ->assertSee($admin->name);
});

it('shows appropriate message when no meetings exist', function () {
    loginAsAdmin();

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'nonexistent']);

    $component->assertSee('No meetings found for this department.');
});

it('shows appropriate message when no notes exist for selected meeting', function () {
    loginAsAdmin();

    $meeting = Meeting::factory()->create(['title' => 'Meeting Without Notes']);

    $component = Livewire::test(NotesDisplay::class, ['sectionKey' => 'development']);

    $component->call('selectMeeting', $meeting->id)
        ->assertSee('No Notes Available')
        ->assertSee('No meeting notes found for this department');
});

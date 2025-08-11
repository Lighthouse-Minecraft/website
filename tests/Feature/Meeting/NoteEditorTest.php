<?php

use App\Models\Meeting;
use App\Models\MeetingNote;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->meeting = Meeting::factory()->create();
});

describe('Note Editor - Create Note', function () {
    it('shows a Create Note button if the note does not exist', function () {
        loginAsAdmin();

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->assertOk()
            ->assertSet('section_key', 'agenda')
            ->assertSet('meeting', $this->meeting)
            ->assertSeeText('Create Agenda');
    })->done();

    it('creates a database entry', function () {
        $user = loginAsAdmin();

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->call('CreateNote')
            ->assertOk();

        $this->assertDatabaseHas('meeting_notes', [
            'section_key' => 'agenda',
            'meeting_id' => $this->meeting->id,
            'created_by' => $user->id,
        ]);
    })->done();

    it('does not show the create note button if the note already exists', function () {
        $note = MeetingNote::factory()
            ->withMeeting($this->meeting)
            ->withSectionKey('agenda')
            ->create();

        loginAsAdmin();

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->assertDontSee('Create Agenda');
    })->done();

})->done(issue: 13, assignee: 'jonzenor');

describe('Note Editor - Lock The Note for Editing', function () {

    it('adds a lock for the current user when the note is created', function () {
        $user = loginAsAdmin();

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->call('CreateNote');

        $note = MeetingNote::first();

        expect($note->locked_at)->not->toBeNull();
        expect($note->lock_updated_at)->not->toBeNull();
        expect($note->locked_by)->toBe($user->id);
    })->done();

    it('displays the content and an edit button if the note is not locked', function () {
        loginAsAdmin();
        $note = MeetingNote::factory()->withMeeting($this->meeting)->withSectionKey('agenda')->create();

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->assertSee($note->content)
            ->assertSee('Edit Agenda');
    })->done();

    it('locks the note for the user when the Edit Note button is pressed', function () {
        $user = loginAsAdmin();
        $note = MeetingNote::factory()->withMeeting($this->meeting)->withSectionKey('agenda')->create();

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->call('EditNote')
            ->assertOk();

        $note = MeetingNote::first();

        expect($note->locked_at)->not->toBeNull();
        expect($note->lock_updated_at)->not->toBeNull();
        expect($note->locked_by)->toBe($user->id);
    })->done();

    it('shows the editor for the user if they have the lock', function () {
        $user = loginAsAdmin();
        $note = MeetingNote::factory()->withMeeting($this->meeting)->withSectionKey('agenda')->withLock($user)->create();

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->assertDontSee('Edit Agenda')
            ->assertSee('Locked by '.$user->name)
            ->assertSee('Save Agenda');
    })->done();

    // If a lock exists for another user, show an editing message

    // If a lock exists for another user, disable the edit button

    // If the lock is expired, allow another user to edit the note

    // If the note is not locked, show the note viewer

    // If the note is locked, show the note viewer for other users
})->wip(issue: 13, assignee: 'jonzenor');

describe('Note Editor - Save', function () {
    it('saves data when the Save Note method is used', function () {
        $user = loginAsAdmin();
        $note = MeetingNote::factory()->withMeeting($this->meeting)->withSectionKey('agenda')->withLock($user)->create();
        $updatedContent = 'Space: the final frontier. These are the voyages of the starship Enterprise.';

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->set('updatedContent', $updatedContent)
            ->call('SaveNote')
            ->assertOk();

        $this->assertDatabaseHas('meeting_notes', [
            'id' => $note->id,
            'content' => $updatedContent,
        ]);
    })->done();

    it('unlocks the note when the Save Note method is used', function () {
        $user = loginAsAdmin();
        $note = MeetingNote::factory()->withMeeting($this->meeting)->withSectionKey('agenda')->withLock($user)->create();

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->call('SaveNote')
            ->assertOk();

        $this->assertDatabaseHas('meeting_notes', [
            'id' => $note->id,
            'locked_by' => null,
            'locked_at' => null,
            'lock_updated_at' => null,
        ]);
    });
})->done(issue: 13, assignee: 'jonzenor');

// I'm not sure how we're going to test these....
// The editor saves the data

// The editor keeps the lock engaged

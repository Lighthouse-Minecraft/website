<?php

use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Models\User;
use Carbon\Carbon;

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
            ->assertSee('You have locked this section')
            ->assertSee('Save Agenda');
    })->done();

    it('shows the person who has the lock for the viewer', function () {
        $user = loginAsAdmin();
        $locker = User::factory()->create();
        $note = MeetingNote::factory()->withMeeting($this->meeting)->withSectionKey('agenda')->withLock($locker)->create();

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->assertSeeText('Locked by '.$locker->name);
    })->done();

    it('disables the edit button if a lock is held by another user', function () {
        $user = loginAsAdmin();
        $locker = User::factory()->create();
        $note = MeetingNote::factory()->withMeeting($this->meeting)->withSectionKey('agenda')->withLock($locker)->create();

        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->assertSeeInOrder(['Locked by', 'disabled', 'Edit Agenda']);
    })->done();

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
    })->done();

    it('saves the content and retains the lock when UpdateNote is called', function () {
        $user = loginAsAdmin();
        $currentTime = Carbon::now();
        $pastTime = $currentTime->subMinutes(1);
        $note = MeetingNote::factory()->withMeeting($this->meeting)->withSectionKey('agenda')->withLockAtTime($user, $pastTime)->create();

        $updatedContent = 'Peace is a lie. There is only Passion. Through Passion, I gain Strength. Through Strenght, I gain Power. Through Power, I gain Victory. Through Victory my chains are Broken. The Force shall free me.';
        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->set('updatedContent', $updatedContent)
            ->call('UpdateNote')
            ->assertOk();

        $this->assertDatabaseHas('meeting_notes', [
            'id' => $note->id,
            'locked_by' => $user->id,
            'content' => $updatedContent,
        ]);

        $note = MeetingNote::first();

        expect($note->locked_at)->not->toBeNull();
        expect($note->lock_updated_at)->not->toBeNull();
        expect($note->lock_updated_at)->toBeGreaterThan($note->locked_at);
    })->done();

    it('does not update the note if the content has not changed', function () {
        $user = loginAsAdmin();
        $currentTime = Carbon::now();
        $pastTime = $currentTime->subMinutes(1);
        $content = 'Indeed';
        $note = MeetingNote::factory()->withMeeting($this->meeting)->withContent($content)->withSectionKey('agenda')->withLockAtTime($user, $pastTime)->create();

        $updatedContent = 'Peace is a lie. There is only Passion. Through Passion, I gain Strength. Through Strenght, I gain Power. Through Power, I gain Victory. Through Victory my chains are Broken. The Force shall free me.';
        livewire('note.editor', ['meeting' => $this->meeting, 'section_key' => 'agenda'])
            ->call('UpdateNote')
            ->assertOk();

        $this->assertDatabaseHas('meeting_notes', [
            'id' => $note->id,
            'locked_by' => $user->id,
            'content' => $content,
            'lock_updated_at' => $pastTime,
        ]);
    })->wip();
})->wip(issue: 13, assignee: 'jonzenor');

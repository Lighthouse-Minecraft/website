<?php

use function Pest\Laravel\get;

describe('be accessed by each level of user', function () {
    it('loads the page without errors', function ($user) {
        loginAs($user);

        get(route('dashboard'))
            ->assertOk();
    })->with('memberAll')->wip();
})->wip(assignee: 'jonzenor');

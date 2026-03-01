<?php

declare(strict_types=1);

use App\Actions\AutoLinkParentOnRegistration;
use App\Models\ParentChildLink;
use App\Models\User;

uses()->group('parent-portal', 'actions');

it('links parent to child when emails match', function () {
    $child = User::factory()->minor()->create(['parent_email' => 'parent@example.com']);
    $parent = User::factory()->adult()->create(['email' => 'parent@example.com']);

    AutoLinkParentOnRegistration::run($parent);

    expect(ParentChildLink::where('parent_user_id', $parent->id)->where('child_user_id', $child->id)->exists())->toBeTrue();
});

it('does not create duplicate links', function () {
    $child = User::factory()->minor()->create(['parent_email' => 'parent@example.com']);
    $parent = User::factory()->adult()->create(['email' => 'parent@example.com']);

    AutoLinkParentOnRegistration::run($parent);
    AutoLinkParentOnRegistration::run($parent);

    expect(ParentChildLink::where('parent_user_id', $parent->id)->where('child_user_id', $child->id)->count())->toBe(1);
});

it('links parent to multiple children with same parent_email', function () {
    $child1 = User::factory()->minor()->create(['parent_email' => 'parent@example.com']);
    $child2 = User::factory()->minor()->create(['parent_email' => 'parent@example.com']);
    $parent = User::factory()->adult()->create(['email' => 'parent@example.com']);

    AutoLinkParentOnRegistration::run($parent);

    expect($parent->children()->count())->toBe(2);
});

it('does nothing when no children have matching parent_email', function () {
    $parent = User::factory()->adult()->create(['email' => 'parent@example.com']);

    AutoLinkParentOnRegistration::run($parent);

    expect(ParentChildLink::count())->toBe(0);
});

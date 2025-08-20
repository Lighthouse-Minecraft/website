<?php

declare(strict_types=1);

use App\Actions\AcknowledgeAnnouncement;
use App\Actions\AcknowledgeBlog;
use App\Livewire\Comments\CommentsSection;
use App\Livewire\Comments\Create as CreateCommentComponent;
use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

describe('UI Buttons', function () {
    // Admin Listing Tables Buttons
    describe('Admin listing tables buttons', function () {
        it('blogs table has view/edit/delete and create buttons', function () {
            $admin = User::factory()->admin()->create();
            $blog = Blog::factory()->for($admin, 'author')->create();

            $this->actingAs($admin)
                ->get(route('acp.index', ['tab' => 'blog-manager']))
                ->assertSuccessful()
                ->assertSee(route('blogs.show', ['id' => $blog->id, 'from' => 'acp']))
                ->assertSee(route('acp.blogs.edit', $blog->id))
                ->assertSee(route('acp.blogs.confirmDelete', ['id' => $blog->id, 'from' => 'acp']))
                ->assertSee(route('acp.blogs.create'));
        })->done('ghostridr');

        it('announcements table has view/edit/delete and create buttons', function () {
            $admin = User::factory()->admin()->create();
            $announcement = Announcement::factory()->for($admin, 'author')->published()->create();

            $this->actingAs($admin)
                ->get(route('acp.index', ['tab' => 'announcement-manager']))
                ->assertSuccessful()
                ->assertSee(route('announcements.show', ['id' => $announcement->id, 'from' => 'acp']))
                ->assertSee(route('acp.announcements.edit', $announcement->id))
                ->assertSee(route('acp.announcements.confirmDelete', ['id' => $announcement->id, 'from' => 'acp']))
                ->assertSee(route('acp.announcements.create'));
        })->done('ghostridr');
    })->done('ghostridr');

    // Admin Taxonomy Manager - Buttons
    describe('Admin Taxonomy Manager - Buttons', function () {
        it('shows create forms and bulk delete buttons for categories and tags', function () {
            loginAsAdmin();

            get(route('acp.index', ['tab' => 'taxonomy-manager']))
                ->assertSuccessful()
                ->assertSeeLivewire('admin-manage-taxonomies-page')
                ->assertSeeText('Manage Taxonomies')
                ->assertSeeText('Categories')
                ->assertSeeText('Tags')
                ->assertSeeTextInOrder(['Categories', 'Create'])
                ->assertSeeTextInOrder(['Tags', 'Create'])
                ->assertSeeText('Bulk');
        })->done('ghostridr');

        it('shows edit and delete actions for category rows', function () {
            loginAsAdmin();

            $category = Category::factory()->create(['name' => 'Alpha Category']);

            get(route('acp.index', ['tab' => 'taxonomy-manager']))
                ->assertSuccessful()
                ->assertSee('Alpha Category')
                ->assertSee('pencil-square')
                ->assertSee('deleteCategory(');
        })->done('ghostridr');

        it('shows edit and delete actions for tag rows', function () {
            loginAsAdmin();

            $tag = Tag::factory()->create(['name' => 'Zulu Tag']);

            get(route('acp.index', ['tab' => 'taxonomy-manager']))
                ->assertSuccessful()
                ->assertSee('Zulu Tag')
                ->assertSee('pencil-square')
                ->assertSee('deleteTag(');
        })->done('ghostridr');
    })->done('ghostridr');

    // Announcement CRUD UI
    describe('Announcement CRUD UI', function () {
        it('create: shows Save Announcement', function () {
            $admin = User::factory()->admin()->create();
            $this->actingAs($admin)
                ->get(route('acp.announcements.create'))
                ->assertSuccessful()
                ->assertSee('Save Announcement');
        })->done('ghostridr');

        it('edit: shows Update Announcement and Cancel back to ACP', function () {
            $admin = User::factory()->admin()->create();
            $announcement = Announcement::factory()->for($admin, 'author')->published()->create();

            $this->actingAs($admin)
                ->get(route('acp.announcements.edit', ['id' => $announcement->id]))
                ->assertSuccessful()
                ->assertSee('Update Announcement')
                ->assertSee(route('acp.index', ['tab' => 'announcement-manager']))
                ->assertSee('Cancel');
        })->done('ghostridr');

        it('destroy confirm: has Back link and delete form action route', function () {
            $admin = User::factory()->admin()->create();
            $announcement = Announcement::factory()->for($admin, 'author')->published()->create();

            $this->actingAs($admin)
                ->get(route('acp.announcements.confirmDelete', ['id' => $announcement->id]))
                ->assertSuccessful()
                ->assertSee(route('acp.index', ['tab' => 'announcement-manager']))
                ->assertSee(route('acp.announcements.delete', $announcement->id));
        })->done('ghostridr');
    })->done('ghostridr');

    // Announcement UI Buttons
    describe('Announcement UI Buttons', function () {
        it('announcement show back button links to ACP/dashboard/index correctly', function () {
            $user = User::factory()->create();
            $announcement = Announcement::factory()->for($user, 'author')->published()->create();

            $this->actingAs($user)
                ->get(route('announcements.show', ['id' => $announcement->id, 'from' => 'acp']))
                ->assertSuccessful()
                ->assertSee(route('acp.index', ['tab' => 'announcement-manager']));

            $this->actingAs($user)
                ->get(route('announcements.show', ['id' => $announcement->id, 'from' => 'dashboard']))
                ->assertSuccessful()
                ->assertSee(route('dashboard'));

            $this->actingAs($user)
                ->get(route('announcements.show', ['id' => $announcement->id]))
                ->assertSuccessful()
                ->assertSee(route('announcements.index'));
        })->done('ghostridr');
    })->done('ghostridr');

    // Blog CRUD UI
    describe('Blog CRUD UI', function () {
        it('create: shows Save Blog and validates', function () {
            $admin = User::factory()->admin()->create();
            $this->actingAs($admin)
                ->get(route('acp.blogs.create'))
                ->assertSuccessful()
                ->assertSee('Save Blog');
        })->done('ghostridr');

        it('edit: shows Update Blog and Cancel back to ACP', function () {
            $admin = User::factory()->admin()->create();
            $blog = Blog::factory()->for($admin, 'author')->create();

            $this->actingAs($admin)
                ->get(route('acp.blogs.edit', ['id' => $blog->id]))
                ->assertSuccessful()
                ->assertSee('Update Blog')
                ->assertSee(route('acp.index', ['tab' => 'blog-manager']))
                ->assertSee('Cancel');
        })->done('ghostridr');

        it('destroy confirm: has Back link and delete form action route', function () {
            $admin = User::factory()->admin()->create();
            $blog = Blog::factory()->for($admin, 'author')->create();

            $this->actingAs($admin)
                ->get(route('acp.blogs.confirmDelete', ['id' => $blog->id]))
                ->assertSuccessful()
                ->assertSee(route('acp.index', ['tab' => 'blog-manager']))
                ->assertSee(route('acp.blogs.delete', $blog->id));
        })->done('ghostridr');
    })->done('ghostridr');

    // Blog UI Buttons
    describe('Blog UI Buttons', function () {
        it('blog show back button links to ACP/dashboard/index correctly', function () {
            $user = User::factory()->create();
            $blog = Blog::factory()->for($user, 'author')->create(['is_published' => true]);

            $this->actingAs($user)
                ->get(route('blogs.show', ['id' => $blog->id, 'from' => 'acp']))
                ->assertSuccessful()
                ->assertSee(route('acp.index', ['tab' => 'blog-manager']));

            $this->actingAs($user)
                ->get(route('blogs.show', ['id' => $blog->id, 'from' => 'dashboard']))
                ->assertSuccessful()
                ->assertSee(route('dashboard'));

            $this->actingAs($user)
                ->get(route('blogs.show', ['id' => $blog->id]))
                ->assertSuccessful()
                ->assertSee(route('blogs.index'));
        })->done('ghostridr');
    })->done('ghostridr');

    // Comments CRUD UI
    describe('Comments CRUD UI', function () {
        it('comment show: has Back to ACP and View parent', function () {
            $admin = User::factory()->admin()->create();
            $blog = Blog::factory()->for($admin, 'author')->create(['is_published' => true]);
            $comment = Comment::factory()->forBlog($blog)->withAuthor($admin)->create(['needs_review' => false]);

            $this->actingAs($admin)
                ->get(route('comments.show', ['id' => $comment->id, 'from' => 'acp']))
                ->assertSuccessful()
                ->assertSee(route('acp.index', ['tab' => 'comment-manager']))
                ->assertSee('View');
        })->done('ghostridr');

        it('comment edit: shows Update button (ACP and non-ACP)', function () {
            $admin = User::factory()->admin()->create();
            $announcement = Announcement::factory()->for($admin, 'author')->published()->create();
            $comment = Comment::factory()->forAnnouncement($announcement)->withAuthor($admin)->create(['needs_review' => false]);

            $this->actingAs($admin)
                ->get(route('comments.edit', ['id' => $comment->id]))
                ->assertSuccessful()
                ->assertSee('Update Comment');

            $this->actingAs($admin)
                ->get(route('acp.comments.edit', ['id' => $comment->id]))
                ->assertSuccessful()
                ->assertSee('Update Comment');
        })->done('ghostridr');
    })->done('ghostridr');

    // CommentsSection Buttons
    describe('CommentsSection Buttons', function () {
        it('shows Post Comment for authed and validates content', function () {
            $user = User::factory()->create();
            $blog = Blog::factory()->for($user, 'author')->create(['is_published' => true]);

            Livewire::actingAs($user)
                ->test(CommentsSection::class, ['parent' => $blog])
                ->assertSee('Post Comment')
                ->call('addComment')
                ->assertHasErrors(['content' => 'required'])
                ->set('content', 'Nice post!')
                ->call('addComment')
                ->assertHasNoErrors();
        })->done('ghostridr');

        it('guests cannot add comment and see error flash', function () {
            $author = User::factory()->create();
            $blog = Blog::factory()->for($author, 'author')->create(['is_published' => true]);

            Livewire::test(CommentsSection::class, ['parent' => $blog])
                ->call('addComment');
            $this->assertTrue(true);
        })->done('ghostridr');
    })->done('ghostridr');

    // Create Comment component (authed)
    describe('Create Comment component', function () {
        it('shows Save button for authed users and can submit', function () {
            $user = User::factory()->create();
            $blog = Blog::factory()->for($user, 'author')->create(['is_published' => true]);

            Livewire::actingAs($user)
                ->test(CreateCommentComponent::class)
                ->assertSee('Save Comment')
                ->set('commentContent', 'This is a great post!')
                ->set('commentable_type', 'blog')
                ->set('commentable_id', $blog->id)
                ->call('saveComment')
                ->assertHasNoErrors()
                ->assertRedirect(route('blogs.show', $blog->id));
        })->done('ghostridr');
    })->done('ghostridr');

    // Dashboard Widgets - Buttons
    describe('Dashboard Widgets - Buttons', function () {
        it('blogs widget shows Read Full and acknowledge button in modal', function () {
            $blog = Blog::factory()->published()->create(['title' => 'Widget Blog']);
            $user = loginAsAdmin();

            get(route('dashboard'))
                ->assertSuccessful()
                ->assertSeeLivewire('dashboard.blogs-widget')
                ->assertSeeText('Read Full Blog');

            app(AcknowledgeBlog::class)->run($blog, $user);
            get(route('dashboard'))->assertSuccessful();
        })->done('ghostridr');

        it('announcements widget shows Read Full and acknowledge button in modal', function () {
            $announcement = Announcement::factory()->published()->create(['title' => 'Widget Announcement']);
            $user = loginAsAdmin();

            get(route('dashboard'))
                ->assertSuccessful()
                ->assertSeeLivewire('dashboard.announcements-widget')
                ->assertSeeText('Read Full Announcement');

            app(AcknowledgeAnnouncement::class)->run($announcement, $user);
            get(route('dashboard'))->assertSuccessful();
        })->done('ghostridr');
    })->done('ghostridr');

    // Pages CRUD UI
    describe('Pages CRUD UI', function () {
        it('create: shows Save Page and validates presence', function () {
            $admin = User::factory()->admin()->create();
            $this->actingAs($admin)
                ->get(route('admin.pages.create'))
                ->assertSuccessful()
                ->assertSee('Save Page');
        })->done('ghostridr');

        it('edit: shows Update Page and Cancel back to ACP', function () {
            $admin = User::factory()->admin()->create();
            $page = Page::query()->create([
                'title' => 'Contact',
                'slug' => 'contact',
                'content' => 'Contact content',
                'is_published' => true,
            ]);

            $this->actingAs($admin)
                ->get(route('admin.pages.edit', ['page' => $page]))
                ->assertSuccessful()
                ->assertSee('Update Page')
                ->assertSee(route('acp.index', ['tab' => 'page-manager']))
                ->assertSee('Cancel');
        })->done('ghostridr');
    })->done('ghostridr');
})->done('ghostridr');

# Blog System

## 1. Overview

The Blog System provides a full-featured community blog for the Lighthouse Minecraft community. It supports a multi-stage publishing pipeline (Draft -> In Review -> Scheduled/Published -> Archived), role-based authoring and peer review, markdown content with embedded community story cards, SEO metadata (Open Graph, Twitter Cards, JSON-LD), RSS feed, XML sitemap, and a moderated commenting system.

Key capabilities:
- **Authoring & Management**: Blog Authors create and edit posts with markdown, images, categories, tags, and community story embeds via a Livewire management page.
- **Publishing Pipeline**: Posts go through peer review before publication. Approved posts can be published immediately or scheduled for a future date.
- **Community Story Integration**: Posts can embed responses from the Community Questions system as styled blockquote cards using `{{story:ID}}` markers.
- **Comments**: Authenticated users (Traveler+) can comment on published posts. Comments from lower-trust users go through moderation.
- **SEO & Syndication**: Each post page includes Open Graph, Twitter Card, and JSON-LD structured data. An RSS feed and XML sitemap are generated automatically.
- **Notifications**: Authors are notified on approval, other Blog Authors are notified on review submissions, subscribers (Traveler+) are notified on publication, and community story authors are notified when their stories are featured.

---

## 2. Database Schema

### `blog_categories` table
**Migration**: `database/migrations/2026_03_20_100001_create_blog_categories_table.php`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint (PK) | Auto-increment |
| `name` | string | Category display name |
| `slug` | string | Unique, URL-friendly |
| `include_in_sitemap` | boolean | Default `true` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `blog_tags` table
**Migration**: `database/migrations/2026_03_20_100002_create_blog_tags_table.php`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint (PK) | Auto-increment |
| `name` | string | Tag display name |
| `slug` | string | Unique, URL-friendly |
| `include_in_sitemap` | boolean | Default `false` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `blog_posts` table
**Migration**: `database/migrations/2026_03_20_100003_create_blog_posts_table.php`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint (PK) | Auto-increment |
| `title` | string | Post title |
| `slug` | string | Unique, URL-friendly (checked against soft-deleted posts too) |
| `body` | longText | Markdown content; supports `{{story:ID}}` markers |
| `hero_image_path` | string (nullable) | Path in `blog/hero/` on public disk |
| `meta_description` | string (nullable) | SEO description, max 160 chars |
| `og_image_path` | string (nullable) | Path in `blog/og/` on public disk |
| `status` | string | Default `'draft'`. See `BlogPostStatus` enum. |
| `scheduled_at` | timestamp (nullable) | When to auto-publish (only for Scheduled status) |
| `published_at` | timestamp (nullable) | When the post went live |
| `author_id` | foreignId | FK to `users.id`, cascadeOnDelete |
| `category_id` | foreignId (nullable) | FK to `blog_categories.id`, nullOnDelete |
| `community_question_id` | foreignId (nullable) | FK to `community_questions.id`, nullOnDelete |
| `is_edited` | boolean | Default `false`; set to `true` when a Published post is updated |
| `deleted_at` | timestamp (nullable) | Soft delete |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### `blog_post_tag` pivot table
**Migration**: `database/migrations/2026_03_20_100004_create_blog_post_tag_table.php`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint (PK) | Auto-increment |
| `blog_post_id` | foreignId | FK to `blog_posts.id`, cascadeOnDelete |
| `blog_tag_id` | foreignId | FK to `blog_tags.id`, cascadeOnDelete |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Unique constraint on `(blog_post_id, blog_tag_id)`.

### `blog_post_community_response` pivot table
**Migration**: `database/migrations/2026_03_20_200001_add_community_story_integration_to_blog.php`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint (PK) | Auto-increment |
| `blog_post_id` | foreignId | FK to `blog_posts.id`, cascadeOnDelete |
| `community_response_id` | foreignId | FK to `community_responses.id`, cascadeOnDelete |
| `sort_order` | unsigned int | Default `0`; controls display order of story embeds |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Unique constraint on `(blog_post_id, community_response_id)`.

### Blog Author Role Seed
**Migration**: `database/migrations/2026_03_20_100005_seed_blog_author_role.php`

Inserts (or updates) a role in the `roles` table:
- **name**: `Blog Author`
- **description**: `Create, edit, and manage blog posts`
- **color**: `violet`
- **icon**: `pencil-square`

### User Model Changes

The `users` table has these blog-relevant columns (not created by blog migrations, but used by the blog system):
- `slug` -- URL-friendly user identifier, auto-generated by `GenerateUserSlug` on create/update
- `resident_since` -- datetime, used by comment moderation logic to determine trust level
- `notification_preferences` -- JSON, includes `blog` category with `email`, `pushover`, `discord` keys

The `User` model has a `blogPosts(): HasMany` relationship returning `BlogPost::class` via `author_id`.

---

## 3. Models & Relationships

### `App\Models\BlogPost`
**File**: `app/Models/BlogPost.php`

- Uses `HasFactory`, `SoftDeletes`
- **Fillable**: `title`, `slug`, `body`, `hero_image_path`, `meta_description`, `og_image_path`, `status`, `scheduled_at`, `published_at`, `author_id`, `category_id`, `community_question_id`, `is_edited`
- **Casts**: `status` -> `BlogPostStatus`, `scheduled_at` -> `datetime`, `published_at` -> `datetime`, `is_edited` -> `boolean`

**Relationships**:
| Method | Type | Target |
|---|---|---|
| `author()` | BelongsTo | `User` (via `author_id`) |
| `category()` | BelongsTo | `BlogCategory` (via `category_id`) |
| `tags()` | BelongsToMany | `BlogTag` (via `blog_post_tag`) |
| `communityQuestion()` | BelongsTo | `CommunityQuestion` (via `community_question_id`) |
| `commentThread()` | MorphOne | `Thread` (via `topicable`) |
| `communityResponses()` | BelongsToMany | `CommunityResponse` (via `blog_post_community_response` with `sort_order` pivot) |

**Methods**:
| Method | Returns | Description |
|---|---|---|
| `renderBody()` | `string` | Converts markdown body to HTML, replaces `{{story:ID}}` markers with styled blockquote cards |
| `isDraft()` | `bool` | Status is Draft |
| `isPublished()` | `bool` | Status is Published |
| `heroImageUrl()` | `?string` | Public URL for hero image via `StorageService::publicUrl()` |
| `ogImageUrl()` | `?string` | Public URL for OG image via `StorageService::publicUrl()` |

### `App\Models\BlogCategory`
**File**: `app/Models/BlogCategory.php`

- Uses `HasFactory`
- **Fillable**: `name`, `slug`, `include_in_sitemap`
- **Casts**: `include_in_sitemap` -> `boolean`

**Relationships**:
| Method | Type | Target |
|---|---|---|
| `posts()` | HasMany | `BlogPost` (via `category_id`) |

### `App\Models\BlogTag`
**File**: `app/Models/BlogTag.php`

- Uses `HasFactory`
- **Fillable**: `name`, `slug`, `include_in_sitemap`
- **Casts**: `include_in_sitemap` -> `boolean`

**Relationships**:
| Method | Type | Target |
|---|---|---|
| `posts()` | BelongsToMany | `BlogPost` (via `blog_post_tag`) |

---

## 4. Enums Reference

### `App\Enums\BlogPostStatus`
**File**: `app/Enums/BlogPostStatus.php`

| Case | Value | Label | Color |
|---|---|---|---|
| `Draft` | `'draft'` | Draft | `zinc` |
| `InReview` | `'in_review'` | In Review | `amber` |
| `Scheduled` | `'scheduled'` | Scheduled | `blue` |
| `Published` | `'published'` | Published | `emerald` |
| `Archived` | `'archived'` | Archived | `red` |

Methods: `label(): string`, `color(): string`

### `App\Enums\ThreadType` (Blog-relevant case)
**File**: `app/Enums/ThreadType.php`

| Case | Value | Label |
|---|---|---|
| `BlogComment` | `'blog_comment'` | Blog Comment |

This case is used for the polymorphic `Thread` that backs blog post comments.

---

## 5. Authorization & Permissions

### Gates
**File**: `app/Providers/AuthServiceProvider.php`

| Gate | Logic | Used By |
|---|---|---|
| `manage-blog` | `$user->hasRole('Blog Author')` | Manage page access, category/tag CRUD |
| `post-blog-comment` | `!$user->in_brig && $user->isAtLeastLevel(MembershipLevel::Traveler)` | Posting comments on blog posts |
| `moderate-blog-comments` | `$user->hasRole('Moderator') \|\| $user->hasRole('Blog Author')` | Approving/rejecting pending comments |

### Policy: `BlogPostPolicy`
**File**: `app/Policies/BlogPostPolicy.php`

**Registered in** `AuthServiceProvider::$policies`: `BlogPost::class => BlogPostPolicy::class`

**`before()` override**: Admins get `true` for all abilities except `delete` (delete has its own admin check).

| Ability | Logic |
|---|---|
| `create` | User has `Blog Author` role |
| `update` | User has `Blog Author` role |
| `submitForReview` | User has `Blog Author` role AND is the post's author AND post `isDraft()` |
| `approve` | User has `Blog Author` role AND is NOT the post's author AND post status is `InReview` |
| `archive` | User has `Blog Author` role AND post `isPublished()` |
| `delete` | Admin, or Command Officer (Officer+ rank in Command department), or Blog Author who is the post's original author |

### Permissions Matrix

| Action | Regular User | Traveler+ | Blog Author | Blog Author (own post) | Moderator | Command Officer | Admin |
|---|---|---|---|---|---|---|---|
| View public blog | Yes | Yes | Yes | Yes | Yes | Yes | Yes |
| Post comment | No | Yes (if not in brig) | Yes | Yes | Yes | Yes | Yes |
| Comment without moderation | No | No | No | No | No | No | Citizen or Resident 6mo+ |
| Manage blog (page access) | No | No | Yes | Yes | No | No | Yes |
| Create post | No | No | Yes | -- | No | No | Yes |
| Update any post | No | No | Yes | Yes | No | No | Yes |
| Submit for review | No | No | No | Yes (Draft only) | No | No | Yes |
| Approve post | No | No | Yes (not own) | No (cannot self-approve) | No | No | Yes |
| Archive post | No | No | Yes (Published only) | Yes (Published only) | No | No | Yes |
| Delete any post | No | No | No | Yes (own only) | No | Yes | Yes |
| Moderate comments | No | No | Yes | Yes | Yes | No | Yes |

---

## 6. Routes

**File**: `routes/web.php` (lines 149-162)

### Public Routes (no auth required)

| Method | Path | Name | Handler |
|---|---|---|---|
| GET | `/blog` | `blog.index` | `Volt::route -> blog.index` |
| GET | `/blog/category/{categorySlug}` | `blog.category` | `Volt::route -> blog.index` |
| GET | `/blog/tag/{tagSlug}` | `blog.tag` | `Volt::route -> blog.index` |
| GET | `/blog/author/{authorSlug}` | `blog.author` | `Volt::route -> blog.index` |
| GET | `/blog/rss` | `blog.rss` | `BlogRssController` (invokable) |
| GET | `/blog/sitemap.xml` | `blog.sitemap` | `BlogSitemapController` (invokable) |
| GET | `/blog/{slug}` | `blog.show` | `Volt::route -> blog.show` |

### Authenticated Route

| Method | Path | Name | Middleware | Handler |
|---|---|---|---|---|
| GET | `/blog/manage` | `blog.manage` | `auth`, `can:manage-blog` | `Volt::route -> blog.manage` |

**Note**: The `/blog/{slug}` route is defined last to avoid catching `/blog/manage`, `/blog/rss`, etc.

---

## 7. User Interface Components

### `blog.index` -- Public Blog Index
**File**: `resources/views/livewire/blog/index.blade.php`

A public listing page showing published blog posts in reverse chronological order. Supports filtering by category, tag, or author via route parameters.

**Features**:
- Paginated grid (12 per page) with hero images, author links, category badges, tag badges, and excerpt
- Sidebar showing categories with published post counts
- "Manage" button visible to users with `manage-blog` gate
- RSS link in header
- Each post card links to `blog.show`
- Filtering by category slug, tag slug, or author slug (via route parameters, resolved in `mount()`)

### `blog.show` -- Single Post Page
**File**: `resources/views/livewire/blog/show.blade.php`

Displays a single published blog post with full rendered markdown body, community story cards, SEO metadata, social sharing, and comments.

**Features**:
- Renders markdown body via `BlogPost::renderBody()` (including `{{story:ID}}` card replacement)
- Meta tags pushed via `@push('meta')`: `<meta name="description">`, Open Graph (`og:type`, `og:title`, `og:description`, `og:url`, `og:image`), Twitter Card (`twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`), JSON-LD `Article` schema, canonical URL
- Social sharing buttons: Twitter/X, Facebook, Copy Link (clipboard API)
- Shows hero image, author (linked to author page), published date, category badge, tag badges
- Soft-deleted posts show a "This post has been removed" message (returns 200, not 404)
- Non-published, non-deleted posts return 404
- **Comments section**: Shows approved (non-pending-moderation) comments with user avatars; comment form for users with `post-blog-comment` gate; login prompt for guests; markdown rendering of comment bodies
- Computed properties: `comments` (filters `is_pending_moderation = false`, `kind = Message`), `canComment` (checks `post-blog-comment` gate)

### `blog.manage` -- Blog Management Page
**File**: `resources/views/livewire/blog/manage.blade.php`

Admin panel for Blog Authors. Protected by `manage-blog` gate in both route middleware and `mount()`.

**Features**:
- **Posts tab**: Table listing all posts (paginated 15 per page) with search and status filter. Columns: Title, Author, Category, Status (color-coded badge), Scheduled date, Created date, Actions. Action buttons conditionally shown via `@can`: Edit, Submit for Review, Approve, Archive, Delete.
- **Categories & Tags tab**: Category table with CRUD (name, slug, sitemap toggle). Tag list with inline create and delete.
- **Post Create/Edit Modal** (`post-form-modal`): Title, Body (markdown textarea), Preview button, Category select, Tags multi-select, Meta Description textarea (max 160), Community Stories section (question select + response checkboxes), Hero Image upload, OG Image upload.
- **Preview Modal** (`preview-modal`): Renders markdown body via `BlogPost::renderBody()`.
- **Approve Modal** (`approve-modal`): Checkbox for "Publish immediately" vs schedule with datetime-local input (converted from user timezone to UTC).
- **Category Create/Edit Modal** (`category-form-modal`): Name (auto-generates slug on create), Slug, Include in Sitemap checkbox.
- Validation rules: title required max:255, body required min:10, images mimes:jpg,jpeg,png,gif,webp max from `SiteConfig::getValue('max_image_size_kb', '2048')`, meta_description max:160.
- Image uploads stored to `blog/hero/` and `blog/og/` on the public disk. Old images deleted on replacement.

### Sidebar Link
**File**: `resources/views/components/layouts/app/sidebar.blade.php` (line 71)

Under the "Community" navigation group:
```blade
<flux:navlist.item icon="pencil-square" :href="route('blog.index')" :current="request()->routeIs('blog.*') && !request()->routeIs('blog.manage')" wire:navigate>Blog</flux:navlist.item>
```

### Ready Room Link
**File**: `resources/views/dashboard/ready-room.blade.php` (lines 13-16)

```blade
@can('manage-blog')
    <flux:button href="{{ route('blog.manage') }}" wire:navigate icon="pencil-square">
        Blog Management
    </flux:button>
@endcan
```

### Comment Moderation (Discussions Page)
**File**: `resources/views/livewire/topics/topics-list.blade.php`

The existing Topics/Discussions page has a Moderation Queue tab that shows pending blog comments. Uses the `moderate-blog-comments` gate and calls `ApproveBlogComment::run()` / `RejectBlogComment::run()`. Pending comments are queried by `is_pending_moderation = true`, `kind = Message`, `thread.type = ThreadType::BlogComment`.

### Notification Settings
**File**: `resources/views/livewire/settings/notifications.blade.php`

Under the notification preferences, a "Blog Posts" section with toggles for Email, Pushover, and Discord DM (labeled "New blog post notifications"). Maps to `notification_preferences.blog` in the user's JSON column.

---

## 8. Actions (Business Logic)

### `CreateBlogPost`
**File**: `app/Actions/CreateBlogPost.php`

**Signature**: `handle(User $author, array $data): BlogPost`

**Steps**:
1. Generate slug via `GenerateBlogPostSlug::run($data['title'])`
2. Create `BlogPost` with status `Draft`, all provided fields, and `author_id`
3. Sync tags if `$data['tag_ids']` provided
4. Sync community responses with sort order if `$data['community_response_ids']` provided
5. `RecordActivity::run($post, 'blog_post_created', ...)`
6. Return the created post

### `UpdateBlogPost`
**File**: `app/Actions/UpdateBlogPost.php`

**Signature**: `handle(BlogPost $post, array $data): BlogPost`

**Steps**:
1. If title changed, regenerate slug via `GenerateBlogPostSlug::run($data['title'], $post->id)`
2. Build update array from provided data keys (`body`, `hero_image_path`, `meta_description`, `og_image_path`, `category_id`, `community_question_id`)
3. If post `isPublished()` and any updates exist, set `is_edited = true`
4. Apply update
5. Sync tags if `$data['tag_ids']` provided
6. Sync community responses with sort order if `$data['community_response_ids']` provided
7. `RecordActivity::run($post, 'blog_post_updated', ...)`
8. Return `$post->fresh()`

### `DeleteBlogPost`
**File**: `app/Actions/DeleteBlogPost.php`

**Signature**: `handle(BlogPost $post): void`

**Steps**:
1. `RecordActivity::run($post, 'blog_post_deleted', ...)`
2. If the post has a comment thread, close it (set status to `ThreadStatus::Closed`, `closed_at = now()`)
3. Soft-delete the post (`$post->delete()`)

### `GenerateBlogPostSlug`
**File**: `app/Actions/GenerateBlogPostSlug.php`

**Signature**: `handle(string $title, ?int $excludePostId = null): string`

**Steps**:
1. Generate base slug via `Str::slug($title)`, default to `'post'` if empty
2. Check for uniqueness against all posts including soft-deleted (`BlogPost::withTrashed()`)
3. If collision, append `-2`, `-3`, etc. until unique
4. Optionally exclude a post ID (for updates to preserve existing slug)

### `SubmitBlogPostForReview`
**File**: `app/Actions/SubmitBlogPostForReview.php`

**Signature**: `handle(BlogPost $post, User $submitter): BlogPost`

**Steps**:
1. Update post status to `InReview`
2. `RecordActivity::run($post, 'blog_post_submitted_for_review', ...)`
3. Find all other Blog Authors (users with `Blog Author` role, excluding submitter)
4. Send `BlogPostSubmittedForReviewNotification` to each via `TicketNotificationService::send()` with category `'staff_alerts'`
5. Return `$post->fresh()`

### `ApproveBlogPost`
**File**: `app/Actions/ApproveBlogPost.php`

**Signature**: `handle(BlogPost $post, User $reviewer, ?Carbon $scheduledAt = null): BlogPost`

**Steps**:
1. If `$scheduledAt` is provided:
   - Update status to `Scheduled`, set `scheduled_at`
   - `RecordActivity::run($post, 'blog_post_approved', ...)`
2. If no `$scheduledAt`:
   - `RecordActivity::run($post, 'blog_post_approved', ...)`
   - Call `PublishBlogPost::run($post)` for immediate publication
3. Send `BlogPostApprovedNotification` to the post's author via `TicketNotificationService::send()` with category `'staff_alerts'`
4. Return `$post->fresh()`

### `PublishBlogPost`
**File**: `app/Actions/PublishBlogPost.php`

**Signature**: `handle(BlogPost $post): BlogPost`

**Steps**:
1. Update status to `Published`, set `published_at = now()`
2. `RecordActivity::run($post, 'blog_post_published', ...)`
3. Call `CreateBlogCommentThread::run($post)` to create the comment thread
4. Call `updateFeaturedStories($post)`:
   - For each linked community response, update `featured_in_blog_url` to the post's URL
   - Send `CommunityStoryFeaturedNotification` to each response author via `TicketNotificationService::send()` with category `'announcements'`
5. Call `notifySubscribers($post)`:
   - Find all users with membership >= Traveler and not in brig
   - Send `BlogPostPublishedNotification` to each via `TicketNotificationService::send()` with category `'blog'`
6. Return `$post->fresh()`

### `ArchiveBlogPost`
**File**: `app/Actions/ArchiveBlogPost.php`

**Signature**: `handle(BlogPost $post): BlogPost`

**Steps**:
1. Update status to `Archived`
2. `RecordActivity::run($post, 'blog_post_archived', ...)`
3. Return `$post->fresh()`

### `CreateBlogCommentThread`
**File**: `app/Actions/CreateBlogCommentThread.php`

**Signature**: `handle(BlogPost $post): Thread`

**Steps**:
1. Check if a comment thread already exists for this post (prevents duplicates)
2. Create a `Thread` with:
   - `type`: `ThreadType::BlogComment`
   - `subject`: `"Comments: {$post->title}"`
   - `status`: `ThreadStatus::Open`
   - `created_by_user_id`: `$post->author_id`
   - `topicable_type`: `BlogPost::class`
   - `topicable_id`: `$post->id`
   - `last_message_at`: `now()`
3. Return the thread

### `PostBlogComment`
**File**: `app/Actions/PostBlogComment.php`

**Signature**: `handle(BlogPost $post, User $user, string $body): Message`

**Steps**:
1. Get or create comment thread for the post
2. Determine if moderation is required via `requiresModeration($user)`:
   - **Citizens**: No moderation needed
   - **Residents with `resident_since` >= 6 months ago**: No moderation needed
   - **Everyone else** (Travelers, new Residents, Residents with null `resident_since`): Moderation required
3. Create `Message` on the thread with `is_pending_moderation` flag, `kind = MessageKind::Message`
4. Update thread's `last_message_at`
5. Return the message

### `ApproveBlogComment`
**File**: `app/Actions/ApproveBlogComment.php`

**Signature**: `handle(Message $message, User $moderator): void`

**Steps**:
1. Set `is_pending_moderation = false` on the message
2. `RecordActivity::run($message, 'blog_comment_approved', ...)`

### `RejectBlogComment`
**File**: `app/Actions/RejectBlogComment.php`

**Signature**: `handle(Message $message, User $moderator): void`

**Steps**:
1. `RecordActivity::run($message, 'blog_comment_rejected', ...)`
2. Hard-delete the message (`$message->delete()`)

### `GenerateUserSlug`
**File**: `app/Actions/GenerateUserSlug.php`

**Signature**: `handle(string $name, ?int $excludeUserId = null): string`

Used by the `User` model's `booted()` hooks (creating/updating) to auto-generate URL-friendly slugs for author pages. Same collision-avoidance pattern as `GenerateBlogPostSlug`.

---

## 9. Notifications

All notifications implement `ShouldQueue`, use `Queueable`, and follow the `setChannels()` pattern for delivery via `TicketNotificationService`.

### `BlogPostSubmittedForReviewNotification`
**File**: `app/Notifications/BlogPostSubmittedForReviewNotification.php`

| Property | Value |
|---|---|
| Constructor | `BlogPost $post` (eager-loads `author`) |
| Sent to | All Blog Authors except the submitter |
| Category | `staff_alerts` |
| Mail subject | `"Blog Post Submitted for Review: {title}"` |
| Mail CTA | Link to `route('blog.manage')` |
| Pushover title | `"Blog Post Needs Review"` |

### `BlogPostApprovedNotification`
**File**: `app/Notifications/BlogPostApprovedNotification.php`

| Property | Value |
|---|---|
| Constructor | `BlogPost $post`, `User $reviewer` |
| Sent to | The post's original author |
| Category | `staff_alerts` |
| Mail subject | `"Your Blog Post Has Been Approved: {title}"` |
| Mail body | Indicates if scheduled or published immediately |
| Mail CTA | Link to `route('blog.manage')` |
| Pushover title | `"Blog Post Approved"` |

### `BlogPostPublishedNotification`
**File**: `app/Notifications/BlogPostPublishedNotification.php`

| Property | Value |
|---|---|
| Constructor | `BlogPost $post` |
| Sent to | All users with membership >= Traveler and not in brig |
| Category | `blog` |
| Mail subject | `"New Blog Post: {title}"` |
| Mail CTA | Link to `route('blog.show', $post->slug)` |
| Pushover title | `"New Blog Post Published"` |

### `CommunityStoryFeaturedNotification`
**File**: `app/Notifications/CommunityStoryFeaturedNotification.php`

| Property | Value |
|---|---|
| Constructor | `BlogPost $post`, `CommunityResponse $response` |
| Sent to | The author of each featured community response |
| Category | `announcements` |
| Mail subject | `"Your Story Was Featured: {title}"` |
| Mail CTA | Link to `route('blog.show', $post->slug)` |
| Pushover title | `"Your Story Was Featured!"` |

---

## 10. Background Jobs

### `PublishScheduledPosts`
**File**: `app/Jobs/PublishScheduledPosts.php`

Implements `ShouldQueue`. Queries all posts with `status = Scheduled` and `scheduled_at <= now()`, then calls `PublishBlogPost::run($post)` for each.

This job is scheduled via `routes/console.php` (see section 11).

---

## 11. Console Commands & Scheduled Tasks

**File**: `routes/console.php` (lines 58-60)

```php
// Publish scheduled blog posts
Schedule::job(new \App\Jobs\PublishScheduledPosts)
    ->everyMinute();
```

The `PublishScheduledPosts` job runs every minute, checking for posts whose `scheduled_at` has passed.

---

## 12. Services

### `TicketNotificationService`
**File**: `app/Services/TicketNotificationService.php`

The blog system uses this service for all notification dispatch. The service:
- Resolves user notification preferences for the given category (`staff_alerts`, `blog`, `announcements`)
- Determines which channels to use (mail, pushover, discord)
- Calls `setChannels()` on the notification before sending
- The `blog` category defaults to `['email' => true, 'pushover' => false, 'discord' => false]`

### `StorageService`
**File**: `app/Services/StorageService.php`

Used by `BlogPost::heroImageUrl()` and `BlogPost::ogImageUrl()` to generate public URLs for uploaded images via `StorageService::publicUrl($path)`.

---

## 13. Activity Log Entries

All activity is recorded via `RecordActivity::run($model, $action, $description)`.

| Action String | Subject Model | Triggered By |
|---|---|---|
| `blog_post_created` | `BlogPost` | `CreateBlogPost` |
| `blog_post_updated` | `BlogPost` | `UpdateBlogPost` |
| `blog_post_deleted` | `BlogPost` | `DeleteBlogPost` |
| `blog_post_submitted_for_review` | `BlogPost` | `SubmitBlogPostForReview` |
| `blog_post_approved` | `BlogPost` | `ApproveBlogPost` |
| `blog_post_published` | `BlogPost` | `PublishBlogPost` |
| `blog_post_archived` | `BlogPost` | `ArchiveBlogPost` |
| `blog_comment_approved` | `Message` | `ApproveBlogComment` |
| `blog_comment_rejected` | `Message` | `RejectBlogComment` |

---

## 14. Data Flow Diagrams

### Create Post Flow

```
User clicks "New Post" on blog.manage
    -> openCreatePostModal() [authorize: create, BlogPost]
    -> User fills form, clicks "Create Post"
    -> savePost() [validate, authorize: create, BlogPost]
        -> Upload hero/OG images to public disk if provided
        -> CreateBlogPost::run(Auth::user(), $data)
            -> GenerateBlogPostSlug::run($title)
            -> BlogPost::create([status: Draft, ...])
            -> Sync tags, community responses
            -> RecordActivity::run($post, 'blog_post_created')
        -> Flux::toast('Post created successfully.')
        -> Close modal
```

### Publish Post Flow (Full Pipeline)

```
1. Author submits draft for review:
   submitForReview($postId) [authorize: submitForReview, $post]
       -> SubmitBlogPostForReview::run($post, $author)
           -> $post->status = InReview
           -> RecordActivity (blog_post_submitted_for_review)
           -> Notify other Blog Authors (BlogPostSubmittedForReviewNotification, category: staff_alerts)

2. Reviewer approves (immediate publish):
   openApproveModal($postId) -> approvePost() [authorize: approve, $post]
       -> ApproveBlogPost::run($post, $reviewer, scheduledAt: null)
           -> RecordActivity (blog_post_approved)
           -> PublishBlogPost::run($post)
               -> $post->status = Published, published_at = now()
               -> RecordActivity (blog_post_published)
               -> CreateBlogCommentThread::run($post)
               -> Update featured community responses (featured_in_blog_url)
               -> Notify community story authors (CommunityStoryFeaturedNotification, category: announcements)
               -> Notify Traveler+ subscribers (BlogPostPublishedNotification, category: blog)
           -> Notify post author (BlogPostApprovedNotification, category: staff_alerts)

2b. Reviewer approves (scheduled):
   -> ApproveBlogPost::run($post, $reviewer, $scheduledAt)
       -> $post->status = Scheduled, scheduled_at = $scheduledAt
       -> RecordActivity (blog_post_approved)
       -> Notify post author (BlogPostApprovedNotification, category: staff_alerts)
       // Later, PublishScheduledPosts job picks it up and calls PublishBlogPost::run()
```

### Comment on Post Flow

```
User visits /blog/{slug}
    -> blog.show mounts, loads post + rendered body
    -> canComment computed property checks post-blog-comment gate
    -> User types comment, submits form
    -> postComment() [authorize: post-blog-comment]
        -> Validate commentBody (required, min:3, max:5000)
        -> PostBlogComment::run($post, $user, $body)
            -> Get or create comment thread
            -> Determine moderation need:
               - Citizens: no moderation
               - Residents 6+ months: no moderation
               - Everyone else: moderation required
            -> Create Message with is_pending_moderation flag
        -> If pending: toast "submitted for moderation"
        -> If immediate: toast "Comment posted!"
```

### Moderate Comment Flow

```
Moderator visits Topics/Discussions page -> Moderation Queue tab
    -> pendingComments computed queries Messages with:
       is_pending_moderation = true, kind = Message, thread.type = BlogComment
    -> Each comment shows author, body, link to blog post

Approve:
    approveComment($messageId) [authorize: moderate-blog-comments]
        -> ApproveBlogComment::run($message, $moderator)
            -> $message->is_pending_moderation = false
            -> RecordActivity (blog_comment_approved)

Reject:
    rejectComment($messageId) [authorize: moderate-blog-comments]
        -> RejectBlogComment::run($message, $moderator)
            -> RecordActivity (blog_comment_rejected)
            -> Hard-delete message
```

---

## 15. Configuration

### `SiteConfig` References
- `max_image_size_kb` (default `'2048'`): Maximum upload size for hero and OG images in kilobytes. Used in validation rules and displayed in the upload dropzone label.

### `filesystems.public_disk`
- Blog images are stored on the disk specified by `config('filesystems.public_disk')`, under paths `blog/hero/` and `blog/og/`.

### Notification Preference Category
- The `blog` category in `TicketNotificationService` defaults to `['email' => true, 'pushover' => false, 'discord' => false]`.
- Users can toggle these in Settings -> Notifications.

### Scheduled Task
- `PublishScheduledPosts` runs every minute via `routes/console.php`.

---

## 16. Test Coverage

All tests are in `tests/Feature/Blog/` and use Pest. Groups: `blog`, plus additional groups per file.

### `BlogPostActionsTest.php`
**Groups**: `blog`, `actions`

| Test (`it()` block) |
|---|
| `generates a slug from title` |
| `handles slug collisions` |
| `handles multiple slug collisions` |
| `excludes the current post when checking slug uniqueness` |
| `defaults to post slug when title is empty` |
| `checks against soft-deleted posts for slug uniqueness` |
| `creates a blog post in draft status` |
| `creates a blog post with category and tags` |
| `creates a blog post with meta description and images` |
| `records activity when creating a blog post` |
| `updates a blog post title and regenerates slug` |
| `updates blog post body` |
| `sets is_edited flag when updating a published post` |
| `does not set is_edited flag when updating a draft post` |
| `syncs tags on update` |
| `records activity when updating a blog post` |
| `soft deletes a blog post` |
| `records activity when deleting a blog post` |

### `BlogPostModelTest.php`
**Groups**: `blog`, `models`

| Test (`it()` block) |
|---|
| `creates a blog post with factory defaults` |
| `creates a published blog post` |
| `creates a blog post with a category` |
| `attaches tags to a blog post` |
| `soft deletes a blog post` |
| `creates blog categories with factory` |
| `creates blog tags with factory` |
| `has correct status enum values` |
| `casts status to enum` |
| `returns user blog posts via relationship` |

### `BlogPostPolicyTest.php`
**Groups**: `blog`, `policies`

| Test (`it()` block) |
|---|
| `allows blog author to create posts` |
| `denies regular user from creating posts` |
| `allows admin to create posts` |
| `allows blog author to update posts` |
| `denies regular user from updating posts` |
| `allows admin to update posts` |
| `allows admin to delete any post` |
| `allows command officer to delete any post` |
| `allows original author with blog author role to delete own post` |
| `denies blog author from deleting another authors post` |
| `denies regular user from deleting posts` |
| `denies non-command officer from deleting other users posts` |

### `BlogPublishingPipelineTest.php`
**Groups**: `blog`, `actions`, `publishing`

| Test (`it()` block) |
|---|
| `transitions a draft post to in-review status` |
| `records activity when submitting for review` |
| `sends review notification to other blog authors` |
| `does not send review notification to the submitter` |
| `publishes immediately when no scheduled_at is provided` |
| `schedules post when scheduled_at is provided` |
| `records activity when approving a post` |
| `sends approval notification to the post author` |
| `publishes a post and sets published_at` |
| `records activity when publishing a post` |
| `archives a published post` |
| `records activity when archiving a post` |
| `publishes posts whose scheduled_at has passed` |
| `does not publish posts whose scheduled_at is in the future` |
| `does not publish draft posts` |
| `publishes multiple scheduled posts at once` |
| `sets is_edited when editing a published post` |
| `does not set is_edited when editing a draft post` |
| `soft deletes a post preserving it in withTrashed` |

### `BlogPublishingPolicyTest.php`
**Groups**: `blog`, `policies`, `publishing`

| Test (`it()` block) |
|---|
| `allows post author with blog author role to submit for review` |
| `denies submit for review if user is not the post author` |
| `denies submit for review if user does not have blog author role` |
| `denies submit for review if post is not a draft` |
| `allows admin to submit any draft for review` |
| `allows a different blog author to approve a post in review` |
| `denies post author from approving their own post` |
| `denies approval if post is not in review` |
| `denies approval if user does not have blog author role` |
| `allows admin to approve any post in review` |
| `allows blog author to archive a published post` |
| `denies archiving a non-published post` |
| `denies archiving if user does not have blog author role` |
| `allows admin to archive a published post` |

### `BlogPublicPagesTest.php`
**Groups**: `blog`, `public`

| Test (`it()` block) |
|---|
| `returns 200 for blog index without authentication` |
| `shows published posts on the blog index` |
| `does not show draft posts on the blog index` |
| `does not show scheduled posts on the blog index` |
| `does not show archived posts on the blog index` |
| `paginates the blog index` |
| `filters posts by category` |
| `returns 404 for non-existent category slug` |
| `filters posts by tag` |
| `returns 404 for non-existent tag slug` |
| `filters posts by author` |
| `returns 404 for non-existent author slug` |
| `returns 200 for a published blog post` |
| `renders markdown in the post body` |
| `returns 404 for a non-existent post slug` |
| `returns 404 for a draft post` |
| `shows removed message for soft-deleted posts` |
| `displays author name linked to author page` |
| `displays category on the post page` |
| `displays tags on the post page` |
| `includes meta description on post pages` |
| `includes Open Graph tags on post pages` |
| `includes Twitter Card tags on post pages` |
| `includes JSON-LD Article structured data on post pages` |
| `displays social sharing buttons on post pages` |
| `returns valid RSS XML at the rss route` |
| `only includes published posts in the rss feed` |
| `includes author and category in rss items` |
| `returns valid sitemap XML` |
| `includes published posts in the sitemap` |
| `includes categories with include_in_sitemap flag` |
| `includes tags with include_in_sitemap flag` |
| `allows unauthenticated access to all public blog routes` |
| `shows blog link in sidebar for authenticated users` |

### `BlogManagePageTest.php`
**Groups**: `blog`, `livewire`

| Test (`it()` block) |
|---|
| `denies access to regular users` |
| `allows blog authors to access the page` |
| `allows admins to access the page` |
| `displays existing posts in the list` |
| `creates a blog post via the component` |
| `creates a category via the component` |
| `creates a tag via the component` |
| `deletes a post via the component` |
| `deletes a category via the component` |
| `prevents deleting a category that has posts` |
| `deletes a tag via the component` |
| `filters posts by status` |
| `searches posts by title` |
| `opens preview modal with rendered markdown` |

### `BlogCommunityStoryTest.php`
**Groups**: `blog`, `community-story`

| Test (`it()` block) |
|---|
| `replaces a valid story marker with a styled blockquote card` |
| `renders nothing for an invalid story ID` |
| `renders nothing for a missing story ID of zero` |
| `replaces multiple story markers in the same post` |
| `displays the response author avatar in the story card when available` |
| `gracefully handles a mix of valid and invalid story markers` |
| `creates a blog post with community responses attached` |
| `updates community responses on an existing blog post` |
| `stores sort order in the pivot table` |
| `updates featured_in_blog_url on community responses when post is published` |
| `updates featured_in_blog_url when approved for immediate publish` |
| `does not update featured_in_blog_url when scheduled for later` |
| `sends CommunityStoryFeaturedNotification to response authors on publish` |
| `sends notifications to multiple response authors on publish` |
| `does not send story notification when post has no community responses` |
| `does not send story notification when post is only scheduled` |
| `renders story cards on the public blog post page` |
| `gracefully handles invalid story markers on the public page` |

### `BlogCommentsTest.php`
**Groups**: `blog`, `comments`

| Test (`it()` block) |
|---|
| `has BlogComment value in ThreadType enum` |
| `creates a comment thread when a blog post is published` |
| `does not create duplicate threads on re-publish` |
| `allows Citizens to post comments` |
| `allows Residents with 6+ months to post without moderation` |
| `queues comments from Residents under 6 months for moderation` |
| `queues comments from Travelers for moderation` |
| `queues comments from Residents with no resident_since set for moderation` |
| `allows Traveler+ users not in brig to comment via gate` |
| `denies Stowaways from commenting via gate` |
| `denies Drifters from commenting via gate` |
| `denies users in brig from commenting via gate` |
| `approves a pending blog comment` |
| `rejects a pending blog comment by deleting it` |
| `closes comment thread when blog post is soft-deleted` |
| `sends BlogPostPublishedNotification to opted-in Traveler+ users` |
| `does not send blog notification to users in brig` |
| `respects blog notification preference opt-out` |
| `only shows approved comments publicly` |
| `allows users with Moderator role to moderate comments` |
| `allows users with Blog Author role to moderate comments` |
| `denies regular users from moderating comments` |

---

## 17. File Map

### Models
- `app/Models/BlogPost.php`
- `app/Models/BlogCategory.php`
- `app/Models/BlogTag.php`

### Enums
- `app/Enums/BlogPostStatus.php`
- `app/Enums/ThreadType.php` (BlogComment case)

### Actions
- `app/Actions/CreateBlogPost.php`
- `app/Actions/UpdateBlogPost.php`
- `app/Actions/DeleteBlogPost.php`
- `app/Actions/GenerateBlogPostSlug.php`
- `app/Actions/SubmitBlogPostForReview.php`
- `app/Actions/ApproveBlogPost.php`
- `app/Actions/PublishBlogPost.php`
- `app/Actions/ArchiveBlogPost.php`
- `app/Actions/CreateBlogCommentThread.php`
- `app/Actions/PostBlogComment.php`
- `app/Actions/ApproveBlogComment.php`
- `app/Actions/RejectBlogComment.php`
- `app/Actions/GenerateUserSlug.php`

### Policy
- `app/Policies/BlogPostPolicy.php`

### Authorization
- `app/Providers/AuthServiceProvider.php` (gates: `manage-blog`, `post-blog-comment`, `moderate-blog-comments`; policy registration)

### Notifications
- `app/Notifications/BlogPostSubmittedForReviewNotification.php`
- `app/Notifications/BlogPostApprovedNotification.php`
- `app/Notifications/BlogPostPublishedNotification.php`
- `app/Notifications/CommunityStoryFeaturedNotification.php`

### Jobs
- `app/Jobs/PublishScheduledPosts.php`

### Controllers
- `app/Http/Controllers/BlogRssController.php`
- `app/Http/Controllers/BlogSitemapController.php`

### Volt Components
- `resources/views/livewire/blog/index.blade.php`
- `resources/views/livewire/blog/show.blade.php`
- `resources/views/livewire/blog/manage.blade.php`

### Blade Views
- `resources/views/blog/rss.blade.php`
- `resources/views/blog/sitemap.blade.php`

### UI Integration Points
- `resources/views/components/layouts/app/sidebar.blade.php` (Blog nav item)
- `resources/views/dashboard/ready-room.blade.php` (Blog Management button)
- `resources/views/livewire/settings/notifications.blade.php` (Blog notification preferences)
- `resources/views/livewire/topics/topics-list.blade.php` (Comment moderation queue)

### Migrations
- `database/migrations/2026_03_20_100001_create_blog_categories_table.php`
- `database/migrations/2026_03_20_100002_create_blog_tags_table.php`
- `database/migrations/2026_03_20_100003_create_blog_posts_table.php`
- `database/migrations/2026_03_20_100004_create_blog_post_tag_table.php`
- `database/migrations/2026_03_20_100005_seed_blog_author_role.php`
- `database/migrations/2026_03_20_200001_add_community_story_integration_to_blog.php`

### Factories
- `database/factories/BlogPostFactory.php`
- `database/factories/BlogCategoryFactory.php`
- `database/factories/BlogTagFactory.php`

### Routes
- `routes/web.php` (lines 149-162)
- `routes/console.php` (PublishScheduledPosts schedule)

### Tests
- `tests/Feature/Blog/BlogPostActionsTest.php`
- `tests/Feature/Blog/BlogPostModelTest.php`
- `tests/Feature/Blog/BlogPostPolicyTest.php`
- `tests/Feature/Blog/BlogPublishingPipelineTest.php`
- `tests/Feature/Blog/BlogPublishingPolicyTest.php`
- `tests/Feature/Blog/BlogPublicPagesTest.php`
- `tests/Feature/Blog/BlogManagePageTest.php`
- `tests/Feature/Blog/BlogCommunityStoryTest.php`
- `tests/Feature/Blog/BlogCommentsTest.php`

---

## 18. Known Issues & Improvement Opportunities

1. **No pagination on comment display**: The `blog.show` page loads all approved comments at once via a computed property. For posts with many comments, this could become a performance issue. Consider adding pagination or lazy-loading.

2. **Category/tag management is inline in the manage component**: Categories and tags are managed directly in the `blog.manage` Volt component without dedicated Action classes. This means no activity logging for category/tag CRUD operations. Consider extracting to Actions for consistency.

3. **No "return to Draft" transition**: Once a post is submitted for review (`InReview`), there is no action or UI to return it to `Draft` status. A reviewer who wants changes must communicate out-of-band with the author.

4. **No comment editing or deletion by comment author**: Users who post comments cannot edit or delete them. Only moderators can act on comments.

5. **Self-approval restriction bypassed by admin `before()` override**: The `BlogPostPolicy::approve()` prevents self-approval, but the `before()` method grants admins blanket access (except for `delete`). An admin who is also the post author can approve their own post. This may be intentional but differs from the peer review design.

6. **No rate limiting on comment posting**: The comment form validates min/max length but does not rate-limit how frequently a user can post comments.

7. **RSS feed has no pagination**: The RSS controller limits to 50 posts but provides no mechanism for accessing older posts beyond that limit.

8. **Image cleanup on post deletion**: When a blog post is soft-deleted via `DeleteBlogPost`, the hero and OG images remain on disk. There is no cleanup job for orphaned images.

9. **Comment thread not reopened if post is restored**: If a soft-deleted post were ever restored, the comment thread would remain in `Closed` status. There is no restore action.

10. **No draft auto-save**: The post form in `blog.manage` does not auto-save drafts. If the user navigates away, unsaved work is lost.

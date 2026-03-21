# Blog Image Management

## 1. Overview

Blog Image Management provides a centralized system for uploading, organizing, referencing, and cleaning up images used in blog posts. Rather than storing raw image URLs in post bodies, images are managed as first-class database records (`BlogImage`) with metadata (title, alt text, uploader). Images are embedded in post bodies via a custom tag syntax (`{{image:ID}}`), and can also serve as hero images or Open Graph (OG) images for posts.

The system tracks which images are referenced by which posts through a many-to-many pivot table. When an image loses all references, it enters a 30-day grace period before automatic cleanup deletes it from both the database and file storage. This prevents orphaned files from accumulating while giving authors time to re-reference images.

**Key capabilities:**
- Upload images with required title and alt text metadata
- Embed images in post bodies using `{{image:ID}}` or `{{image:ID|custom alt text}}` syntax
- Select images as hero image or OG image for a post via modal pickers
- Browse and search an image gallery from the blog editor
- Admin Control Panel tab for managing all blog images (search, view usage, delete)
- Automatic reference tracking via pivot table sync on create/update/delete
- Background cleanup of unreferenced images after a 30-day grace period
- Activity logging for upload and delete events

---

## 2. Database Schema

### `blog_images` table

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `bigint` (PK) | auto-increment | Primary key |
| `title` | `string` | required | Human-readable name for the image |
| `alt_text` | `string` | required | Accessibility/SEO alt text |
| `path` | `string` | required | Storage path (e.g., `blog/images/{uuid}.jpg`) |
| `uploaded_by` | `foreignId` | constrained to `users` | ID of the user who uploaded the image |
| `unreferenced_at` | `datetime` | nullable | Timestamp when the image lost all post references; null while referenced |
| `created_at` | `timestamp` | auto | |
| `updated_at` | `timestamp` | auto | |

**Migration:** `database/migrations/2026_03_20_300001_create_blog_images_table.php`

### `blog_image_post` pivot table

| Column | Type | Constraints | Description |
|---|---|---|---|
| `blog_image_id` | `foreignId` | constrained to `blog_images`, cascadeOnDelete | |
| `blog_post_id` | `foreignId` | constrained to `blog_posts`, cascadeOnDelete | |
| `created_at` | `timestamp` | nullable | When the reference was established |

**Primary key:** composite `(blog_image_id, blog_post_id)`

**Migration:** `database/migrations/2026_03_20_300001_create_blog_images_table.php` (same migration creates both tables)

### `blog_posts` table modifications

| Column | Type | Constraints | Description |
|---|---|---|---|
| `hero_image_id` | `foreignId` | nullable, constrained to `blog_images`, nullOnDelete | Hero/banner image for the post |
| `og_image_id` | `foreignId` | nullable, constrained to `blog_images`, nullOnDelete | Open Graph image for social sharing |

These columns replaced the former `hero_image_path` and `og_image_path` string columns.

**Migration:** `database/migrations/2026_03_20_400001_replace_blog_post_image_paths_with_fks.php`

---

## 3. Models & Relationships

### `BlogImage` (`app/Models/BlogImage.php`)

**Fillable:** `title`, `alt_text`, `path`, `uploaded_by`, `unreferenced_at`

**Casts:**
- `unreferenced_at` => `datetime`

**Relationships:**

| Method | Type | Related Model | Foreign Key / Pivot |
|---|---|---|---|
| `uploadedBy()` | `BelongsTo` | `User` | `uploaded_by` |
| `posts()` | `BelongsToMany` | `BlogPost` | pivot: `blog_image_post`, with pivot `created_at` |

**Methods:**

| Method | Returns | Description |
|---|---|---|
| `url()` | `string` | Public URL via `StorageService::publicUrl($this->path)` |

### `BlogPost` (`app/Models/BlogPost.php`) -- image-related parts

**Relationships:**

| Method | Type | Related Model | Foreign Key / Pivot |
|---|---|---|---|
| `heroImage()` | `BelongsTo` | `BlogImage` | `hero_image_id` |
| `ogImage()` | `BelongsTo` | `BlogImage` | `og_image_id` |
| `images()` | `BelongsToMany` | `BlogImage` | pivot: `blog_image_post`, with pivot `created_at` |

**Methods:**

| Method | Returns | Description |
|---|---|---|
| `renderBody()` | `string` | Converts Markdown to HTML and resolves `{{image:ID}}` / `{{image:ID\|alt}}` tags into `<img>` elements |
| `heroImageUrl()` | `?string` | Returns the hero image's public URL, or null if no hero image is set |
| `ogImageUrl()` | `?string` | Returns the OG image's public URL, or null if no OG image is set |

**Image tag resolution in `renderBody()`:**

The method uses a regex callback (`/\{\{image:(\d+)(?:\|([^}]+))?\}\}/`) to find image tags. For each match:
1. Looks up the `BlogImage` by ID
2. If not found, renders an empty string
3. If found, renders `<img src="..." alt="..." class="rounded-lg" />`
4. Alt text defaults to the image's `alt_text` field but can be overridden via the `|custom alt` pipe syntax

---

## 4. Enums Reference

No enums are specific to Blog Image Management. The feature uses `BlogPostStatus` from the broader blog system but does not define its own enums.

---

## 5. Authorization & Permissions

### Gate: `manage-blog`

**Defined in:** `app/Providers/AuthServiceProvider.php`

```php
Gate::define('manage-blog', function ($user) {
    return $user->hasRole('Blog Author');
});
```

This gate controls all image management operations:
- Uploading images (via editor)
- Browsing the image gallery
- Inserting images into post bodies
- Selecting/uploading hero and OG images
- Deleting images from the ACP Blog Images tab

### Policy: `BlogPostPolicy` (`app/Policies/BlogPostPolicy.php`)

The `BlogPostPolicy` is not directly used for image operations, but it governs access to the blog editor where images are uploaded and selected. Relevant abilities:
- `create` -- requires `Blog Author` role
- `update` -- requires `Blog Author` role

The policy's `before()` method grants Admin users access to all abilities except `delete`.

### Authorization enforcement points

| Location | Gate/Policy | Method |
|---|---|---|
| `blog/editor.blade.php` `mount()` | `manage-blog` | `$this->authorize('manage-blog')` |
| `blog/editor.blade.php` `uploadBlogImage()` | `manage-blog` | `$this->authorize('manage-blog')` |
| `blog/editor.blade.php` `insertGalleryImage()` | `manage-blog` | `$this->authorize('manage-blog')` |
| `blog/editor.blade.php` `selectHeroImage()` | `manage-blog` | `$this->authorize('manage-blog')` |
| `blog/editor.blade.php` `uploadHeroImage()` | `manage-blog` | `$this->authorize('manage-blog')` |
| `blog/editor.blade.php` `selectOgImage()` | `manage-blog` | `$this->authorize('manage-blog')` |
| `blog/editor.blade.php` `uploadOgImage()` | `manage-blog` | `$this->authorize('manage-blog')` |
| `admin-manage-blog-images-page.blade.php` `deleteImage()` | `manage-blog` | `$this->authorize('manage-blog')` |
| `admin-control-panel-tabs.blade.php` | `manage-blog` | `@can('manage-blog')` wraps the Blog Images tab |
| Route: `/blog/create` | `manage-blog` | middleware `can:manage-blog` |
| Route: `/blog/{post}/edit` | `manage-blog` | middleware `can:manage-blog` |

---

## 6. Routes

### Web Routes (`routes/web.php`)

Blog image management does not have dedicated routes. Images are managed through the blog editor and ACP, which have these routes:

| Method | URI | Name | Middleware | Component |
|---|---|---|---|---|
| GET | `/blog/create` | `blog.create` | `auth`, `can:manage-blog` | `blog.editor` |
| GET | `/blog/{post}/edit` | `blog.edit` | `auth`, `can:manage-blog` | `blog.editor` |
| GET | `/acp` | `acp.index` | `auth` | Admin Control Panel (contains Blog Images tab) |

### Console/Scheduled (`routes/console.php`)

| Schedule | Job | Frequency |
|---|---|---|
| `Schedule::job(new CleanupUnreferencedBlogImages)` | `CleanupUnreferencedBlogImages` | Monthly |

---

## 7. User Interface Components

### Blog Editor (`resources/views/livewire/blog/editor.blade.php`)

A Livewire Volt component that provides the full blog post editing experience. The image-related UI elements are:

#### Upload Image Modal (`blog-image-upload-modal`)
- **Trigger:** "Upload Image" button below the body textarea
- **Fields:** Title (required), Alt Text (required), Image File (required)
- **Accepted formats:** JPG, JPEG, PNG, GIF, WEBP
- **Max size:** Configured via `SiteConfig::getValue('max_image_size_kb', '2048')`
- **Action:** `uploadBlogImage()` -- uploads via `UploadBlogImage` action, appends `{{image:ID}}` to post body
- **Toast:** "Image uploaded and inserted into post body."

#### Image Gallery Modal (`blog-image-gallery-modal`)
- **Trigger:** "Browse Images" button below the body textarea
- **Layout:** Grid of image cards (2-4 columns responsive), scrollable
- **Search:** Live search by title with 300ms debounce
- **Per-image display:** Thumbnail, title, alt text, "Insert" button
- **Action:** `insertGalleryImage($imageId)` -- appends `{{image:ID}}` to post body
- **Empty state:** "No images match your search." or "No images have been uploaded yet."

#### Hero Image Picker (`hero-image-picker-modal`)
- **Location:** Images card in the editor
- **Current selection display:** Thumbnail preview with title, "Change" and "Remove" buttons
- **Empty state:** Dashed border button "Select Hero Image"
- **Modal contents:** Search field, image grid (selected image highlighted with blue ring), upload form at bottom
- **Actions:** `selectHeroImage($imageId)`, `removeHeroImage()`, `uploadHeroImage()`
- **Upload fields:** Title, Alt Text, Image File (same validation as upload modal)

#### OG Image Picker (`og-image-picker-modal`)
- **Location:** Images card in the editor, alongside hero image picker
- **Behavior:** Identical to hero image picker but for the OG (social sharing) image
- **Actions:** `selectOgImage($imageId)`, `removeOgImage()`, `uploadOgImage()`

#### Inline Image Upload (Legacy)
- **Location:** Below the image buttons, inside a bordered container
- **Label:** "Insert Image (Raw URL)"
- **Behavior:** Uploads to `blog/inline/` path, inserts raw markdown `![filename](url)` into body
- **Note:** This is a legacy approach; the managed `{{image:ID}}` system is preferred

### ACP Blog Images Tab (`resources/views/livewire/admin-manage-blog-images-page.blade.php`)

A Livewire Volt component rendered inside the Admin Control Panel under Content > Blog Images.

**Features:**
- Paginated table (20 per page) of all blog images
- Sortable columns: Title, Uploaded date (default: created_at desc)
- Search by title (live, 300ms debounce)
- Table columns: Thumbnail, Title, Alt Text, Uploaded By (linked to profile), Usage Count, Uploaded date, Actions
- Usage count shows as blue badge (e.g., "2 posts") or zinc "Unused" badge
- Delete button only shown for images with zero references
- Delete requires `wire:confirm` confirmation dialog
- Authorization: `manage-blog` gate on delete action

**Tab location in ACP (`resources/views/livewire/admin-control-panel-tabs.blade.php`):**
- Category: Content
- Tab name: `blog-images`
- Tab label: "Blog Images"
- Guarded by: `@can('manage-blog')`

---

## 8. Actions (Business Logic)

### `UploadBlogImage` (`app/Actions/UploadBlogImage.php`)

**Signature:** `handle(User $uploader, UploadedFile $file, string $title, string $altText): BlogImage`

**Behavior:**
1. Stores the file to `blog/images/` on the configured public disk (`config('filesystems.public_disk')`)
2. Creates a `BlogImage` record with the provided metadata
3. Records activity: `blog_image_uploaded`
4. Returns the created `BlogImage`

### `SyncBlogPostImages` (`app/Actions/SyncBlogPostImages.php`)

**Signature:** `handle(BlogPost $post, ?string $body = null): void`

**Behavior:**
1. Parses `{{image:ID}}` tags from the body (or provided body override) using regex `/\{\{image:(\d+)(?:\|[^}]*)?\}\}/`
2. Adds `hero_image_id` and `og_image_id` to the reference list if set on the post
3. De-duplicates and filters to only valid (existing) image IDs
4. Syncs the `blog_image_post` pivot table
5. For removed references: checks if each removed image has zero remaining references; if so, sets `unreferenced_at = now()`
6. For added references: clears `unreferenced_at` (sets to null)

**Called by:**
- `CreateBlogPost::handle()` -- after creating the post
- `UpdateBlogPost::handle()` -- after updating the post
- `DeleteBlogPost::handle()` -- with empty body string `''` to release all references before soft-deleting

### `DeleteBlogImage` (`app/Actions/DeleteBlogImage.php`)

**Signature:** `handle(BlogImage $image): void`

**Behavior:**
1. Checks if the image has any post references; throws `RuntimeException` if so: "Cannot delete a blog image that is still referenced by posts."
2. Records activity: `blog_image_deleted`
3. Deletes the database record
4. Deletes the file from the configured public disk

### `CreateBlogPost` (`app/Actions/CreateBlogPost.php`) -- image hook

After creating the post record, calls `SyncBlogPostImages::run($post)` to establish initial image references. Accepts `hero_image_id` and `og_image_id` in the data array.

### `UpdateBlogPost` (`app/Actions/UpdateBlogPost.php`) -- image hook

After updating the post record (including `hero_image_id`, `og_image_id`, and `body`), calls `SyncBlogPostImages::run($post)` to re-sync references.

### `DeleteBlogPost` (`app/Actions/DeleteBlogPost.php`) -- image hook

Before soft-deleting the post, calls `SyncBlogPostImages::run($post, '')` with an empty body to release all image references, allowing the unreferenced_at lifecycle to begin for any orphaned images.

---

## 9. Notifications

Blog Image Management does not send any notifications. All feedback is delivered via `Flux::toast()` in the Livewire components.

---

## 10. Background Jobs

### `CleanupUnreferencedBlogImages` (`app/Jobs/CleanupUnreferencedBlogImages.php`)

**Implements:** `ShouldQueue`

**Schedule:** Monthly (configured in `routes/console.php`)

**Behavior:**
1. Queries all `BlogImage` records where `unreferenced_at` is not null and `unreferenced_at <= now()->subDays(30)`
2. For each qualifying image, calls `DeleteBlogImage::run($image)`
3. This deletes both the database record and the stored file
4. Activity is logged for each deletion

**Grace period:** 30 days from when the image lost all post references. Images unreferenced for exactly 30 days are included; those at 29 days are not.

---

## 11. Console Commands & Scheduled Tasks

No custom Artisan commands exist for blog image management. The cleanup is handled by a scheduled job (see section 10).

| Task | Type | Schedule | Definition |
|---|---|---|---|
| Cleanup unreferenced blog images | `Schedule::job()` | Monthly | `routes/console.php` |

---

## 12. Services

### `StorageService` (`app/Services/StorageService.php`)

Used by `BlogImage::url()` to generate the public URL for an image's storage path. Called as `StorageService::publicUrl($this->path)`.

---

## 13. Activity Log Entries

| Action Key | Description Template | Triggered By |
|---|---|---|
| `blog_image_uploaded` | `Blog image "{title}" uploaded by {uploader_name}.` | `UploadBlogImage` action |
| `blog_image_deleted` | `Blog image "{title}" deleted.` | `DeleteBlogImage` action |

Both log entries use `RecordActivity::run($image, $action, $description)` with the `BlogImage` model as the subject.

---

## 14. Data Flow Diagrams

### Image Upload Flow (Editor)

```
User clicks "Upload Image" in editor
  -> Fills in Title, Alt Text, selects file
  -> Calls uploadBlogImage()
    -> Validates file (mimes, max size from SiteConfig)
    -> UploadBlogImage::run(user, file, title, altText)
      -> file->store('blog/images', public_disk)
      -> BlogImage::create(...)
      -> RecordActivity::run(...)
    -> Appends {{image:ID}} to postBody
    -> Closes modal, shows toast
```

### Image Reference Sync Flow

```
CreateBlogPost / UpdateBlogPost / DeleteBlogPost
  -> SyncBlogPostImages::run($post, $body?)
    -> Parse {{image:ID}} tags from body
    -> Add hero_image_id, og_image_id to referenced IDs
    -> Filter to valid (existing) IDs
    -> Sync pivot table (blog_image_post)
    -> For removed images:
      -> Check if image has 0 remaining references
      -> If orphaned: set unreferenced_at = now()
    -> For added images:
      -> Clear unreferenced_at (set null)
```

### Image Cleanup Flow

```
Monthly scheduled job: CleanupUnreferencedBlogImages
  -> Query: unreferenced_at IS NOT NULL AND unreferenced_at <= 30 days ago
  -> For each image:
    -> DeleteBlogImage::run($image)
      -> Verify no post references (should be zero)
      -> RecordActivity::run(...)
      -> Delete DB record
      -> Delete file from storage
```

### Hero/OG Image Selection Flow

```
User opens Hero/OG Image Picker modal
  -> Browses existing images (with search)
  -> Option A: Select existing image -> sets heroImageId/ogImageId
  -> Option B: Upload new image
    -> UploadBlogImage::run(...)
    -> Sets heroImageId/ogImageId to new image ID
  -> On post save:
    -> hero_image_id / og_image_id stored on BlogPost
    -> SyncBlogPostImages includes them as references
```

### Image Rendering Flow (Public View)

```
BlogPost::renderBody()
  -> Str::markdown($this->body) converts to HTML
  -> Regex callback finds {{image:ID}} and {{image:ID|alt}} tags
  -> For each tag:
    -> BlogImage::find($id)
    -> If found: <img src="url" alt="alt_text_or_override" class="rounded-lg" />
    -> If not found: empty string
```

---

## 15. Configuration

| Config Key | Source | Default | Description |
|---|---|---|---|
| `filesystems.public_disk` | `config/filesystems.php` | varies | Storage disk name used for uploads and file deletion |
| `max_image_size_kb` | `SiteConfig` (database) | `2048` | Maximum upload file size in KB; used for validation and displayed in upload UI |

**Accepted file types (hardcoded in validation):** `jpg`, `jpeg`, `png`, `gif`, `webp`

**Storage paths:**
- Managed images: `blog/images/{auto-generated-filename}`
- Legacy inline images: `blog/inline/{auto-generated-filename}`

---

## 16. Test Coverage

### `tests/Feature/Blog/BlogImageTest.php`

**Groups:** `blog`, `blog-images`

| Test | Description |
|---|---|
| `it('creates a blog image record and stores the file')` | Verifies UploadBlogImage creates a record with correct metadata and stores the file |
| `it('records activity when a blog image is uploaded')` | Checks activity_logs entry with action `blog_image_uploaded` |
| `it('has an uploadedBy relationship')` | Verifies BlogImage -> User relationship |
| `it('has a posts relationship')` | Verifies BlogImage -> BlogPost many-to-many via pivot |
| `it('renders an image tag with default alt text')` | Tests `{{image:ID}}` rendering in renderBody() |
| `it('renders an image tag with override alt text')` | Tests `{{image:ID\|Custom alt}}` rendering |
| `it('renders empty string for invalid image ID')` | Tests graceful handling of non-existent image IDs |
| `it('renders empty string for missing image ID of zero')` | Tests `{{image:0}}` edge case |
| `it('resolves multiple image tags in one post body')` | Tests multiple image tags in a single body |
| `it('handles a mix of valid and invalid image tags')` | Tests body with both valid and invalid image IDs |
| `it('allows users with manage-blog gate to upload images')` | Verifies authorized access to editor |
| `it('denies upload to users without manage-blog gate')` | Verifies unauthorized users get 403 |
| `it('displays the image gallery with existing images')` | Tests gallery rendering in editor |
| `it('filters gallery images by title search')` | Tests gallery search functionality |
| `it('shows empty state when no gallery images match search')` | Tests empty gallery search results |
| `it('inserts gallery image tag into post body')` | Tests insertGalleryImage() appends correct tag |
| `it('denies gallery image insertion to users without manage-blog gate')` | Tests auth on insertGalleryImage |
| `it('selects an existing image as hero image')` | Tests selectHeroImage() sets heroImageId |
| `it('removes the selected hero image')` | Tests removeHeroImage() nullifies heroImageId |
| `it('uploads and selects a new hero image')` | Tests uploadHeroImage() creates image and selects it |
| `it('shows thumbnail preview when hero image is selected')` | Tests hero image preview rendering |
| `it('selects an existing image as OG image')` | Tests selectOgImage() sets ogImageId |
| `it('removes the selected OG image')` | Tests removeOgImage() nullifies ogImageId |
| `it('uploads and selects a new OG image')` | Tests uploadOgImage() creates image and selects it |
| `it('saves hero and OG image IDs when creating a post')` | Tests hero_image_id/og_image_id persist on create |
| `it('loads hero and OG image IDs when editing a post')` | Tests editor loads existing image selections |
| `it('has heroImage belongsTo relationship')` | Tests BlogPost -> BlogImage via hero_image_id |
| `it('has ogImage belongsTo relationship')` | Tests BlogPost -> BlogImage via og_image_id |
| `it('resolves heroImageUrl through the BlogImage record')` | Tests heroImageUrl() returns correct URL |
| `it('resolves ogImageUrl through the BlogImage record')` | Tests ogImageUrl() returns correct URL |
| `it('returns null for heroImageUrl when no hero image is set')` | Tests null hero_image_id returns null URL |
| `it('returns null for ogImageUrl when no OG image is set')` | Tests null og_image_id returns null URL |
| `it('renders hero image on published post from managed system')` | Integration test: hero image visible on public page |
| `it('includes OG image meta tag from managed system')` | Integration test: og:image meta tag on public page |
| `it('includes hero image in JSON-LD when set from managed system')` | Integration test: JSON-LD structured data includes image |

### `tests/Feature/Blog/SyncBlogPostImagesTest.php`

**Groups:** `blog`, `blog-images`, `sync-blog-images`

| Test | Description |
|---|---|
| `it('parses image tags from the post body and creates pivot entries')` | Basic tag parsing and pivot creation |
| `it('parses image tags with alt text overrides')` | Parsing `{{image:ID\|alt}}` syntax |
| `it('ignores invalid image IDs that do not exist in the database')` | Non-existent IDs filtered out |
| `it('handles duplicate image tags in the body')` | Duplicate tags produce single pivot entry |
| `it('handles empty body with no image tags')` | No image tags results in zero references |
| `it('adds new references when images are added to the body')` | Incremental reference addition |
| `it('removes pivot entries when images are removed from the body')` | Reference removal on body change |
| `it('sets unreferenced_at when an image loses all references')` | Unreferenced_at lifecycle: mark |
| `it('clears unreferenced_at when an image gains a reference')` | Unreferenced_at lifecycle: clear |
| `it('does not set unreferenced_at if image is still referenced by another post')` | Multi-post reference safety |
| `it('counts hero_image_id as a reference')` | Hero image tracked as reference |
| `it('counts og_image_id as a reference')` | OG image tracked as reference |
| `it('counts both hero_image_id and og_image_id as references')` | Both special image IDs counted |
| `it('syncs image references when a blog post is created')` | Integration with CreateBlogPost |
| `it('syncs image references when a blog post is updated')` | Integration with UpdateBlogPost |
| `it('sets unreferenced_at on removed images during update')` | Unreferenced_at set on update |
| `it('releases all image references when a blog post is deleted')` | Integration with DeleteBlogPost |
| `it('does not set unreferenced_at on delete if image is still referenced by another post')` | Multi-post safety on delete |

### `tests/Feature/Blog/AcpBlogImagesTest.php`

**Groups:** `blog`, `blog-images`, `acp`

| Test | Description |
|---|---|
| `it('displays blog images table for users with manage-blog gate')` | Table renders for authorized users |
| `it('displays image metadata in the table')` | Title, alt text, uploader name shown |
| `it('displays usage count for images with references')` | Badge shows post count |
| `it('displays unused badge for images with zero references')` | "Unused" badge for unreferenced images |
| `it('paginates blog images')` | 20 per page pagination |
| `it('filters images by title search')` | Search functionality |
| `it('shows empty state when no images match search')` | Empty search results message |
| `it('allows deleting an image with zero references')` | Successful delete flow |
| `it('records activity when a blog image is deleted')` | Activity log on delete |
| `it('blocks deleting an image with active references via component')` | Delete blocked in UI for referenced images |
| `it('blocks deleting an image with active references via action')` | RuntimeException from DeleteBlogImage action |
| `it('denies access to blog images tab for users without manage-blog gate')` | Auth: unauthorized delete returns 403 |
| `it('denies delete action for users without manage-blog gate')` | Auth: unauthorized user cannot delete |

### `tests/Feature/Blog/CleanupUnreferencedBlogImagesTest.php`

**Groups:** `blog`, `blog-images`, `blog-cleanup`

| Test | Description |
|---|---|
| `it('deletes images unreferenced for 30+ days')` | Images past grace period are deleted |
| `it('does not delete images unreferenced for less than 30 days')` | Images within grace period are kept |
| `it('does not delete images that still have references')` | Referenced images (null unreferenced_at) are kept |
| `it('deletes S3 file when cleaning up an unreferenced image')` | Storage file is removed |
| `it('logs activity for each deleted image')` | Activity log entry created per deletion |
| `it('handles multiple images with mixed states correctly')` | Mixed old/recent/referenced batch processing |
| `it('deletes images unreferenced for exactly 30 days')` | Boundary: 30 days included |
| `it('does not delete images unreferenced for 29 days')` | Boundary: 29 days excluded |

---

## 17. File Map

| Category | File Path |
|---|---|
| **Model** | `app/Models/BlogImage.php` |
| **Model** | `app/Models/BlogPost.php` (image relationships, renderBody) |
| **Action** | `app/Actions/UploadBlogImage.php` |
| **Action** | `app/Actions/SyncBlogPostImages.php` |
| **Action** | `app/Actions/DeleteBlogImage.php` |
| **Action** | `app/Actions/CreateBlogPost.php` (sync hook) |
| **Action** | `app/Actions/UpdateBlogPost.php` (sync hook) |
| **Action** | `app/Actions/DeleteBlogPost.php` (sync hook) |
| **Job** | `app/Jobs/CleanupUnreferencedBlogImages.php` |
| **Migration** | `database/migrations/2026_03_20_300001_create_blog_images_table.php` |
| **Migration** | `database/migrations/2026_03_20_400001_replace_blog_post_image_paths_with_fks.php` |
| **Factory** | `database/factories/BlogImageFactory.php` |
| **Volt Component** | `resources/views/livewire/blog/editor.blade.php` |
| **Volt Component** | `resources/views/livewire/admin-manage-blog-images-page.blade.php` |
| **Volt Component** | `resources/views/livewire/admin-control-panel-tabs.blade.php` |
| **Authorization** | `app/Providers/AuthServiceProvider.php` (`manage-blog` gate) |
| **Policy** | `app/Policies/BlogPostPolicy.php` |
| **Service** | `app/Services/StorageService.php` |
| **Schedule** | `routes/console.php` |
| **Routes** | `routes/web.php` |
| **Test** | `tests/Feature/Blog/BlogImageTest.php` |
| **Test** | `tests/Feature/Blog/SyncBlogPostImagesTest.php` |
| **Test** | `tests/Feature/Blog/AcpBlogImagesTest.php` |
| **Test** | `tests/Feature/Blog/CleanupUnreferencedBlogImagesTest.php` |

---

## 18. Known Issues & Improvement Opportunities

1. **No image editing/metadata update.** Once uploaded, an image's title and alt text cannot be changed. An edit form in the ACP or editor would improve usability.

2. **No image resizing or thumbnail generation.** Images are stored at original size. Adding automatic thumbnail generation would improve page load times and reduce bandwidth, especially for the gallery grid views.

3. **Legacy inline image upload persists.** The editor still includes the "Insert Image (Raw URL)" upload that stores files to `blog/inline/` without creating `BlogImage` records. These images are not tracked, not reference-counted, and not cleaned up. Consider deprecating or removing this path.

4. **Gallery loads all images.** The editor's image gallery and picker modals load all `BlogImage` records without pagination (`$galleryQuery->get()`). This could become slow as the image library grows. Adding pagination or lazy loading would improve performance.

5. **No image dimension or file size display.** The ACP and editor do not display image dimensions or file size, which would help authors choose appropriate images.

6. **Cleanup job runs monthly.** The `CleanupUnreferencedBlogImages` job runs monthly, meaning orphaned images could exist for up to 30 days grace + up to 30 days until the next job run (up to 60 days total). Running weekly would tighten this window.

7. **No bulk delete in ACP.** The ACP Blog Images tab only supports deleting one image at a time. Bulk selection and deletion would improve admin workflows.

8. **Delete protection is reference-count-only.** The `DeleteBlogImage` action prevents deletion if the image has post references, but does not check if the image is used as a hero or OG image directly. The `nullOnDelete` foreign key constraint handles this at the database level (nullifying the FK), but the UI could warn that removing an image will clear it as a hero/OG image from posts.

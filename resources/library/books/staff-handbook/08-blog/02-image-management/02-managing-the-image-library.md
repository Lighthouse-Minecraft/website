---
title: 'Managing the Image Library'
visibility: staff
order: 2
summary: 'How to search, review, and delete images from the ACP Blog Images tab.'
---

## Overview

The **Blog Images** tab in the Admin Control Panel gives you a bird's-eye view of every image in the blog library. This is where you go to check what's been uploaded, see which images are actively used in posts, and clean up unused ones.

## Getting to the Blog Images Tab

1. Go to the [Admin Control Panel]({{url:/acp}})
2. Look under the **Content** section for the **Blog Images** tab
3. Click it to see the full image library

You need the **Blog Author** role to see this tab.

## Understanding the Image Table

The table shows all blog images with these columns:

| Column | What It Shows |
|---|---|
| **Thumbnail** | A small preview of the image |
| **Title** | The name given when the image was uploaded |
| **Alt Text** | The accessibility description (truncated if long) |
| **Uploaded By** | The staff member who uploaded it -- click their name to view their profile |
| **Usage Count** | How many posts reference this image (see below) |
| **Uploaded** | The date the image was added to the library |
| **Actions** | Delete button, when available |

## Sorting and Searching

You can sort the table by clicking the **Title** or **Uploaded** column headers. Click once to sort descending, click again to flip to ascending. By default, the newest images appear first.

Use the **search box** at the top to filter images by title. The search updates as you type, so you'll see results narrow down quickly.

## Understanding Usage Counts

The **Usage Count** column tells you how many posts currently reference each image. This includes posts that embed the image in the body, and posts that use it as a hero image or OG image.

- A **blue badge** (e.g., "2 posts") means the image is actively used
- A gray **"Unused"** badge means no posts currently reference the image

An image can become unused when:
- The post that used it was deleted
- The image tag was removed from a post's body during editing
- The image was swapped out as a hero or OG image

## Deleting Images

You can only delete images that have **zero post references** -- the Delete button only appears for images with the "Unused" badge.

To delete an image:

1. Find the unused image in the table
2. Click the red **Delete** button in the Actions column
3. A confirmation dialog will ask "Are you sure you want to delete this image? This cannot be undone."
4. Confirm to permanently remove the image from both the library and file storage

**If you don't see a Delete button** on an image, it's because that image is still referenced by one or more posts. You'll need to remove it from those posts first (edit the post body to remove the `{{image:ID}}` tag, or change the hero/OG image selection).

## Automatic Cleanup

You don't need to manually track down every unused image. The system runs an automatic cleanup process:

1. When an image loses all its post references, the system notes the date
2. The image enters a **30-day grace period** -- it stays in the library in case you want to re-use it
3. After 30 days with no references, a monthly cleanup job permanently deletes the image and its file

This means if you remove an image from a post by mistake, you have about 30 days to add it back to a post before it's cleaned up. Once you re-reference it in any post, the cleanup timer resets.

**The bottom line:** Focus on manually deleting images you know are wrong or outdated. For everything else, the system will take care of cleanup over time.

## Common Scenarios

### "I uploaded the wrong image"

If nobody has used it in a post yet, find it in the Blog Images tab and delete it right away. If it's already in a post, edit the post to remove the image tag first, then come back and delete it.

### "I want to replace an image"

Upload the new image through the editor, update the post to use the new one, and remove references to the old image. The old image will either be available for manual deletion immediately (if unused) or will be cleaned up automatically after 30 days.

### "There are a lot of unused images piling up"

This is normal over time. The automatic cleanup handles it -- unused images are deleted after 30 days. If you want to clean things up sooner, you can manually delete any image showing the "Unused" badge.

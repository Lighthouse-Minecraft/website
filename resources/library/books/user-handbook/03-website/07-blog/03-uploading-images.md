---
title: 'Uploading Images'
visibility: citizen
order: 3
summary: 'How to upload and insert images into your blog posts using the image manager.'
---

## Overview

When writing a blog post, you can upload images directly from the editor and insert them into your post body. Every uploaded image is stored in a shared **image library** that all blog authors can browse and reuse.

Each image requires a **title** and **alt text** before it can be uploaded. The title helps you find the image later when browsing the library. The alt text is a description of what the image shows -- it's used by screen readers and search engines, so take a moment to write something meaningful.

## Supported File Types and Size

You can upload images in the following formats: **JPG**, **PNG**, **GIF**, and **WEBP**. The maximum file size is shown in the upload area (typically 2MB, but this may vary based on site settings).

## Uploading a New Image

1. Open the blog editor by creating a new post or editing an existing one.
2. Below the body text area, click **Upload Image**.
3. In the modal that appears, fill in the required fields:
   - **Title** -- A short, descriptive name for the image (e.g., "Summer Festival Group Photo").
   - **Alt Text** -- A description of what the image shows (e.g., "Players gathered around the fountain at spawn during the summer festival").
   - **Image File** -- Drag and drop your image into the upload area, or click to browse your files.
4. You'll see a small preview of the image once it's selected.
5. Click **Upload & Insert**.

The image is uploaded to the library, and an image tag is automatically inserted at the end of your post body. You can then move the tag to wherever you'd like it to appear.

## The Image Tag

When an image is inserted, you'll see a tag like this in your post body:

`{{image:42}}`

This tag tells the system to display that specific image when readers view your post. You can move this tag anywhere in your post body -- just cut and paste it to the spot where you want the image to appear.

If you'd like to use custom alt text for a specific use of the image (different from the default alt text you set when uploading), you can add it after a pipe character:

`{{image:42|A closer view of the fountain}}`

## Tips for Good Images

- **Use descriptive titles.** "Screenshot 2026-03-20" isn't helpful when you're browsing the library later. Something like "Spawn Castle Aerial View" is much easier to find.
- **Write meaningful alt text.** Describe what's in the image as if you were telling someone who can't see it. This helps with accessibility and search engine visibility.
- **Check file size before uploading.** If your image is too large, resize it before uploading. Most screenshots and photos can be reduced in size without noticeable quality loss.

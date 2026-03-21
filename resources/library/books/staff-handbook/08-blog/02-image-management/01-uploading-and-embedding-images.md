---
title: 'Uploading and Embedding Images'
visibility: staff
order: 1
summary: 'How to upload images and embed them in blog post content.'
---

## Overview

Blog posts support a managed image system that keeps track of every image you upload. When you add an image through the editor, it gets stored in the image library with a title and alt text, and the system tracks which posts use it. This is better than pasting raw image URLs because it keeps things organized and accessible.

## Uploading a New Image

While editing a post, you'll see two buttons below the body text area: **Upload Image** and **Browse Images**.

To upload a fresh image:

1. Click **Upload Image** -- a modal will open
2. Fill in the **Title** -- a short, descriptive name (e.g., "Summer Event Banner")
3. Fill in the **Alt Text** -- describe what the image shows for accessibility (e.g., "Players gathered around the town square for the summer event")
4. Select the **Image File** from your computer
5. Click **Upload**

The image is saved to the library and automatically inserted into your post body as a tag like `{{image:42}}`. You don't need to worry about this tag -- it gets rendered as a proper image when the post is published.

**Accepted formats:** JPG, JPEG, PNG, GIF, WebP

## Browsing and Inserting Existing Images

If the image you want is already in the library (uploaded by you or another Blog Author), you don't need to upload it again.

1. Click **Browse Images** -- the image gallery modal opens
2. Use the **search box** to filter by title if you're looking for something specific
3. Find the image you want and click **Insert**

The image tag is appended to the end of your post body. You can then move it to the right spot in the text.

## How Image Tags Work

When you insert a managed image, the editor adds a tag like this to your post body:

`{{image:42}}`

When the post is published, this tag is replaced with the actual image, using the alt text you set when uploading. If you want to override the alt text for a specific use, you can edit the tag manually:

`{{image:42|Custom alt text for this context}}`

This is useful when the same image means something slightly different in different posts.

## Choosing Hero and OG Images

Each post can have two special images:

- **Hero Image** -- the large banner displayed at the top of the post
- **OG Image** -- the image shown when someone shares the post on social media (Open Graph image)

Both are selected from the **Images** card in the editor. For each one:

1. Click **Select Hero Image** (or **Select OG Image**) -- a picker modal opens
2. Browse existing images or use the search box to find one
3. Click the image to select it -- selected images get a blue highlight
4. Or scroll to the bottom of the modal to **upload a new image** directly from the picker

Once selected, you'll see a thumbnail preview with the image title. You can click **Change** to pick a different image or **Remove** to clear the selection.

**Good to know:** The hero image and OG image are independent. If you set a hero image but skip the OG image, social media shares won't have an image -- it doesn't fall back to the hero automatically.

## Tips for Good Images

- **Always write meaningful alt text.** Screen readers depend on it, and it helps with SEO too. "Screenshot" is not helpful -- "Player builds on the west side of spawn" is.
- **Give images clear titles.** "IMG_2847" doesn't help anyone find it later. "March 2026 Build Contest Winners" does.
- **Reuse images when you can.** The Browse Images gallery exists so you don't upload duplicates. Search before uploading something that might already be there.
- **Check the file size.** Large images slow down page loads. If your image is very large, consider resizing it before uploading.

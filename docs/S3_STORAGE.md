# AWS S3 Storage Setup

This document explains how file uploads work in the Lighthouse Website and how to configure AWS S3 for staging and production environments.

---

## How It Works

All user-uploaded files (images, photos) are stored on a configurable "public disk." The disk
is selected by the `FILESYSTEM_PUBLIC_DISK` environment variable:

- **Local development:** `FILESYSTEM_PUBLIC_DISK=public` (default) — files are stored in `storage/app/public/` and served via the `/storage` symlink.
- **Staging / Production:** `FILESYSTEM_PUBLIC_DISK=s3` — files are stored in a **private** AWS S3 bucket and served via temporary signed URLs (60-minute expiry).

This is handled by `App\Services\StorageService::publicUrl()`, which detects the disk driver at runtime. All models and components use this service to generate file URLs, so no code changes are needed per environment — only the `.env` value changes.

### Storage Directories in the Bucket

| Directory | Content | Uploaded By |
|---|---|---|
| `community-stories/` | Community story response images | Members |
| `board-member-photos/` | Board member profile photos | Admins |
| `staff-photos/` | Staff bio photos | Staff members |

---

## 1. Create the S3 Bucket

1. Go to the [AWS S3 Console](https://s3.console.aws.amazon.com/).
2. Click **Create bucket**.
3. Choose a name (e.g., `lighthouse-website-uploads`).
4. Select a region close to your server (e.g., `us-east-1`).
5. **Block all public access** — leave this **enabled** (all checkboxes checked). The bucket must be **private**. The application generates temporary signed URLs for all file access.
6. Leave all other settings at defaults and create the bucket.

### Bucket Policy

No bucket policy is needed. The bucket stays fully private. The IAM user credentials (configured below) handle all access.

### CORS Configuration

If images fail to load in the browser due to CORS, add this CORS configuration to the bucket (Bucket → Permissions → CORS):

```json
[
    {
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "PUT", "POST"],
        "AllowedOrigins": ["https://www.lighthousemc.net"],
        "ExposeHeaders": [],
        "MaxAgeSeconds": 3600
    }
]
```

Replace with the staging URL if setting up on the staging environment.

---

## 2. Create an IAM User

1. Go to the [IAM Console](https://console.aws.amazon.com/iam/).
2. Click **Users → Create user**.
3. Name it something like `lighthouse-website-s3`.
4. Do **not** enable console access.
5. Attach an **inline policy** with the following JSON (replace `lighthouse-website-uploads`
   with your actual bucket name):

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::lighthouse-website-uploads",
                "arn:aws:s3:::lighthouse-website-uploads/*"
            ]
        }
    ]
}
```

6. After creating the user, go to **Security credentials → Create access key**.
7. Select **Application running outside AWS**.
8. Save the **Access Key ID** and **Secret Access Key** — you will need them for the `.env`.

---

## 3. Configure the Server `.env`

Add/update these variables in your server's `.env` file:

```env
FILESYSTEM_PUBLIC_DISK=s3

AWS_ACCESS_KEY_ID=your-access-key-id
AWS_SECRET_ACCESS_KEY=your-secret-access-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=lighthouse-website-uploads
```

That's it. No other code or config changes are needed. The application will automatically:
- Upload files to S3 instead of local storage
- Generate 60-minute signed URLs for all file access
- Delete files from S3 when records are removed

### Optional Variables

| Variable | Default | Purpose |
|---|---|---|
| `AWS_URL` | (none) | Custom URL for the bucket (e.g., CloudFront distribution) |
| `AWS_ENDPOINT` | (none) | Custom S3-compatible endpoint (e.g., MinIO, DigitalOcean Spaces) |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` | Set to `true` for S3-compatible services that require path-style URLs |

---

## 4. Local Development

No AWS credentials are needed for local development. The default `.env` ships with:

```env
FILESYSTEM_PUBLIC_DISK=public
```

This stores files in `storage/app/public/` and serves them through Laravel's storage symlink.
Make sure the symlink exists:

```bash
php artisan storage:link
```

---

## 5. Migrating Existing Files

If you have existing uploaded files in `storage/app/public/` that need to move to S3:

```bash
# Sync local files to S3 (install AWS CLI first: https://aws.amazon.com/cli/)
aws s3 sync storage/app/public/ s3://lighthouse-website-uploads/ \
    --exclude ".gitignore"
```

After syncing, verify files are accessible by loading a page that displays uploaded images.

---

## 6. Verify the Setup

After configuring the `.env` and deploying:

1. Upload a staff photo via Settings → Staff Bio.
2. Confirm the photo displays correctly on the Staff page.
3. Check the S3 bucket in the AWS Console — the file should appear under `staff-photos/`.
4. Right-click the image in your browser and copy the URL — it should be a long signed URL
   with `X-Amz-Signature` and `X-Amz-Expires` query parameters, not a plain public URL.

---

## Architecture Reference

| Component | Purpose |
|---|---|
| `config/filesystems.php` → `public_disk` | Selects which disk to use for uploads |
| `config/filesystems.php` → `disks.s3` | S3 connection settings (from `.env`) |
| `App\Services\StorageService::publicUrl()` | Generates signed URLs for S3, plain URLs for local |
| `.env` → `FILESYSTEM_PUBLIC_DISK` | The only value that changes between environments |

All file uploads and URL generation go through `config('filesystems.public_disk')` and
`StorageService::publicUrl()` respectively. To add S3 support to a new feature, just use
these two patterns and it will work in both local and production environments automatically.

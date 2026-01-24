## Production Upload Storage

This backend stores user-uploaded media (product images, category thumbnails, branding/logo) on the filesystem disk configured by `UPLOADS_DISK`.

### Recommended: S3 (persists across deploys)

Set environment variables:

- `UPLOADS_DISK=s3`
- `AWS_ACCESS_KEY_ID=...`
- `AWS_SECRET_ACCESS_KEY=...`
- `AWS_DEFAULT_REGION=...`
- `AWS_BUCKET=...`
- Optional: `AWS_URL=https://<bucket-or-cdn-domain>`

Uploads will be written to S3, and files will be served via:

- `GET /api/products/{id}/images/{filename}` (product images)
- `GET /api/media/{path}` (branding/category thumbnails, and any allowed paths)

### Alternative: Render Persistent Disk (persists across deploys)

If you host the Laravel container on Render, create a **Persistent Disk** and mount it to:

- `/var/www/html/storage/app/public`

Then set:

- `UPLOADS_DISK=public`

This keeps Laravel's `public` disk data across deploys.

### Why `/storage/...` URLs are avoided

Some hosting setups can block direct access to `/storage/*` (or symlinked folders).
This project serves uploads through API routes (`/api/media/*` and `/api/products/*/images/*`) so the frontend always has a stable, host-agnostic URL.


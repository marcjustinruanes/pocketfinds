# Category photos

Open **Admin → Settings → Category Photos**, choose a JPG, PNG or WebP for a category, and click **Save Photo**. Images must be no larger than 4 MB or 3000 × 3000 pixels. Uploads are cropped to the center, resized to 480 × 480 pixels, displayed as squares, and stored as JPEGs directly in MySQL.

The `categories` table stores `image_data` (MEDIUMBLOB), `image_mime` (VARCHAR(32)), `image_sha256` (VARCHAR(64)), and `image_source` (TEXT). The public image endpoint serves database bytes, uses ETags, and the homepage adds the image hash to the URL so replacements refresh immediately. Category names and product links are unchanged. New categories without a photo show their initial until the administrator uploads one.

Run `php artisan migrate` on another installation to add these columns. The existing database already has the migration and all eight initial photos. The Workbench review SQL includes the new columns; do not execute its CREATE TABLE statements over the populated database.

Initial photographs are from Unsplash. Their source image URLs are recorded in `categories.image_source`; admin uploads clear that field. Copies are retained in the private `storage/app/private/category-photos` directory. To reimport those originals deliberately, run `php artisan categories:import-photos storage/app/private/category-photos` (this replaces the current category photos).

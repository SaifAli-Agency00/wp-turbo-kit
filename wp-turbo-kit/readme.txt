=== WP Turbo Kit — Media Optimizer ===
Contributors:       saifadvertidea
Tags:               webp, image optimization, compress images, media optimizer, convert images
Requires at least:  5.8
Tested up to:       6.7
Requires PHP:       7.4
Stable tag:         1.0.0
License:            GPLv2 or later
License URI:        https://www.gnu.org/licenses/gpl-2.0.html

Automatically convert images to WebP, compress them under your target size, find & delete unused images — with a beautiful animated dashboard.

== Description ==

**WP Turbo Kit — Media Optimizer** is a premium-quality, free image optimization plugin for WordPress. It converts your images to WebP format, compresses them to your target file size, and includes an unused image finder to clean up your media library.

= ✨ Key Features =

* **Auto WebP Conversion** — New image uploads are automatically converted to WebP on the fly
* **Bulk Optimizer** — Convert your entire existing media library with a real-time progress bar
* **Smart Compression** — Iteratively reduces quality until your target file size is hit (default 100KB)
* **Thumbnail Conversion** — All registered WordPress image sizes are converted too
* **Unused Image Finder** — Scans posts, pages, widgets, ACF fields, WooCommerce galleries and more to find truly unused images
* **Setup Wizard** — On first install, a beautiful animated wizard checks your server and guides you through setup
* **Premium Dashboard** — Animated stat cards, dark terminal-style logs, real-time updates
* **Keep Originals Option** — Optionally keep your original JPG/PNG files alongside WebP versions
* **Reset & Re-optimize** — Reset optimization status and re-process any or all images

= 🖥 Server Requirements =

* **Imagick** PHP extension (recommended — best quality)
* OR **GD** library with WebP support (fallback)
* PHP 7.4 or higher
* WordPress 5.8 or higher

= 🔒 Privacy & Safety =

* No data is sent to external servers
* All processing happens on your own server
* No tracking, no analytics, no phone home

== Installation ==

1. Upload the `wp-turbo-kit` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. The setup wizard will launch automatically — follow the 3 steps
4. Go to **⚡ Turbo Kit** in the admin menu to access the dashboard

== Frequently Asked Questions ==

= Does this work with Elementor? =
Yes. The plugin works independently of your page builder. WebP images are served to any browser that supports them.

= Will my existing images be converted automatically? =
No — existing images need to be processed using the Bulk Optimizer tool in the dashboard. Only new uploads are converted automatically (if the setting is on).

= What if my server does not support Imagick or GD? =
The setup wizard will detect this and warn you. You can still install the plugin — once you enable the library from your hosting panel, it will work.

= Is the original file deleted? =
Only if you turn off the "Keep Originals" setting. By default, original files are kept.

= Can I undo the optimization? =
You can use the "Reset All" button to clear the optimization status, but the original files are only recoverable if you had "Keep Originals" enabled.

= Is this safe for a live site? =
Yes. The plugin processes images on your server without any downtime. The bulk optimizer uses small batches (5 images at a time) to prevent timeouts.

== Screenshots ==

1. Setup wizard — animated server check
2. Setup wizard — configure settings
3. Main dashboard — stat cards and bulk optimizer
4. Unused images finder — scan results grid
5. Settings panel with toggle switches

== Changelog ==

= 1.0.0 =
* Initial release
* Auto WebP conversion on upload
* Bulk optimizer with progress bar
* Unused image scanner and deleter
* Animated setup wizard
* Premium dashboard UI

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.

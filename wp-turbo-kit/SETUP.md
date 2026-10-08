# WP Turbo Kit — Setup Guide

## Step 1: Create GitHub Repository

1. Go to https://github.com/new
2. Repository name: `wp-turbo-kit`
3. Set to **Public** (required for free auto-updates)
4. DO NOT add README, .gitignore or license (we have them already)
5. Click **Create repository**

---

## Step 2: Push Plugin to GitHub

Run these commands in your plugin folder:

```bash
cd wp-turbo-kit
git init
git add .
git commit -m "Initial release v1.0.0"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/wp-turbo-kit.git
git push -u origin main
```

---

## Step 3: Update Your Username in Plugin

Open `wp-turbo-kit.php` and change:
```php
define( 'WTK_GITHUB_USER', 'YOUR_GITHUB_USERNAME' );  // ← your GitHub username
```

---

## Step 4: Create First Release (v1.0.0)

```bash
git tag v1.0.0
git push origin v1.0.0
```

GitHub Actions will automatically:
- Build the ZIP
- Create a GitHub Release
- Attach the ZIP to the release

Your plugin users will get an update notification within 12 hours.

---

## Step 5: How to Release Updates

Every time you want to release a new version:

1. Make your code changes
2. Update the version number in `wp-turbo-kit.php`:
   ```php
   define( 'WTK_VERSION', '1.0.1' );   // bump this
   ```
3. Push a new tag:
   ```bash
   git add .
   git commit -m "Version 1.0.1 - describe your changes"
   git tag v1.0.1
   git push origin main
   git push origin v1.0.1
   ```
4. GitHub Actions builds ZIP → creates Release → all sites see update notice ✅

---

## Step 6: WordPress.org Submission (Optional)

To get the plugin listed on WordPress.org:

1. Go to https://wordpress.org/plugins/developers/add/
2. Submit the plugin ZIP
3. Wait for review (usually 1–4 weeks)
4. Once approved, you can switch to the official WordPress.org update system
   (and remove WTK_Updater from the plugin)

**Requirements for WordPress.org:**
- ✅ GPL license
- ✅ readme.txt with proper format
- ✅ No external API calls without user consent
- ✅ All data sanitized and escaped
- ✅ Nonces on all forms/AJAX

---

## Auto-Update Flow (How it works)

```
User's WordPress site
        ↓ (every 12 hours)
GitHub API: api.github.com/repos/YOUR_USERNAME/wp-turbo-kit/releases/latest
        ↓
If new version tag found → WP shows "Update Available" notice
        ↓ User clicks Update Now
WordPress downloads ZIP from GitHub Release
        ↓
Plugin updated automatically ✅
```

---

## Troubleshooting

**Update not showing?**
- Transient cache lasts 12 hours. To force check: wp-admin → Updates → Check Again
- Or run: `delete_transient('wtk_github_update_...')` in wp-cli

**Wrong folder name after update?**
- The `after_install` hook in WTK_Updater handles this automatically

**GitHub Actions failing?**
- Check Actions tab in your GitHub repo for error logs
- Make sure repo is Public
